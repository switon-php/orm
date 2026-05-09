<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted when relation loading detects inconsistent foreign-key data.
 *
 * Log category: <code>switon.orm.relation.data.inconsistency</code>
 *
 * Guidance: Treat repeated emissions as data integrity drift and audit the underlying relation keys.
 *
 * @see \Switon\Orm\Relation\HasManyRelation
 * @see \Switon\Orm\Relation\HasManyThroughRelation
 * @see \Switon\Orm\Exception\RelationFieldMissingException
 */
#[EventLevel(Severity::WARNING)]
class RelationDataInconsistency
{
    public function __construct(
        public readonly string $relationName,
        public readonly string $parentEntityClass,
        public readonly string $relatedEntityClass,
        public readonly string $foreignKeyField,
        public readonly array  $orphanedForeignKeyValues,
        public readonly int    $orphanedCount,
        public readonly int    $totalRelatedRecords,
    )
    {
    }

    public function getOrphanedPercentage(): float
    {
        if ($this->totalRelatedRecords === 0) {
            return 0.0;
        }
        return ($this->orphanedCount / $this->totalRelatedRecords) * 100;
    }

    public function isSevere(float $threshold = 10.0): bool
    {
        return $this->getOrphanedPercentage() > $threshold;
    }
}
