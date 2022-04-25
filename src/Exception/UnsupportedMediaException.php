<?php

namespace Xylemical\Controller\Rest\Exception;

/**
 * Triggered when serialization support is unavailable for the headers.
 */
class UnsupportedMediaException extends \Exception {

  /**
   * {@inheritdoc}
   */
  public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code ?: 415, $previous);
  }

}
