<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Raised when relation loading needs a field that is absent in the selected data.
 *
 * Guidance: Keep relation key fields in custom select lists whenever relation mapping depends on them.
 *
 * @see \Switon\Orm\Relation\HasManyRelation
 * @see \Switon\Orm\Relation\HasManyThroughRelation
 * @see \Switon\Orm\Relation\MorphManyRelation
 */
class RelationFieldMissingException extends Exception
{
}
