<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\ContextInterface;

/**
 * Provides a simple rest storage interface.
 */
interface RestStorageInterface {

  /**
   * Get the identifier from the contents.
   *
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return string
   *   The identifier.
   */
  public function id(mixed $contents, ContextInterface $context): string;

  /**
   * Check the storage has the identified item.
   *
   * @param string $identifier
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return bool
   *   The results.
   */
  public function has(string $identifier, ContextInterface $context): bool;

  /**
   * Checks access for the storage operation.
   *
   * @param string $operation
   *   The operation.
   * @param mixed $resource
   *   The resource.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   */
  public function access(string $operation, mixed $resource, ContextInterface $context): void;

  /**
   * Validate the contents matches to storage.
   *
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return bool
   *   The result.
   */
  public function validate(mixed $contents, ContextInterface $context): bool;

  /**
   * Add the identifier to the contents.
   *
   * @param string $identifier
   *   The identifier.
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return mixed
   *   The contents.
   */
  public function addIdentifier(string $identifier, mixed $contents, ContextInterface $context): mixed;

}
