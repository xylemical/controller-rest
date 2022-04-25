<?php

namespace Xylemical\Controller\Rest\Serializer;

use Psr\Http\Message\RequestInterface;
use Xylemical\Controller\Exception\InvalidBodyException;
use Xylemical\Controller\Rest\RestSerializedContent;
use Xylemical\Controller\Rest\RestSerializerInterface;

/**
 * Provides JSON serialization.
 */
class RestJsonSerializer implements RestSerializerInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(string $contentType): bool {
    return preg_match('#^application/(.*\+)?json#', $contentType);
  }

  /**
   * {@inheritdoc}
   */
  public function getRequest(RequestInterface $request): mixed {
    $result = @json_decode((string) $request->getBody(), TRUE);
    if (json_last_error()) {
      throw new InvalidBodyException(json_last_error_msg());
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, mixed $contents): RestSerializedContent {
    return new RestSerializedContent(
      !is_null($contents) ? json_encode($contents) : '',
      'application/json'
    );
  }

}
