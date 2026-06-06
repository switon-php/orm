<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Exception for unknown ID generation strategy.
 *
 * Thrown when an entity uses an unsupported ID generation strategy value.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\IdGeneratorInterface Typical consumer
 * @see \Switon\Orm\IdGenerator Typical raise site
 * @see \Switon\Orm\Attribute\Id Typical trigger
 * @see \Switon\Id\IdGeneratorInterface Valid strategies live in id package
 */
class InvalidIdStrategyException extends Exception
{
}
