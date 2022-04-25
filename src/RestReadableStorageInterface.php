<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\ContextInterface;

/**
 * Allows the storage to be readable.
 */
interface RestReadableStorageInterface extends RestStorageInterface {

  /**
   * Read the resource by identifier.
   *
   * @param string $identifier
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return mixed
   *   The stored item.
   */
  public function read(string $identifier, ContextInterface $context): mixed;

}
