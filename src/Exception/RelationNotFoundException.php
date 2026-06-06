<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Raised when requested relation metadata is missing on an entity.
 *
 * Guidance: Validate dynamic relation names before use when they come from user or runtime input.
 *
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Orm\EntityMetadataInterface::getRelations()
 */
class RelationNotFoundException extends Exception
{
}
