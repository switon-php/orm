<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Relation\JunctionManyRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a many relation from a junction entity to its other side.
 *
 * Road-signs:
 * - current entity acts as junction
 * - target comes from property type
 * - selfField and selfValue infer from BelongsTo
 * - orderBy passes to relation handler
 *
 * Guidance: Use this only on junction entities that expose clear <code>BelongsTo</code> mappings for inference.
 *
 * @see \Switon\Orm\Relation\JunctionManyRelation
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Orm\EntityMetadataInterface::getRelations()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class JunctionMany implements RelationAttribute
{
    /**
     * Creates a new JunctionMany relationship attribute instance.
     *
     * **Note:** All relationship parameters (foreignEntity, selfField, selfValue) are automatically inferred
     * by the framework from BelongsTo relationships and property type. This constructor only accepts
     * configuration parameters.
     *
     * @param array<string, string> $orderBy Ordering specification for related entities (default: empty array)
     * @param class-string<RelationInterface> $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\JunctionManyRelation}.
     */
    public function __construct(
        public array  $orderBy = [],
        public string $handler = JunctionManyRelation::class
    ) {
    }

    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [$this->orderBy]);
    }
}
