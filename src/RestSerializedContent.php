<?php

namespace Xylemical\Controller\Rest;

/**
 * Provides a response with the content type and the serialized contents.
 */
class RestSerializedContent {

  /**
   * The content type.
   *
   * @var string
   */
  protected string $contentType;

  /**
   * The contents.
   *
   * @var string
   */
  protected string $contents;

  /**
   * RestSerializedItem constructor.
   *
   * @param string $contents
   *   The contents.
   * @param string $contentType
   *   The content type.
   */
  public function __construct(string $contents, string $contentType = '') {
    $this->contentType = $contentType;
    $this->contents = $contents;
  }

  /**
   * Get the content type.
   *
   * @return string
   *   The content type.
   */
  public function getContentType(): string {
    return $this->contentType;
  }

  /**
   * Set the content type.
   *
   * @param string $contentType
   *   The content type.
   *
   * @return $this
   */
  public function setContentType(string $contentType): static {
    $this->contentType = $contentType;
    return $this;
  }

  /**
   * Get the serialized contents.
   *
   * @return string
   *   The contents.
   */
  public function getContents(): string {
    return $this->contents;
  }

}
