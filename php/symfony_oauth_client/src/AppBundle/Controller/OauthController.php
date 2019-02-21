<?php
/**
 * Created by PhpStorm.
 * User: dhuerta
 * Date: 10/01/18
 * Time: 14:01
 */


namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use League\OAuth2\Client\Grant\Exception\InvalidGrantException;
use AppBundle\Providers\CFE_Provider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use AppBundle\Exceptions\InvalidTokenException;



class OauthController extends Controller {

  private static $TOKENIZER_SVC = '901';

  private $ccdid;
  private $provider;
  private $cache;

  public function __construct() {

  $this->ccdid = getenv('CCDID');

    $this->provider = new CFE_Provider([
      'clientId'                => getenv('CLIENT_ID'),
      'clientSecret'            => getenv('CLIENT_SECRET'),
      'redirectUri'             => 'http://' . $_SERVER['HTTP_HOST'] . '/authorize',
      'urlAuthorize'            => getenv('CARFAX_GATEWAY') . '/oauth/authorize/',
      'urlAccessToken'          => getenv('CARFAX_GATEWAY') . '/oauth/token/',
      'urlResourceOwnerDetails' => getenv('CARFAX_GATEWAY'). '/api/'
    ]);

    $this->cache = new FilesystemAdapter();

  }

  /**
   * @Route("/resource/svc/{service}/vinreg/{vinreg}")
   * Example gateway URL: http://localhost:8888/resource/svc/251/vinreg/SDK456
   */
  public function getResourceAction($service, $vinreg)
  {
    $this->provider->setUrlResourceOwnerDetails(
      array(
        'ccdid'     => $this->ccdid,
        'svc'       => $service,
        'vinreg'    => $vinreg,
      )
    );

    return $this->processRequest();
  }

  /**
   * @Route("/cleantoken")
   *
   */
  public function getCleanCacheAction()
  {
    $this->cache->clear();

    return new Response("Cache Cleaned");
  }

  /**
   * @Route("/resource/svc/{service}/vinreg/{vinreg}/checksum/{checksum}")
   * Example gateway URL: http://localhost:8888/resource/svc/251/vinreg/SDK456/checksum/2947e24b3f82cfa143e8b32c31beabe5
   */
  public function getResourceWithChecksumAction($service, $vinreg, $checksum)
  {
    $this->provider->setUrlResourceOwnerDetails(
      array(
        'ccdid'     => $this->ccdid,
        'svc'       => $service,
        'vinreg'    => $vinreg,
        'checksum'  => $checksum
      )
    );
    return $this->processRequest();
  }


  /**
   * @Route("/generate_url/vinreg/{vinreg}/svc/{service}")
   * Example host URL: http://localhost:8888/generate_url/vinreg/SDK456/svc/251
   * Final gateway URL
   */
  public function generateUrlAction($vinreg, $service)
  {
    $this->provider->setUrlResourceOwnerDetails(
      array(
        'ccdid'     => $this->ccdid,
        'svc'       => $this::$TOKENIZER_SVC,
        'vinreg'    => $vinreg,
        'targetsvc' => $service
      )
    );
    return $this->processRequest();
  }

  /**
   * @Route("/authorize")
   */
  public function authorizedAction() {

    if (isset($_GET['error']) )
      exit('ERROR: ' . $_GET['error']);

    if (!isset($_GET['code'])) {
      // Get the state generated for you and store it to the session.
      $_SESSION['oauth2state'] = $this->provider->getState();

      // Redirect the user to the authorization URL.
      header('Location: ' . $this->provider->getAuthorizationUrl());
      exit;

      // Check given state against previously stored one to mitigate CSRF attack
    }
    elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {

      if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
      }

      exit('Invalid state');

    }
    else {
      try {
        // Try to get an access token using the authorization code grant.
        $accessToken = $this->provider->getAccessToken('authorization_code', [
          'code' => $_GET['code']
        ]);

        // Storing token in cache system
        $access_token = $this->cache->getItem('access_token');
        $access_token->set($accessToken);
        $this->cache->save($access_token);

        $requested_resource_uri = $this->cache->getItem('requested_resource_uri');
        $redirect_uri = $requested_resource_uri->get();

        if ($redirect_uri) {
          $requested_resource_uri->set(null);
          $this->cache->save($requested_resource_uri);

          return $this->redirect($redirect_uri);
        }

        $response = new Response(json_encode($accessToken));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
      }
        // Here we handle if the token generation is invalid
      catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        exit("Error msg: " . $e->getMessage());
      }
    }
  }

  private function processRequest()
  {
    $response = null;
    try {
      $accessToken = $this->getAccessToken();
      $resourceOwner = $this->provider->getResourceOwner($accessToken);
      $response = $this->prepareResponse($resourceOwner);

    } catch (InvalidTokenException $e) {

      $requested_resource_uri = $this->cache->getItem('requested_resource_uri');
      $requested_resource_uri->set('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
      $this->cache->save($requested_resource_uri);

      $response = $this->forward('AppBundle:Oauth:authorized');

    } catch (\Exception $e) {

    }
    return $response;
  }

  private function prepareResponse($resourceOwner) {

    $response = new Response();
    $content = $resourceOwner->getResponse();

    $this->checkContentErrors($content);

    if (is_array($content)) {

      if (array_key_exists('url', $content)) {
        $server_url_array = parse_url($content['url']);
        $path = preg_replace("/.+(\/svc)(.+)/", "$1$2", $server_url_array['path']);
        $content['url'] = 'http://' . $_SERVER['HTTP_HOST'] . '/resource' . $path;
      }

      $response->headers->set('Content-Type', 'application/json');
      $response->setContent(json_encode($content, JSON_UNESCAPED_SLASHES));
    } else {
      $response->headers->set('Content-Type', 'text/html');
      $response->setContent($content);
    }

    return $response;
  }

  private function getAccessToken()
  {
    $access_token = $this->cache->getItem('access_token');
    $accessToken = $access_token->get();

    if (isset($accessToken)) {

      if ($accessToken->hasExpired()) {

        $newAccessToken = $this->provider->getAccessToken(
          'refresh_token',
          [
            'refresh_token' => $accessToken->getRefreshToken()
          ]
        );
        $access_token->set($newAccessToken);
        $this->cache->save($access_token);
        $accessToken = $newAccessToken;

      }
    } else
      throw new InvalidTokenException();

    return $accessToken;
  }

  private function checkContentErrors($content)
  {
    $invalid_token = False;

    if (is_array($content)) {
      if (array_key_exists('error_code', $content)) {
        //TODO: log error
        if ($content['error_code'] === 'EA040')
          $invalid_token = True;
      }
    }
    else {
      if( strpos( $content, 'EA040' ) !== false ) {
        //TODO: log error
        $invalid_token = True;
      }

    }

    if ($invalid_token) {
      throw new InvalidTokenException();
    }

  }
}