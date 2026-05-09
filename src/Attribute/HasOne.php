<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\MakerInterface;
use Switon\Orm\Relation\HasOneRelation;
use Switon\Orm\RelationInterface;

/**
 * Declares a one-to-one relation.
 *
 * Road-signs:
 * - foreignKey explicit or inferred
 * - relation handler owns eager and lazy loading
 * - inverse side of BelongsTo
 *
 * Guidance: Specify <code>foreignKey</code> when the related table key naming does not follow referenced-key conventions.
 *
 * @see \Switon\Orm\Attribute\ReferencedKey
 * @see \Switon\Orm\Relation\HasOneRelation
 * @see \Switon\Orm\Attribute\BelongsTo
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasOne implements RelationAttribute
{
    /**
     * Creates a new HasOne relationship attribute instance.
     *
     * @param string|null $foreignKey The foreign key field name in the related entity. If omitted, automatically
     *                                obtained from the current entity's referenced key.
     * @param string $handler Relation handler class name. Defaults to {@see \Switon\Orm\Relation\HasOneRelation}.
     *                                Must implement {@see \Switon\Orm\RelationInterface}.
     */
    public function __construct(
        public readonly ?string $foreignKey = null,
        public readonly string  $handler = HasOneRelation::class
    )
    {
    }

    public function createRelation(MakerInterface $maker): RelationInterface
    {
        return $maker->make($this->handler, [$this->foreignKey]);
    }
}
