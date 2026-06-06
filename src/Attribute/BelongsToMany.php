<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Semantic alias of HasManyToMany for the inverse reading of a many-to-many relation.
 *
 * Guidance: Choose this when the property reads more naturally as “belongs to many”; behavior stays identical to <code>HasManyToMany</code>.
 *
 * @see \Switon\Orm\Attribute\HasManyToMany
 * @see \Switon\Orm\Relation\HasManyToManyRelation
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsToMany extends HasManyToMany
{
    // Inherits all functionality from HasManyToMany
    // This class exists purely for semantic clarity
}
