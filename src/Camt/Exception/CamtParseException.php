<?php

declare(strict_types=1);

namespace Sepa\Camt\Exception;

use RuntimeException;

/**
 * Thrown when a CAMT.053 or CAMT.054 document cannot be parsed — wraps
 * underlying `genkgo/camt` `ReaderException` with a plugin-specific type
 * so consumers can catch a single class.
 */
class CamtParseException extends RuntimeException
{
}
