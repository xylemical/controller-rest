<?php

namespace Xylemical\Controller\Rest;

/**
 * Provides a means to have objects merge with the PATCH functionality.
 */
interface RestMergeableInterface {

  /**
   * Merge the contents into the object.
   *
   * @param mixed $contents
   *   The contents.
   *
   * @return $this
   */
  public function merge(mixed $contents): static;

}
