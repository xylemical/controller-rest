<?php

namespace Xylemical\Controller\Rest\Processor;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\RequestInterface;
use Xylemical\Controller\ContextInterface;
use Xylemical\Controller\Exception\AccessException;
use Xylemical\Controller\Exception\UnavailableException;
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

/**
 * Tests \Xylemical\Controller\Rest\Processor\RestProcessor.
 */
class RestProcessorTest extends TestCase {

  use ProphecyTrait;

  /**
   * Assert an exception is called for the processor.
   *
   * @param \Xylemical\Controller\Rest\Processor\RestProcessor $processor
   *   The processor.
   * @param string $exception
   *   The expected exception.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param mixed $contents
   *   The contents.
   * @param \Xylemical\Controller\ContextInterface $context
   *   The context.
   */
  protected function assertException(RestProcessor $processor, string $exception, RequestInterface $request, mixed $contents, ContextInterface $context) {
    $called = FALSE;
    try {
      $processor->getResult($request, $contents, $context);
    }
    catch (\Exception $e) {
      if ($e instanceof $exception) {
        $called = TRUE;
      }
    }
    $this->assertTrue($called);
  }

  /**
   * Get a mocked request.
   *
   * @param string $method
   *   The HTTP method.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The request.
   */
  protected function getMockRequest(string $method, ?string $etag = NULL): RequestInterface {
    $request = $this->prophesize(RequestInterface::class);
    $request->getMethod()->willReturn($method);
    if (!is_null($etag)) {
      $request->hasHeader('If-Match')->willReturn((bool) $etag);
      if ($etag) {
        $request->getHeader('If-Match')->willReturn([$etag]);
      }
    }
    return $request->reveal();
  }

  /**
   * Get a mocked context.
   *
   * @return \Xylemical\Controller\ContextInterface
   *   The context.
   */
  protected function getMockContext(): ContextInterface {
    $context = $this->prophesize(ContextInterface::class);
    return $context->reveal();
  }

  /**
   * Test POST method.
   */
  public function testCreate() {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('POST');
    $contents = ['body'];
    $result = ['crazed'];

    // Test with no create support.
    $storage = $this->prophesize(RestStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertTrue($processor->applies($request, $contents, $context));
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with create support, but existing data.
    $storage = $this->prophesize(RestCreatableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with create support, but invalid data.
    $storage = $this->prophesize(RestCreatableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $storage->has('', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with create support, valid data, but no access.
    $storage = $this->prophesize(RestCreatableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $storage->has('', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->access('create', $contents, $context)
      ->willThrow(new \Exception());
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with create support, valid data, no notification.
    $storage = $this->prophesize(RestCreatableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $storage->id($result, $context)->willReturn('id');
    $storage->has('', $context)->willReturn(TRUE);
    $storage->access('create', $contents, $context);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->create($contents, $context)->willReturn($result);
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());

    // Test with create support, valid data, notification.
    $notification = $this->prophesize(RestNotificationInterface::class);
    $notification->create('id', $result, $context)->shouldBeCalledOnce();
    $processor = new RestProcessor($storage, $notification->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());

    // Test with create support, and entity tag support.
    $storage = $this->prophesize(TestCreateStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $storage->id($result, $context)->willReturn('id');
    $storage->has('', $context)->willReturn(TRUE);
    $storage->access('create', $contents, $context);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->create($contents, $context)->willReturn($result);
    $storage->tag('id', $context)->willReturn('etag');
    $processor = new RestProcessor($storage->reveal());
    /** @var RestResult $response */
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());
    $this->assertTrue(in_array('etag', $response->getHeader('ETag')));
  }

  /**
   * Test GET method.
   */
  public function testRead() {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('GET');
    $contents = ['body'];

    // Test with no read support.
    $storage = $this->prophesize(RestStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertTrue($processor->applies($request, $contents, $context));
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, but no identifier.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, identifier but no data.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, identifier, data, no access.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($contents);
    $storage->access('read', $contents, $context)->willThrow(new \Exception());
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, identifier, data, access, no notification.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($contents);
    $storage->access('read', $contents, $context);
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($contents, $response->getContents());

    // Test with notification.
    $notification = $this->prophesize(RestNotificationInterface::class);
    $notification->read('id', $contents, $context)->shouldBeCalledOnce();
    $processor = new RestProcessor($storage, $notification->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($contents, $response->getContents());

    // Test with entity tag.
    $storage = $this->prophesize(TestReadStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($contents);
    $storage->access('read', $contents, $context);
    $storage->tag('id', $context)->willReturn('etag');
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);

    /** @var RestResult $response */
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($contents, $response->getContents());
    $this->assertTrue(in_array('etag', $response->getHeader('ETag')));
  }

  /**
   * Test HEAD method.
   */
  public function testHead() {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('HEAD');
    $contents = ['body'];

    // Test with no read support.
    $storage = $this->prophesize(RestStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertTrue($processor->applies($request, $contents, $context));
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, but no identifier.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, identifier but no data.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, identifier, data, no access.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($contents);
    $storage->access('read', $contents, $context)->willThrow(new \Exception());
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with read support, identifier, data, access, no notification.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($contents);
    $storage->access('read', $contents, $context);
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals(200, $response->getStatus());
    $this->assertEquals(NULL, $response->getContents());
    $this->assertEquals([], $response->getHeaders());

    // Test with notification.
    $notification = $this->prophesize(RestNotificationInterface::class);
    $notification->exists('id', $context)->shouldBeCalledOnce();
    $processor = new RestProcessor($storage, $notification->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals(200, $response->getStatus());
    $this->assertEquals(NULL, $response->getContents());
    $this->assertEquals([], $response->getHeaders());

    // Test with entity tag.
    $storage = $this->prophesize(TestReadStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($contents);
    $storage->access('read', $contents, $context);
    $storage->tag('id', $context)->willReturn('etag');
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);

    /** @var RestResult $response */
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals(200, $response->getStatus());
    $this->assertEquals(NULL, $response->getContents());
    $this->assertTrue(in_array('etag', $response->getHeader('ETag')));
  }

  /**
   * Test PUT method.
   */
  public function testWrite() {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('PUT');
    $contents = ['body'];
    $result = ['crazed'];

    // Test with no write support.
    $storage = $this->prophesize(RestStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertTrue($processor->applies($request, $contents, $context));
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with no identifier.
    $storage = $this->prophesize(RestWriteableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with no existing content.
    $storage = $this->prophesize(RestWriteableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with existing content, no valid.
    $storage = $this->prophesize(RestWriteableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with valid existing content, no access.
    $storage = $this->prophesize(RestWriteableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context)->willThrow(new \Exception());
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with entity tag support, but no 'If-Match' tag.
    $request = $this->getMockRequest('PUT', '');
    $storage = $this->prophesize(TestWriteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with entity tag support, but 'If-Match' invalid.
    $request = $this->getMockRequest('PUT', 'invalid');
    $storage = $this->prophesize(TestWriteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $storage->tag('id', $context)->willReturn('etag');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      PreconditionFailedException::class,
      $request,
      $contents,
      $context
    );

    // Test with entity tag support, but 'If-Match' valid.
    $request = $this->getMockRequest('PUT', 'etag');
    $storage = $this->prophesize(TestWriteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $count = 0;
    $storage->tag('id', $context)->will(function ($id, $context) use (&$count) {
      return $count++ > 0 ? 'new' : 'etag';
    });
    $storage->write($contents, $context)->willReturn($result);
    $processor = new RestProcessor($storage->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());
    $this->assertTrue(in_array('new', $response->getHeader('ETag')));

    // Test with write, no notification.
    $request = $this->getMockRequest('PUT');
    $storage = $this->prophesize(RestWriteableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $storage->write($contents, $context)->willReturn($result);
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());

    // Test with write, notification.
    $notification = $this->prophesize(RestNotificationInterface::class);
    $notification->update('id', $result, $context)->shouldBeCalledOnce();
    $processor = new RestProcessor($storage, $notification->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());
  }

  /**
   * Test PATCH method.
   */
  public function testPartialWrite() {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('PATCH');
    $existing = ['crazed'];
    $contents = ['body'];
    $result = ['body', 'crazed'];

    // Test with no write/read support.
    $storage = $this->prophesize(RestStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertTrue($processor->applies($request, $contents, $context));
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with no write support.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with no read support.
    $storage = $this->prophesize(RestWriteableStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with no identifier.
    $storage = $this->prophesize(TestPartialWriteStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with no existing content.
    $storage = $this->prophesize(TestPartialWriteStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with existing content, no valid.
    $storage = $this->prophesize(TestPartialWriteStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($existing);
    $storage->validate($contents, $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with valid existing content, no access.
    $storage = $this->prophesize(TestPartialWriteStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($existing);
    $storage->validate($contents, $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context)->willThrow(new \Exception());
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with entity tag support, but no 'If-Match' tag.
    $request = $this->getMockRequest('PATCH', '');
    $storage = $this->prophesize(TestPartialWriteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($existing);
    $storage->validate(Argument::any(), $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with entity tag support, but 'If-Match' invalid.
    $request = $this->getMockRequest('PATCH', 'invalid');
    $storage = $this->prophesize(TestPartialWriteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($existing);
    $storage->validate(Argument::any(), $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $storage->tag('id', $context)->willReturn('etag');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      PreconditionFailedException::class,
      $request,
      $contents,
      $context
    );

    // Test with entity tag support, but 'If-Match' valid.
    $request = $this->getMockRequest('PATCH', 'etag');
    $storage = $this->prophesize(TestPartialWriteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($existing);
    $storage->validate(Argument::any(), $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $count = 0;
    $storage->tag('id', $context)->will(function ($id, $context) use (&$count) {
      return $count++ > 0 ? 'new' : 'etag';
    });
    $storage->write($contents, $context)->willReturn($result);
    $processor = new RestProcessor($storage->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());
    $this->assertTrue(in_array('new', $response->getHeader('ETag')));

    // Test with write, no notification.
    $request = $this->getMockRequest('PATCH');
    $storage = $this->prophesize(TestPartialWriteStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($existing);
    $storage->validate(Argument::any(), $context)->willReturn(TRUE);
    $storage->access('write', $contents, $context);
    $storage->write($contents, $context)->willReturn($result);
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());

    // Test with write, notification.
    $notification = $this->prophesize(RestNotificationInterface::class);
    $notification->update('id', $result, $context)->shouldBeCalledOnce();
    $processor = new RestProcessor($storage, $notification->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());
  }

  /**
   * Test DELETE method.
   */
  public function testDelete() {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('DELETE');
    $contents = ['body'];

    // Test with no delete support.
    $storage = $this->prophesize(RestStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertTrue($processor->applies($request, $contents, $context));
    $this->assertException(
      $processor,
      MethodNotAllowedException::class,
      $request,
      $contents,
      $context
    );

    // Test with no identifier.
    $storage = $this->prophesize(RestDeletableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with no data.
    $storage = $this->prophesize(RestDeletableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(FALSE);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      UnavailableException::class,
      $request,
      $contents,
      $context
    );

    // Test with data, no access.
    $storage = $this->prophesize(RestDeletableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->access('delete', $contents, $context)
      ->willThrow(new \Exception());
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with data, access, entity tag with no 'If-Match'.
    $request = $this->getMockRequest('DELETE', '');
    $storage = $this->prophesize(TestDeleteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->access('delete', $contents, $context);
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      AccessException::class,
      $request,
      $contents,
      $context
    );

    // Test with data, access, entity tag with invalid 'If-Match'.
    $request = $this->getMockRequest('DELETE', 'invalid');
    $storage = $this->prophesize(TestDeleteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->access('delete', $contents, $context);
    $storage->tag('id', $context)->willReturn('etag');
    $processor = new RestProcessor($storage->reveal());
    $this->assertException(
      $processor,
      PreconditionFailedException::class,
      $request,
      $contents,
      $context
    );

    // Test with data, access, entity tag with valid 'If-Match'
    $request = $this->getMockRequest('DELETE', 'etag');
    $storage = $this->prophesize(TestDeleteStorageEntityInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->access('delete', $contents, $context);
    $count = 0;
    $storage->tag('id', $context)->will(function ($id) use (&$count) {
      return $count++ > 0 ? 'new' : 'etag';
    });
    $storage->delete('id', $context);
    $processor = new RestProcessor($storage->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals(200, $response->getStatus());
    $this->assertEquals(NULL, $response->getContents());
    $this->assertEquals([], $response->getHeaders());

    // Test with no notification
    $request = $this->getMockRequest('DELETE');
    $storage = $this->prophesize(RestDeletableStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->access('delete', $contents, $context);
    $storage->delete('id', $context);
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals(200, $response->getStatus());
    $this->assertEquals(NULL, $response->getContents());
    $this->assertEquals([], $response->getHeaders());

    // Test with notification.
    $notification = $this->prophesize(RestNotificationInterface::class);
    $notification->delete('id', $context)->shouldBeCalledOnce();
    $processor = new RestProcessor($storage, $notification->reveal());
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals(200, $response->getStatus());
    $this->assertEquals(NULL, $response->getContents());
    $this->assertEquals([], $response->getHeaders());
  }

  /**
   * Test OPTIONS method.
   */
  public function testOptions() {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('OPTIONS');

    // Test no support for anything.
    $storage = $this->prophesize(RestStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $this->assertTrue($processor->applies($request, NULL, $context));
    $result = $processor->getResult($request, NULL, $context);
    $this->assertEquals(['OPTIONS'], $result->getHeader('Allow'));

    // Test just read capability.
    $storage = $this->prophesize(RestReadableStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $result = $processor->getResult($request, NULL, $context);
    $this->assertEquals(['OPTIONS, HEAD, GET'], $result->getHeader('Allow'));

    // Test just write capability.
    $storage = $this->prophesize(RestWriteableStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $result = $processor->getResult($request, NULL, $context);
    $this->assertEquals(['OPTIONS, PUT'], $result->getHeader('Allow'));

    // Test both read and write capability.
    $storage = $this->prophesize(TestPartialWriteStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $result = $processor->getResult($request, NULL, $context);
    $this->assertEquals(['OPTIONS, HEAD, GET, PATCH, PUT'], $result->getHeader('Allow'));

    // Test create capability.
    $storage = $this->prophesize(RestCreatableStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $result = $processor->getResult($request, NULL, $context);
    $this->assertEquals(['OPTIONS, POST'], $result->getHeader('Allow'));

    // Test delete capability.
    $storage = $this->prophesize(RestDeletableStorageInterface::class);
    $processor = new RestProcessor($storage->reveal());
    $result = $processor->getResult($request, NULL, $context);
    $this->assertEquals(['OPTIONS, DELETE'], $result->getHeader('Allow'));
  }

  /**
   * Provides partial write merge data.
   */
  public function providerTestPartialWriteMerge() {
    return [
      [
        'existing',
        'content',
        'content',
      ],
      [
        ['existing'],
        'content',
        ['existing', 'content'],
      ],
      [
        ['existing'],
        ['content'],
        ['content'],
      ],
      [
        ['existing' => TRUE, 'content' => FALSE],
        ['content' => TRUE],
        ['existing' => TRUE, 'content' => TRUE],
      ],
      [
        new TestRestMerge(['dummy' => 1]),
        ['content' => 1],
        new TestRestMerge(['dummy' => 1, 'content' => 1]),
      ],

    ];
  }

  /**
   * Test PATCH merging.
   *
   * @dataProvider providerTestPartialWriteMerge
   */
  public function testPartialWriteMerge($existing, $contents, $result) {
    $context = $this->getMockContext();
    $request = $this->getMockRequest('PATCH');
    $storage = $this->prophesize(TestPartialWriteStorageInterface::class);
    $storage->id($contents, $context)->willReturn('id');
    $storage->has('id', $context)->willReturn(TRUE);
    $storage->read('id', $context)->willReturn($existing);
    $storage->validate(Argument::any(), $context)->willReturn(TRUE);
    $storage->access('write', Argument::any(), $context);
    $storage->write(Argument::any(), $context)->will(function ($args) {
      return $args[0];
    });
    $storage = $storage->reveal();
    $processor = new RestProcessor($storage);
    $response = $processor->getResult($request, $contents, $context);
    $this->assertEquals($result, $response->getContents());
  }


}

interface TestCreateStorageEntityInterface extends RestCreatableStorageInterface, RestStorageEntityTagInterface {

}

interface TestReadStorageEntityInterface extends RestReadableStorageInterface, RestStorageEntityTagInterface {

}

interface TestWriteStorageEntityInterface extends RestWriteableStorageInterface, RestStorageEntityTagInterface {

}

interface TestPartialWriteStorageInterface extends RestWriteableStorageInterface, RestReadableStorageInterface {

}

interface TestPartialWriteStorageEntityInterface extends RestReadableStorageInterface, RestWriteableStorageInterface, RestStorageEntityTagInterface {

}

interface TestDeleteStorageEntityInterface extends RestDeletableStorageInterface, RestStorageEntityTagInterface {

}

class TestRestMerge implements RestMergeableInterface {

  protected array $contents;

  public function __construct(array $contents = []) {
    $this->contents = $contents;
  }

  public function merge(mixed $contents): static {
    $this->contents = array_merge_recursive($this->contents, $contents);
    return $this;
  }

}