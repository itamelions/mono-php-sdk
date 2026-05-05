<?php

namespace Mono\Exceptions;

/**
 * Thrown when the Mono API returns HTTP 429 Too Many Requests.
 * Use getRetryAfter() to determine how many seconds to wait before retrying.
 * Still a subclass of MonoApiException so callers can catch either.
 */
class MonoRateLimitException extends MonoApiException
{
    public function __construct(
        string $message,
        int $code,
        private readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Seconds to wait before the next attempt, as supplied by Mono's Retry-After header.
     * Returns null if the header was absent.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
