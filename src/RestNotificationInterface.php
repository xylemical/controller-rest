<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\ContextInterface;

/**
 * Provides rest notification behaviour.
 */
interface RestNotificationInterface {

  /**
   * A create event occurred on the resource.
   *
   * @param string $identifier
   *   The identifier.
   * @param mixed $resource
   *   The resource.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   */
  public function create(string $identifier, mixed $resource, ContextInterface $context): void;

  /**
   * A read event occurred on the resource.
   *
   * @param string $identifier
   *   The identifier.
   * @param mixed $resource
   *   The resource.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   */
  public function read(string $identifier, mixed $resource, ContextInterface $context): void;

  /**
   * An update event occurred on the resource.
   *
   * @param string $identifier
   *   The identifier.
   * @param mixed $resource
   *   The resource.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   */
  public function update(string $identifier, mixed $resource, ContextInterface $context): void;

  /**
   * A delete event occurred on the resource.
   *
   * @param string $identifier
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   */
  public function delete(string $identifier, ContextInterface $context): void;

  /**
   * An existence event occurred on the resource.
   *
   * @param string $identifier
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   */
  public function exists(string $identifier, ContextInterface $context): void;

}
