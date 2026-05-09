<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Exception for missing primary key definitions.
 *
 * Thrown when ORM metadata resolution cannot find a primary key for an entity class.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\EntityMetadataInterface Typical source
 * @see \Switon\Orm\EntityMetadata Typical source
 * @see \Switon\Orm\Attribute\Id Typical fix
 */
class PrimaryKeyNotFoundException extends Exception
{
}
