<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Relation\BelongsToRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a many-to-one relation from the current entity to one parent entity.
 *
 * Road-signs:
 * - foreignKey explicit or inferred from referenced key
 * - relation handler owns eager and lazy loading
 * - inverse side of HasOne and HasMany
 *
 * Guidance: Specify <code>foreignKey</code> explicitly when the current table does not follow referenced-key conventions.
 *
 * @see \Switon\Orm\Attribute\ReferencedKey
 * @see \Switon\Orm\Relation\BelongsToRelation
 * @see \Switon\Orm\Attribute\HasOne
 * @see \Switon\Orm\Attribute\HasMany
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo implements RelationAttribute
{
    /**
     * Creates a new BelongsTo relationship attribute instance.
     *
     * @param string|null $foreignKey The foreign key field name in the current entity. If omitted, automatically
     *                                inferred from the related entity's referenced key.
     * @param string $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\BelongsToRelation}.
     *                                Must implement {@see \Switon\Orm\RelationInterface}.
     */
    public function __construct(
        public readonly ?string $foreignKey = null,
        public readonly string  $handler = BelongsToRelation::class
    )
    {
    }

    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [$this->foreignKey]);
    }
}
