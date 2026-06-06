<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Entity;
use Switon\Orm\Relation\HasManyThroughRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a has-many-through relation through an intermediate entity.
 *
 * Road-signs:
 * - target entity plus through entity
 * - firstKey and secondKey can infer
 * - orderBy passes to relation handler
 * - data path is self through target
 *
 * Guidance: Set explicit keys when the through chain does not match referenced-key conventions.
 *
 * @see \Switon\Orm\Relation\HasManyThroughRelation
 * @see \Switon\Orm\EntityMetadataInterface::getRelations()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class HasManyThrough implements RelationAttribute
{
    /**
     * Creates a new HasManyThrough relationship attribute instance.
     *
     * **Auto-Inference:**
     * - <code>firstKey</code>: Automatically inferred from current entity's referenced key
     * - <code>secondKey</code>: Automatically inferred from intermediate entity's referenced key
     *
     * @param class-string<Entity> $targetEntity The target entity class name (required)
     * @param class-string<Entity> $throughEntity The intermediate entity class name (required)
     * @param string|null $firstKey The foreign key from current entity to intermediate entity. If omitted,
     *                                    automatically obtained from current entity's referenced key.
     * @param string|null $secondKey The foreign key from intermediate entity to target entity. If omitted,
     *                                    automatically obtained from intermediate entity's referenced key.
     * @param array<string, string> $orderBy Ordering specification for related entities (default: empty array)
     * @param class-string<RelationInterface> $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\HasManyThroughRelation}.
     */
    public function __construct(
        public string  $targetEntity,
        public string  $throughEntity,
        public ?string $firstKey = null,
        public ?string $secondKey = null,
        public array   $orderBy = [],
        public string  $handler = HasManyThroughRelation::class
    ) {
    }

    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [
            $this->targetEntity,
            $this->throughEntity,
            $this->firstKey,
            $this->secondKey,
            $this->orderBy,
        ]);
    }
}
