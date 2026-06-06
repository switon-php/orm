<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Exception for missing primary key values.
 *
 * Thrown when an entity operation requires a primary key value but none is provided.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\RepositoryInterface Typical consumer
 * @see \Switon\Orm\EntityManagerInterface Typical consumer
 * @see \Switon\Orm\EntityManager
 * @see \Switon\Orm\EntityMetadataInterface::getPrimaryKey() Typical source
 */
class PrimaryKeyMissingException extends Exception
{
}
