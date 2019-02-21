<?php
/**
 * Created by PhpStorm.
 * User: dhuerta
 * Date: 10/01/18
 * Time: 21:22
 */

namespace AppBundle\ResourceOwners;


class CFE_Resource {
  /**
   * @var array
   */
  protected $response;


  /**
   * @param array $response
   */
  public function __construct(array $response)
  {
    $this->response = $response;
  }


  public function getResponse() {
    return $this->response['response'];
  }

  /**
   * Returns the raw resource owner response.
   *
   * @return array
   */
  public function toArray()
  {
    return $this->response;
  }

}