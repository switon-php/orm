<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Relation\MorphManyRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a reverse polymorphic one-to-many relation.
 *
 * Road-signs:
 * - related entity plus morph fields
 * - tableField and idField identify owner
 * - orderBy passes to relation handler
 * - inverse side of MorphTo
 *
 * Guidance: Keep morph type values aligned with parent base table names so inverse loading stays stable.
 *
 * @see \Switon\Orm\Attribute\MorphTo
 * @see \Switon\Orm\Relation\MorphManyRelation
 * @see \Switon\Orm\EntityMetadataInterface::getTable()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphMany implements RelationAttribute
{
    /**
     * Creates a new MorphMany relationship attribute instance.
     *
     * @param string $relatedEntity The related entity class name that has MorphTo fields
     * @param string $tableField The field in related entity storing the parent table name
     * @param string $idField The field in related entity storing the parent entity ID
     * @param array $orderBy Ordering specification for related entities (default: empty array)
     * @param string $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\MorphManyRelation}.
     *                       Must implement {@see \Switon\Orm\RelationInterface}.
     */
    public function __construct(
        public readonly string $relatedEntity,
        public readonly string $tableField,
        public readonly string $idField,
        public readonly array  $orderBy = [],
        public readonly string $handler = MorphManyRelation::class
    )
    {
    }

    /**
     * {@inheritDoc}
     */
    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [
            $this->relatedEntity,
            $this->tableField,
            $this->idField,
            $this->orderBy,
        ]);
    }
}
