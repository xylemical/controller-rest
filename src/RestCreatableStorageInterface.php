<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\ContextInterface;

/**
 * Allows the storage to create.
 */
interface RestCreatableStorageInterface extends RestStorageInterface {

  /**
   * Create a resource.
   *
   * @param mixed $resource
   *   The resource.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return mixed
   *   The created resource.
   */
  public function create(mixed $resource, ContextInterface $context): mixed;

}
