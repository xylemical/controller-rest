<?php

namespace Xylemical\Controller\Rest\Processor;

use Psr\Http\Message\RequestInterface;
use Xylemical\Controller\ContextInterface;
use Xylemical\Controller\Exception\AccessException;
use Xylemical\Controller\Exception\UnavailableException;
use Xylemical\Controller\ProcessorInterface;
use Xylemical\Controller\Rest\Exception\MethodNotAllowedException;
use Xylemical\Controller\Rest\Exception\PreconditionFailedException;
use Xylemical\Controller\Rest\RestCreatableStorageInterface;
use Xylemical\Controller\Rest\RestDeletableStorageInterface;
use Xylemical\Controller\Rest\RestMergeableInterface;
use Xylemical\Controller\Rest\RestNotificationInterface;
use Xylemical\Controller\Rest\RestReadableStorageInterface;
use Xylemical\Controller\Rest\RestResult;
use Xylemical\Controller\Rest\RestStorageEntityTagInterface;
use Xylemical\Controller\Rest\RestStorageInterface;
use Xylemical\Controller\Rest\RestWriteableStorageInterface;
use Xylemical\Controller\ResultInterface;

/**
 * Provides a rest processor.
 */
class RestProcessor implements ProcessorInterface {

  /**
   * The storage used for processing a rest request.
   *
   * @var \Xylemical\Controller\Rest\RestStorageInterface
   */
  protected RestStorageInterface $storage;

  /**
   * The REST event notification service.
   *
   * @var \Xylemical\Controller\Rest\RestNotificationInterface|null
   */
  protected ?RestNotificationInterface $notification;

  /**
   * RestProcessor constructor.
   *
   * @param \Xylemical\Controller\Rest\RestStorageInterface $storage
   *   The REST storage.
   * @param \Xylemical\Controller\Rest\RestNotificationInterface|null $notification
   *   The notification service.
   */
  public function __construct(RestStorageInterface $storage, ?RestNotificationInterface $notification = NULL) {
    $this->storage = $storage;
    $this->notification = $notification;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, mixed $contents, ContextInterface $context): bool {
    $method = $request->getMethod();
    return in_array(
      $method,
      ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function getResult(RequestInterface $request, mixed $contents, ContextInterface $context): ResultInterface {
    switch ($request->getMethod()) {
      default:
        return $this->doRead($contents, $context);
      case 'POST':
        return $this->doCreate($contents, $context);

      case 'PUT':
        return $this->doWrite($request, $contents, $context);

      case 'PATCH':
        return $this->doPartialWrite($request, $contents, $context);

      case 'DELETE':
        return $this->doDelete($request, $contents, $context);

      case 'HEAD':
        return $this->doExists($contents, $context);

      case 'OPTIONS':
        return $this->doOptions();
    }
  }

  /**
   * Perform the load of a resource.
   *
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return array
   *   The identifier and resource.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\MethodNotAllowedException
   */
  protected function doLoad(mixed $contents, ContextInterface $context): array {
    if (!$this->storage instanceof RestReadableStorageInterface) {
      throw new MethodNotAllowedException('Unsupported functionality.');
    }

    $identifier = $this->storage->id($contents, $context);
    if (!$identifier || !$this->storage->has($identifier, $context)) {
      throw new UnavailableException('REST resource does not exist.');
    }

    $result = $this->storage->read($identifier, $context);

    try {
      $this->storage->access('read', $result, $context);
    }
    catch (\Exception $e) {
      throw new AccessException($e->getMessage());
    }

    return [$identifier, $result];
  }

  /**
   * Perform a resource read operation.
   *
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return \Xylemical\Controller\ResultInterface
   *   The result.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\MethodNotAllowedException
   */
  protected function doRead(mixed $contents, ContextInterface $context): ResultInterface {
    [$identifier, $result] = $this->doLoad($contents, $context);

    $this->notification?->read($identifier, $result, $context);

    return $this->getRestResult($identifier, $result, $context);
  }

  /**
   * Perform a resource create operation.
   *
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return \Xylemical\Controller\ResultInterface
   *   The result.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\MethodNotAllowedException
   */
  protected function doCreate(mixed $contents, ContextInterface $context): ResultInterface {
    if (!$this->storage instanceof RestCreatableStorageInterface) {
      throw new MethodNotAllowedException('Unsupported functionality.');
    }

    $identifier = $this->storage->id($contents, $context);
    if ($identifier && $this->storage->has($identifier, $context)) {
      throw new UnavailableException('REST resource already exists.');
    }

    if (!$this->storage->validate($contents, $context)) {
      throw new UnavailableException('Invalid resource.');
    }

    try {
      $this->storage->access('create', $contents, $context);
    }
    catch (\Exception $e) {
      throw new AccessException($e->getMessage());
    }

    $result = $this->storage->create($contents, $context);
    $identifier = $this->storage->id($result, $context);

    $this->notification?->create($identifier, $result, $context);

    return $this->getRestResult($identifier, $result, $context);
  }

  /**
   * Save the contents to storage.
   *
   * @param string $identifier
   *   The contents.
   * @param mixed $contents
   *   The request.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return \Xylemical\Controller\Rest\RestResult
   *   The result.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\PreconditionFailedException
   */
  protected function doSave(string $identifier, mixed $contents, RequestInterface $request, ContextInterface $context): RestResult {
    if (!$this->storage->validate($contents, $context)) {
      throw new UnavailableException('Invalid resource');
    }

    try {
      $this->storage->access('write', $contents, $context);
    }
    catch (\Exception $e) {
      throw new AccessException($e->getMessage());
    }

    $this->doConfirm($request, $identifier, $context);

    $result = NULL;
    if ($this->storage instanceof RestWriteableStorageInterface) {
      $result = $this->storage->write($contents, $context);
      $this->notification?->update($identifier, $result, $context);
    }

    return $this->getRestResult($identifier, $result, $context);
  }

  /**
   * Perform a resource write operation.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return \Xylemical\Controller\ResultInterface
   *   The result.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\MethodNotAllowedException
   * @throws \Xylemical\Controller\Rest\Exception\PreconditionFailedException
   */
  protected function doWrite(RequestInterface $request, mixed $contents, ContextInterface $context): ResultInterface {
    if (!$this->storage instanceof RestWriteableStorageInterface) {
      throw new MethodNotAllowedException('Unsupported functionality.');
    }

    $identifier = $this->storage->id($contents, $context);
    if (!$identifier || !$this->storage->has($identifier, $context)) {
      throw new UnavailableException('REST resource does not exist.');
    }

    return $this->doSave($identifier, $contents, $request, $context);
  }

  /**
   * Perform a resource partial write operation.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return \Xylemical\Controller\ResultInterface
   *   The result.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\MethodNotAllowedException
   * @throws \Xylemical\Controller\Rest\Exception\PreconditionFailedException
   */
  protected function doPartialWrite(RequestInterface $request, mixed $contents, ContextInterface $context): ResultInterface {
    if (!$this->storage instanceof RestWriteableStorageInterface) {
      throw new MethodNotAllowedException('Unsupported functionality.');
    }
    if (!$this->storage instanceof RestReadableStorageInterface) {
      throw new MethodNotAllowedException('Unsupported functionality.');
    }

    $identifier = $this->storage->id($contents, $context);
    if (!$identifier || !$this->storage->has($identifier, $context)) {
      throw new UnavailableException('REST resource does not exist.');
    }

    $result = $this->storage->read($identifier, $context);
    $result = $this->merge($result, $contents);
    return $this->doSave($identifier, $result, $request, $context);
  }

  /**
   * Performs an array merge for REST resources.
   *
   * @param mixed $source
   *   The source content.
   * @param mixed $contents
   *   The updated content.
   *
   * @return mixed
   *   The result.
   */
  protected function merge(mixed $source, mixed $contents): mixed {
    if ($source instanceof RestMergeableInterface) {
      return $source->merge($contents);
    }

    if (is_array($source)) {
      if (!is_array($contents)) {
        $source[] = $contents;
        return $source;
      }

      if (array_keys($source) === range(0, count($source) - 1)) {
        return $contents;
      }

      foreach ($source as $key => $value) {
        if (isset($contents[$key])) {
          $source[$key] = $this->merge($value, $contents[$key]);
        }
      }

      return $source;
    }

    return $contents;
  }

  /**
   * Perform a resource delete operation.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return \Xylemical\Controller\ResultInterface
   *   The result.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\MethodNotAllowedException
   * @throws \Xylemical\Controller\Rest\Exception\PreconditionFailedException
   */
  protected function doDelete(RequestInterface $request, mixed $contents, ContextInterface $context): ResultInterface {
    if (!$this->storage instanceof RestDeletableStorageInterface) {
      throw new MethodNotAllowedException('Unsupported functionality.');
    }

    $identifier = $this->storage->id($contents, $context);
    if (!$identifier || !$this->storage->has($identifier, $context)) {
      throw new UnavailableException('REST resource does not exist.');
    }

    try {
      $this->storage->access('delete', $contents, $context);
    }
    catch (\Exception $e) {
      throw new AccessException($e->getMessage());
    }

    $this->doConfirm($request, $identifier, $context);

    if ($this->storage instanceof RestDeletableStorageInterface) {
      $this->storage->delete($identifier, $context);
    }

    $this->notification?->delete($identifier, $context);

    return $this->getRestResult();
  }

  /**
   * Perform a resource existence operation.
   *
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @return \Xylemical\Controller\ResultInterface
   *   The result.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Exception\UnavailableException
   * @throws \Xylemical\Controller\Rest\Exception\MethodNotAllowedException
   */
  protected function doExists(mixed $contents, ContextInterface $context): ResultInterface {
    [$identifier] = $this->doLoad($contents, $context);

    $this->notification?->exists($identifier, $context);

    return $this->getRestResult($identifier, NULL, $context);
  }

  /**
   * Perform a resource existence operation.
   *
   * @return \Xylemical\Controller\ResultInterface
   *   The result.
   */
  protected function doOptions(): ResultInterface {
    $options = ['OPTIONS'];

    if ($this->storage instanceof RestCreatableStorageInterface) {
      $options[] = 'POST';
    }

    if ($this->storage instanceof RestReadableStorageInterface) {
      $options[] = 'HEAD';
      $options[] = 'GET';

      if ($this->storage instanceof RestWriteableStorageInterface) {
        $options[] = 'PATCH';
      }
    }

    if ($this->storage instanceof RestWriteableStorageInterface) {
      $options[] = 'PUT';
    }

    if ($this->storage instanceof RestDeletableStorageInterface) {
      $options[] = 'DELETE';
    }

    return $this->getRestResult()
      ->addHeader('Allow', implode(', ', $options));
  }

  /**
   * Confirms entity tag matches resource.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param string $identifier
   *   The identifier.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   *
   * @throws \Xylemical\Controller\Exception\AccessException
   * @throws \Xylemical\Controller\Rest\Exception\PreconditionFailedException
   */
  protected function doConfirm(RequestInterface $request, string $identifier, ContextInterface $context): void {
    if (!$this->storage instanceof RestStorageEntityTagInterface) {
      return;
    }

    if (!$request->hasHeader('If-Match')) {
      throw new AccessException('"If-Match" header required.');
    }

    $tag = $this->storage->tag($identifier, $context);
    $headers = $request->getHeader('If-Match');
    if (!in_array($tag, $headers)) {
      throw new PreconditionFailedException();
    }
  }

  /**
   * Get the result for a resource.
   *
   * @param string|null $identifier
   *   The identifier.
   * @param mixed $resource
   *   The resource.
   * @param \Xylemical\Controller\ContextInterface|null $context
   *   The context, or NULL.
   *
   * @return \Xylemical\Controller\Rest\RestResult
   *   The result.
   */
  protected function getRestResult(?string $identifier = NULL, mixed $resource = NULL, ?ContextInterface $context = NULL): RestResult {
    $result = RestResult::complete($resource);
    if (!$this->storage instanceof RestStorageEntityTagInterface || !$identifier) {
      return $result;
    }
    return $result->addHeader('ETag', $this->storage->tag($identifier, $context));
  }

}
