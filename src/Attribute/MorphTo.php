<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Relation\MorphToRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a polymorphic many-to-one relation.
 *
 * Road-signs:
 * - tableField and idField identify owner
 * - stored type values resolve through morph mapping
 * - relation handler owns eager and lazy loading
 *
 * Guidance: Store stable type values and keep them synchronized with the configured morph allow-list.
 *
 * @see \Switon\Orm\Relation\MorphToRelation
 * @see \Switon\Orm\Attribute\MorphMany
 * @see \Switon\Orm\EntityMetadataInterface::getTable()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphTo implements RelationAttribute
{
    /**
     * Creates a new MorphTo relationship attribute instance.
     *
     * @param string $tableField The field name storing the related table name (e.g., 'commentable_table')
     * @param string $idField The field name storing the related entity ID (e.g., 'commentable_id')
     * @param string $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\MorphToRelation}.
     *                       Must implement {@see \Switon\Orm\RelationInterface}.
     */
    public function __construct(
        public readonly string $tableField,
        public readonly string $idField,
        public readonly string $handler = MorphToRelation::class
    )
    {
    }

    /**
     * {@inheritDoc}
     */
    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [
            'tableField' => $this->tableField,
            'idField' => $this->idField,
        ]);
    }
}
