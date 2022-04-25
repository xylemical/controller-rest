<?php

namespace Xylemical\Controller\Rest;

use Xylemical\Controller\Result;

/**
 * Provide extra details.
 */
class RestResult extends Result {

  /**
   * Additional headers for the result.
   *
   * @var string[][]
   */
  protected array $headers = [];

  /**
   * Get the additional headers.
   *
   * @return string[][]
   *   The headers.
   */
  public function getHeaders(): array {
    return $this->headers;
  }

  /**
   * Get a header.
   *
   * @param string $header
   *   The header.
   *
   * @return string[]
   *   The values.
   */
  public function getHeader(string $header): array {
    return $this->headers[$header] ?? [];
  }

  /**
   * Check the header exists.
   *
   * @param string $header
   *   The header.
   *
   * @return bool
   *   The result.
   */
  public function hasHeader(string $header): bool {
    return isset($this->headers[$header]);
  }

  /**
   * Set the header.
   *
   * @param string $header
   *   The header.
   * @param string $value
   *   The value.
   *
   * @return $this
   */
  public function setHeader(string $header, string $value): static {
    $this->headers[$header] = [$value];
    return $this;
  }

  /**
   * Add a header.
   *
   * @param string $header
   *   The header.
   * @param string $value
   *   The value.
   *
   * @return $this
   */
  public function addHeader(string $header, string $value): static {
    $this->headers[$header][] = $value;
    return $this;
  }

  /**
   * Remove a header.
   *
   * @param string $header
   *   The header.
   * @param string $value
   *   The value.
   *
   * @return $this
   */
  public function removeHeader(string $header, string $value): static {
    if (isset($this->headers[$header])) {
      $this->headers[$header] = array_filter(
        $this->headers[$header],
        function ($item) use ($value) {
          return $item !== $value;
        }
      );
    }
    return $this;
  }

  /**
   * Completely remove a header.
   *
   * @param string $header
   *   The header.
   *
   * @return $this
   */
  public function clearHeader(string $header): static {
    unset($this->headers[$header]);
    return $this;
  }

}
