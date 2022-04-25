<?php

namespace Xylemical\Controller\Rest;

use Psr\Http\Message\RequestInterface;

/**
 * Provides a means for Rest serialization of content.
 */
interface RestSerializerInterface {

  /**
   * Check the content type can be serialized.
   *
   * @param string $contentType
   *   The content type.
   *
   * @return bool
   *   The result.
   */
  public function applies(string $contentType): bool;

  /**
   * Get the contents from the request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return mixed
   *   The body contents.
   */
  public function getRequest(RequestInterface $request): mixed;

  /**
   * Get the contents for the response.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param mixed $contents
   *   The contents.
   *
   * @return \Xylemical\Controller\Rest\RestSerializedContent
   *   The serialized item.
   */
  public function getResponse(RequestInterface $request, mixed $contents): RestSerializedContent;

}
