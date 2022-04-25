<?php

namespace Xylemical\Controller\Rest\Exception;

/**
 * Triggered when 'If-Match' fails.
 */
class PreconditionFailedException extends \Exception {

  /**
   * {@inheritdoc}
   */
  public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code ?: 412, $previous);
  }

}
