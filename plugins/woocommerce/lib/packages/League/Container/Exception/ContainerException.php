<?php declare(strict_types=1);

namespace Automattic\WooCommerce\Vendor\League\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
