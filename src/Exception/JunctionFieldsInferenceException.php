<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Raised when ORM cannot infer the required junction relation key fields.
 *
 * Guidance: Add stable junction <code>BelongsTo</code> mappings or explicit relation configuration before relying on inference.
 *
 * @see \Switon\Orm\Relation\HasManyToManyRelation
 * @see \Switon\Orm\Relation\JunctionManyRelation
 * @see \Switon\Orm\Attribute\JunctionMany
 */
class JunctionFieldsInferenceException extends Exception
{
}
