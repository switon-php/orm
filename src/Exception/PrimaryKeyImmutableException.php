<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Exception for immutable primary key modification.
 *
 * Thrown when an operation tries to change an existing entity primary key value.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\EntityManagerInterface Typical consumer
 * @see \Switon\Orm\EntityManager Typical raise site
 * @see \Switon\Orm\EntityMetadataInterface::getPrimaryKey() Typical source
 * @see \Switon\Orm\Attribute\Immutable Typical trigger
 */
class PrimaryKeyImmutableException extends Exception
{
}
