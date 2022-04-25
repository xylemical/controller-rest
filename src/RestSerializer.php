<?php

namespace Xylemical\Controller\Rest;

use Psr\Http\Message\RequestInterface;
use Xylemical\Controller\Rest\Exception\UnsupportedMediaException;

/**
 * Provides management of multiple RestSerializableInterfaces.
 */
class RestSerializer implements RestSerializerInterface {

  /**
   * The serializable.
   *
   * @var \Xylemical\Controller\Rest\RestSerializerInterface[]
   */
  protected array $serializers = [];

  /**
   * The default content type.
   *
   * @var string
   */
  protected string $defaultContentType = '';

  /**
   * RestSerializer constructor.
   *
   * @param \Xylemical\Controller\Rest\RestSerializerInterface[] $serializers
   *   The serializers.
   * @param string $defaultContentType
   *   The default content type.
   */
  public function __construct(array $serializers = [], string $defaultContentType = '') {
    $this->serializers = $serializers;
    $this->defaultContentType = $defaultContentType;
  }

  /**
   * Get the default content type.
   *
   * @return string
   *   The content type.
   */
  public function getDefaultContentType(): string {
    return $this->defaultContentType;
  }

  /**
   * Set the default content type.
   *
   * @param string $contentType
   *   The content type.
   *
   * @return $this
   */
  public function setDefaultContentType(string $contentType): static {
    $this->defaultContentType = $contentType;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(string $contentType): bool {
    $parts = explode(';', $contentType);
    $contentType = $parts[0];

    foreach ($this->serializers as $serializer) {
      if ($serializer->applies($contentType)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequest(RequestInterface $request): mixed {
    $parts = explode(';', $request->getHeaderLine('Content-Type'));
    $contentType = $parts[0] ?: $this->defaultContentType;

    foreach ($this->serializers as $serializer) {
      if ($serializer->applies($contentType)) {
        return $serializer->getRequest($request);
      }
    }

    throw new UnsupportedMediaException();
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, mixed $contents): RestSerializedContent {
    foreach ($this->getAccept($request) as $contentType) {
      foreach ($this->serializers as $serializer) {
        if ($serializer->applies($contentType)) {
          return $serializer->getResponse($request, $contents)
            ->setContentType($contentType);
        }
      }
    }

    throw new UnsupportedMediaException();
  }

  /**
   * Get the 'Accept' content types in preferred order.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return string[]
   *   The content types.
   */
  protected function getAccept(RequestInterface $request): array {
    $accept = [];
    foreach ($request->getHeader('Accept') as $value) {
      foreach (explode(',', $value) as $item) {
        $parts = explode(';q=', trim($item));
        $accept[$parts[0]] = filter_var($parts[1] ?? '1', FILTER_VALIDATE_FLOAT);
      }
    }

    arsort($accept);
    if ($accept) {
      $default = $this->defaultContentType;
      return array_map(function ($item) use ($default) {
        return $item === '*/*' ? $default : $item;
      }, array_keys($accept));
    }

    return $this->defaultContentType ? [$this->defaultContentType] : [];
  }

}
