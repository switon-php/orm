<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the field name other entities should use when referencing this entity.
 *
 * Road-signs:
 * - entity-wide referenced key override
 * - relation inference reads this first
 * - explicit relation foreignKey still wins
 *
 * Guidance: Use this for entity-wide naming mismatches; use relation-level <code>foreignKey</code> only for one-off exceptions.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getReferencedKey()
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Orm\Attribute\HasOne
 * @see \Switon\Orm\Attribute\HasMany
 * @see \Switon\Orm\Attribute\HasManyToMany
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ReferencedKey
{
    /**
     * Creates a new ReferencedKey attribute instance.
     *
     * @param string $name The field name to use when this entity is referenced by other entities.
     */
    public function __construct(public string $name)
    {
    }
}
