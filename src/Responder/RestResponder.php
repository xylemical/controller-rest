<?php

namespace Xylemical\Controller\Rest\Responder;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Xylemical\Controller\ContextInterface;
use Xylemical\Controller\ResponderInterface;
use Xylemical\Controller\Response;
use Xylemical\Controller\Rest\Exception\UnsupportedMediaException;
use Xylemical\Controller\Rest\RestSerializerInterface;
use Xylemical\Controller\ResultInterface;

/**
 * Provides a responder for REST requests.
 */
class RestResponder implements ResponderInterface {

  /**
   * The serializer.
   *
   * @var \Xylemical\Controller\Rest\RestSerializerInterface
   */
  protected RestSerializerInterface $serializable;

  /**
   * RestResponder constructor.
   *
   * @param \Xylemical\Controller\Rest\RestSerializerInterface $serializable
   *   The serializer.
   */
  public function __construct(RestSerializerInterface $serializable) {
    $this->serializable = $serializable;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, ResultInterface $result, ContextInterface $context): bool {
    foreach ($request->getHeader('Allow') as $value) {
      if ($this->serializable->applies($value)) {
        return TRUE;
      }
    }

    throw new UnsupportedMediaException();
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, ResultInterface $result, ContextInterface $context): ResponseInterface {
    $contents = NULL;
    foreach ($request->getHeader('Allow') as $value) {
      if ($this->serializable->applies($value)) {
        $contents = $this->serializable->getResponse($request, $result->getContents());
        break;
      }
    }
    return (new Response($result->getStatus(), $contents->getContents()))
      ->withHeader('Content-Type', $contents->getContentType());
  }

}
