<?php

namespace Xylemical\Controller\Rest\Exception;

/**
 * Provides a method not allowed exception.
 */
class MethodNotAllowedException extends \Exception {

  /**
   * {@inheritdoc}
   */
  public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code ?: 405, $previous);
  }

}
