<?php

namespace Mono\Exceptions;

/**
 * Thrown when a network-level failure occurs before the Mono API responds
 * (e.g. DNS failure, connection refused, timeout).
 * Distinct from MonoApiException, which implies the API did respond with an error.
 * Still a subclass of MonoApiException so callers can catch either.
 */
class MonoConnectionException extends MonoApiException {}
