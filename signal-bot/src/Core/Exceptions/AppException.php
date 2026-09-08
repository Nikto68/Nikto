<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Base type for every domain exception in the app. Catch this at the
 * worker/queue boundary to log-and-continue instead of catch(Throwable),
 * which would also swallow genuine programming errors.
 */
class AppException extends RuntimeException
{
}
