<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Exception for missing repository classes.
 *
 * Thrown when ORM cannot resolve a repository class for an entity type.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\EntityMetadataInterface::getRepository() Typical source
 * @see \Switon\Orm\EntityMetadata Typical source
 * @see \Switon\Orm\Attribute\Repository Typical fix
 */
class RepositoryNotFoundException extends Exception
{
}
