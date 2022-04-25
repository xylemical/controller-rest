<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\ContextInterface;

/**
 * Allows the storage to generate a unique entity tag for content.
 */
interface RestStorageEntityTagInterface extends RestStorageInterface {

  /**
   * Generate the entity tag for the entity.
   *
   * @param string $identifier
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return string
   *   The tag.
   */
  public function tag(string $identifier, ContextInterface $context): string;

}
