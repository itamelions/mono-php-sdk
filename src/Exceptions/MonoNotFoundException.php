<?php

namespace Mono\Exceptions;

/**
 * Thrown when the Mono API returns a 404 Not Found response.
 * This is a subclass of MonoApiException so callers can catch either.
 */
class MonoNotFoundException extends MonoApiException {}
