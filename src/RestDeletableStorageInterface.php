<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\ContextInterface;

/**
 * Allows storage to be deletable.
 */
interface RestDeletableStorageInterface extends RestStorageInterface {

  /**
   * Delete a rest resource.
   *
   * @param string $identifier
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   */
  public function delete(string $identifier, ContextInterface $context): void;

}
