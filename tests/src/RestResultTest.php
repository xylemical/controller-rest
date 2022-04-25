<?php

namespace Xylemical\Controller\Rest;

use PHPUnit\Framework\TestCase;

/**
 * Tests \Xylemical\Controller\Rest\RestResult.
 */
class RestResultTest extends TestCase {

  /**
   * Test the Rest Result.
   */
  public function testResult() {
    $result = new RestResult(200, '');

    $this->assertEquals([], $result->getHeaders());
    $this->assertEquals([], $result->getHeader('test'));
    $this->assertFalse($result->hasHeader('test'));

    $result->setHeader('test', 'value');
    $this->assertEquals(['value'], $result->getHeader('test'));

    $result->addHeader('test', 'demo');
    $this->assertEquals(['value', 'demo'], $result->getHeader('test'));

    $result->removeHeader('test', 'demo');
    $this->assertEquals(['value'], $result->getHeader('test'));
    $this->assertTrue($result->hasHeader('test'));

    $result->clearHeader('test');
    $this->assertEquals([], $result->getHeader('value'));
    $this->assertFalse($result->hasHeader('test'));
  }

}
