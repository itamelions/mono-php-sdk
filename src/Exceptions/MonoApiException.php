<?php

namespace Mono\Exceptions;

use RuntimeException;

/**
 * Thrown when the Mono API returns an error response (4xx / 5xx)
 * or when a network-level failure occurs.
 */
class MonoApiException extends RuntimeException {}
