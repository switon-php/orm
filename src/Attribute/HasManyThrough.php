<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
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
class HasManyThrough implements RelationAttribute
{
    /**
     * Creates a new HasManyThrough relationship attribute instance.
     *
     * **Auto-Inference:**
     * - <code>firstKey</code>: Automatically inferred from current entity's referenced key
     * - <code>secondKey</code>: Automatically inferred from intermediate entity's referenced key
     *
     * @param string $targetEntity The target entity class name (required)
     * @param string $throughEntity The intermediate entity class name (required)
     * @param string|null $firstKey The foreign key from current entity to intermediate entity. If omitted,
     *                                    automatically obtained from current entity's referenced key.
     * @param string|null $secondKey The foreign key from intermediate entity to target entity. If omitted,
     *                                    automatically obtained from intermediate entity's referenced key.
     * @param array $orderBy Ordering specification for related entities (default: empty array)
     * @param string $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\HasManyThroughRelation}.
     *                                    Must implement {@see \Switon\Orm\RelationInterface}.
     */
    public function __construct(
        public readonly string  $targetEntity,
        public readonly string  $throughEntity,
        public readonly ?string $firstKey = null,
        public readonly ?string $secondKey = null,
        public readonly array   $orderBy = [],
        public readonly string  $handler = HasManyThroughRelation::class
    )
    {
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
