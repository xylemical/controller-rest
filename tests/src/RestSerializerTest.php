<?php

namespace Xylemical\Controller\Rest;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\RequestInterface;
use Xylemical\Controller\Rest\Exception\UnsupportedMediaException;

/**
 * Tests \Xylemical\Controller\Rest\RestSerializer
 */
class RestSerializerTest extends TestCase {

  use ProphecyTrait;

  /**
   * Get a mock serializer.
   *
   * @param string $contentType
   *   The content type.
   * @param string $result
   *   The result.
   * @param string $response
   *   The response.
   *
   * @return \Xylemical\Controller\Rest\RestSerializerInterface
   *   The mocked serializer.
   */
  protected function getMockSerializer(string $contentType, string $result) {
    $serializer = $this->prophesize(RestSerializerInterface::class);
    $serializer->applies($contentType)->willReturn(TRUE);
    $serializer->applies(Argument::any())->willReturn(FALSE);
    $serializer->getRequest(Argument::any())->willReturn($result);
    $serializer->getResponse(Argument::any(), Argument::any())
      ->will(function ($args) use ($result) {
        return new RestSerializedContent($args[1] . '+' . $result);
      });
    return $serializer->reveal();
  }

  /**
   * Test the serializer.
   */
  public function testSerializer() {
    $serializers = [
      $this->getMockSerializer('application/json', 'json'),
      $this->getMockSerializer('application/xml', 'xml'),
    ];
    $serializer = new RestSerializer($serializers, 'application/xml');
    $this->assertTrue($serializer->applies('application/json'));
    $this->assertTrue($serializer->applies('application/xml'));
    $this->assertFalse($serializer->applies('text/plain'));
    $this->assertEquals('application/xml', $serializer->getDefaultContentType());

    $serializer->setDefaultContentType('application/json');
    $this->assertEquals('application/json', $serializer->getDefaultContentType());

    // Properly setup request and response.
    $request = $this->prophesize(RequestInterface::class);
    $request->getHeaderLine('Content-Type')->willReturn('application/json');
    $request->getHeader('Accept')
      ->willReturn(['application/json;q=0.4, application/xml;q=0.9']);
    $request = $request->reveal();

    $this->assertEquals('json', $serializer->getRequest($request));
    $response = $serializer->getResponse($request, 'test');
    $this->assertEquals('application/xml', $response->getContentType());
    $this->assertEquals('test+xml', $response->getContents());

    // Request missing content-type, use default content type.
    $request = $this->prophesize(RequestInterface::class);
    $request->getHeaderLine('Content-Type')->willReturn('');
    $request->getHeader('Accept')
      ->willReturn(['application/json;q=0.4, application/xml;q=0.9']);
    $request = $request->reveal();

    $this->assertEquals('json', $serializer->getRequest($request));
    $response = $serializer->getResponse($request, 'test');
    $this->assertEquals('application/xml', $response->getContentType());
    $this->assertEquals('test+xml', $response->getContents());

    // Request missing accept, use default content type.
    $request = $this->prophesize(RequestInterface::class);
    $request->getHeaderLine('Content-Type')->willReturn('');
    $request->getHeader('Accept')->willReturn([]);
    $request = $request->reveal();

    $this->assertEquals('json', $serializer->getRequest($request));
    $response = $serializer->getResponse($request, 'test');
    $this->assertEquals('application/json', $response->getContentType());
    $this->assertEquals('test+json', $response->getContents());

    // Request using unsupported accept, unsupported content-type.
    $request = $this->prophesize(RequestInterface::class);
    $request->getHeaderLine('Content-Type')->willReturn('text/plain');
    $request->getHeader('Accept')->willReturn(['text/plain']);
    $request = $request->reveal();

    $thrown = FALSE;
    try {
      $serializer->getRequest($request);
    } catch (UnsupportedMediaException $e) {
      $thrown = TRUE;
    }
    $this->assertTrue($thrown);

    $thrown = FALSE;
    try {
      $serializer->getResponse($request, 'test');
    } catch (UnsupportedMediaException $e) {
      $thrown = TRUE;
    }
    $this->assertTrue($thrown);
  }

}
