<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\ContextInterface;

/**
 * Allows storage to be writeable.
 */
interface RestWriteableStorageInterface extends RestStorageInterface {

  /**
   * Write resource to storage.
   *
   * @param mixed $resource
   *   The resource.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return mixed
   *   The updated resource.
   */
  public function write(mixed $resource, ContextInterface $context): mixed;

}
