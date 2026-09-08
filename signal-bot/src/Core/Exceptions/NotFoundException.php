<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
