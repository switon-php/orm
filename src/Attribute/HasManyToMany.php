<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Entity;
use Switon\Orm\Relation\HasManyToManyRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a many-to-many relation through a junction entity.
 *
 * Road-signs:
 * - junction entity is required
 * - keys prefer junction BelongsTo inference
 * - foreignEntity can override the related entity
 * - orderBy passes to relation handler
 *
 * Guidance: Define the two junction <code>BelongsTo</code> relations first; set <code>foreignEntity</code> only when the target class must be overridden.
 *
 * @see \Switon\Orm\Relation\HasManyToManyRelation
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Orm\EntityMetadataInterface::getRelations()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasManyToMany implements RelationAttribute
{
    /**
     * Creates a new HasManyToMany relationship attribute instance.
     *
     * @param class-string<Entity> $junctionEntity The junction entity class name (required)
     * @param class-string<Entity>|null $foreignEntity Optional related entity class override after junction keys are inferred
     * @param array<string, string> $orderBy Ordering specification for related entities (default: empty array)
     * @param class-string<RelationInterface> $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\HasManyToManyRelation}.
     */
    public function __construct(
        public readonly string  $junctionEntity,
        public readonly ?string $foreignEntity = null,
        public readonly array   $orderBy = [],
        public readonly string  $handler = HasManyToManyRelation::class
    ) {
    }

    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [
            $this->junctionEntity,
            $this->foreignEntity,
            $this->orderBy,
        ]);
    }
}
