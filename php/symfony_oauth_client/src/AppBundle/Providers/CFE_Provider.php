<?php

namespace AppBundle\Providers;

use InvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Grant\Exception\InvalidGrantException;
use AppBundle\ResourceOwners\CFE_Resource;

class CFE_Provider extends AbstractProvider
{
  use BearerAuthorizationTrait;

  /**
   * @var string
   */
  private $urlAuthorize;

  /**
   * @var string
   */
  private $urlAccessToken;

  /**
   * @var string
   */
  private $urlResourceOwnerDetails;

  /**
   * @var string
   */
  private $accessTokenMethod;

  /**
   * @var string
   */
  private $accessTokenResourceOwnerId;

  /**
   * @var array|null
   */
  private $scopes = null;

  /**
   * @var string
   */
  private $scopeSeparator;

  /**
   * @var string
   */
  private $responseError = 'error';

  /**
   * @var string
   */
  private $responseCode;

  /**
   * @var string
   */
  private $responseResourceOwnerId = 'id';

  /**
   * @param array $options
   * @param array $collaborators
   */
  public function __construct(array $options = [], array $collaborators = [])
  {

    $this->assertRequiredOptions($options);

    $possible   = $this->getConfigurableOptions();
    $configured = array_intersect_key($options, array_flip($possible));

    foreach ($configured as $key => $value) {
      $this->$key = $value;
    }

    // Remove all options that are only used locally
    $options = array_diff_key($options, $configured);

    parent::__construct($options, $collaborators);
  }

  /**
   * Returns all options that can be configured.
   *
   * @return array
   */
  protected function getConfigurableOptions()
  {
    return array_merge($this->getRequiredOptions(), [
      'accessTokenMethod',
      'accessTokenResourceOwnerId',
      'scopeSeparator',
      'responseError',
      'responseCode',
      'responseResourceOwnerId',
      'scopes',
    ]);
  }

  /**
   * Returns all options that are required.
   *
   * @return array
   */
  protected function getRequiredOptions()
  {
    return [
      'urlAuthorize',
      'urlAccessToken',
      'urlResourceOwnerDetails',
    ];
  }

  /**
   * Verifies that all required options have been passed.
   *
   * @param  array $options
   * @return void
   * @throws InvalidArgumentException
   */
  private function assertRequiredOptions(array $options)
  {
    $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

    if (!empty($missing)) {
      throw new InvalidArgumentException(
        'Required options not defined: ' . implode(', ', array_keys($missing))
      );
    }
  }

  /**
   * @inheritdoc
   */
  public function getBaseAuthorizationUrl()
  {
    $value = $this->urlAuthorize;
    return $value;
  }

  /**
   * @inheritdoc
   */
  public function getBaseAccessTokenUrl(array $params)
  {
    $value = $this->urlAccessToken;
    return $value;
  }

  /**
   * @inheritdoc
   */
  public function getResourceOwnerDetailsUrl(AccessToken $token)
  {
    $value = $this->urlResourceOwnerDetails;
    return $value;
  }

  /**
   * @inheritdoc
   */
  public function getDefaultScopes()
  {
    $value =  $this->scopes;
    return $value;
  }

  /**
   * @inheritdoc
   */
  protected function getAccessTokenMethod()
  {
    $value =  $this->accessTokenMethod ?: parent::getAccessTokenMethod();
    return $value;
  }

  /**
   * @inheritdoc
   */
  protected function getAccessTokenResourceOwnerId()
  {
    $value =  $this->accessTokenResourceOwnerId ?: parent::getAccessTokenResourceOwnerId();
    return $value;
  }

  /**
   * @inheritdoc
   */
  protected function getScopeSeparator()
  {
    $value =  $this->scopeSeparator ?: parent::getScopeSeparator();
    return $value;
  }

  /**
   * @inheritdoc
   */
  protected function checkResponse(ResponseInterface $response, $data)
  {
    if (!empty($data[$this->responseError])) {
      $error = $data[$this->responseError];
      $code  = $this->responseCode ? $data[$this->responseCode] : 0;
      throw new IdentityProviderException($error, $code, $data);
    }
  }

  /**
   * Requests and returns the resource owner of given access token.
   *
   * @param  AccessToken $token
   * @return ResourceOwnerInterface
   */
  public function getResourceOwner(AccessToken $token)
  {
    $response = $this->fetchResourceOwnerDetails($token);
    $array_response = array('response' => $response);

    return $this->createResourceOwner($array_response, $token);
  }

  /**
   * @inheritdoc
   */
  protected function createResourceOwner(array $response, AccessToken $token)
  {
    return new CFE_Resource($response);
  }

  /**
   * @param array $urlParameters. key=>value to set URL path to call
   *
   */
  public function setUrlResourceOwnerDetails($urlParameters)
  {
    $url = $this->buildResourceURL($urlParameters);
    $this->urlResourceOwnerDetails = $url;
  }

  private function buildResourceURL($urlParameters)
  {
    $built_params = implode('/', array_map(
      function ($v, $k) { return sprintf("%s/%s", $k, $v); },
      $urlParameters,
      array_keys($urlParameters)
    ));

    return $this->urlResourceOwnerDetails . $built_params;
  }

}