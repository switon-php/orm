<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Relation\HasManyRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a one-to-many relation.
 *
 * Road-signs:
 * - related entity is required
 * - foreignKey explicit or inferred
 * - orderBy passes to relation handler
 * - inverse side of BelongsTo
 *
 * Guidance: Specify <code>foreignKey</code> when the related table does not follow referenced-key conventions.
 *
 * @see \Switon\Orm\Attribute\ReferencedKey
 * @see \Switon\Orm\Relation\HasManyRelation
 * @see \Switon\Orm\Attribute\BelongsTo
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany implements RelationAttribute
{
    /**
     * Creates a new HasMany relationship attribute instance.
     *
     * @param string $foreignEntity The related entity class name (required)
     * @param string|null $foreignKey The foreign key field name in the related entity. If omitted, automatically
     *                                   obtained from the current entity's referenced key.
     * @param array $orderBy Ordering specification for related entities (default: empty array)
     * @param string $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\HasManyRelation}.
     *                                   Must implement {@see \Switon\Orm\RelationInterface}.
     */
    public function __construct(
        public readonly string  $foreignEntity,
        public readonly ?string $foreignKey = null,
        public readonly array   $orderBy = [],
        public readonly string  $handler = HasManyRelation::class
    )
    {
    }

    /**
     * {@inheritDoc}
     */
    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [
            $this->foreignEntity,
            $this->foreignKey,
            $this->orderBy,
        ]);
    }
}
