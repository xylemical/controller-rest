<?php

namespace Xylemical\Controller\Rest\Serializer;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\RequestInterface;
use Xylemical\Controller\Exception\InvalidBodyException;
use Xylemical\Controller\Stream;

/**
 * Tests \Xylemical\Controller\Rest\RestJsonSerializer
 */
class RestJsonSerializerTest extends TestCase {

  use ProphecyTrait;

  /**
   * Test the serializer.
   */
  public function testJsonSerializer() {
    $serializer = new RestJsonSerializer();
    $this->assertTrue($serializer->applies('application/json'));
    $this->assertTrue($serializer->applies('application/shop+json'));
    $this->assertFalse($serializer->applies('application/xml'));

    $request = $this->prophesize(RequestInterface::class);
    $request->getBody()->willReturn(new Stream('{"test": 1}'));
    $this->assertEquals(['test' => 1], $serializer->getRequest($request->reveal()));

    $thrown = FALSE;
    try {
      $request = $this->prophesize(RequestInterface::class);
      $request->getBody()->willReturn(new Stream('{'));
      $request = $request->reveal();
      $serializer->getRequest($request);
    } catch (InvalidBodyException $e) {
      $thrown = TRUE;
    }
    $this->assertTrue($thrown);

    $response = $serializer->getResponse($request, NULL);
    $this->assertEquals('', $response->getContents());

    $response = $serializer->getResponse($request, ['test' => 1]);
    $this->assertEquals('application/json', $response->getContentType());
    $this->assertEquals('{"test":1}', $response->getContents());
  }

}
