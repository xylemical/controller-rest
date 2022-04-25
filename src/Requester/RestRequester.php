<?php

namespace Xylemical\Controller\Rest\Requester;

use Psr\Http\Message\RequestInterface;
use Xylemical\Controller\ContextInterface;
use Xylemical\Controller\RequesterInterface;
use Xylemical\Controller\Rest\RestSerializerInterface;

/**
 * Provides a basic REST requester.
 */
class RestRequester implements RequesterInterface {

  /**
   * The serializer for the request.
   *
   * @var \Xylemical\Controller\Rest\RestSerializerInterface
   */
  protected RestSerializerInterface $serializer;

  /**
   * RestRequester constructor.
   *
   * @param \Xylemical\Controller\Rest\RestSerializerInterface $serializer
   *   The serializer.
   */
  public function __construct(RestSerializerInterface $serializer) {
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, ContextInterface $context): bool {
    return $this->serializer->applies($request->getHeaderLine('Content-Type'));
  }

  /**
   * {@inheritdoc}
   */
  public function getBody(RequestInterface $request, ContextInterface $context): mixed {
    return $this->serializer->getRequest($request);
  }

}
