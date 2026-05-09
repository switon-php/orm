<?php

declare(strict_types=1);

namespace Switon\Orm\Relation;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\Entity;
use Switon\Orm\Event\RelationDataInconsistency;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Query\QueryInterface;
use function array_column;
use function array_first;
use function array_key_exists;

/**
 * One-to-many relation implementation.
 *
 * Road-signs:
 * - foreign key can be inferred
 * - eager load queries by whereIn
 * - rows group by parent id
 * - lazy load applies orderBy
 *
 * Guidance: Keep eager relation selects including the foreign key column so rows can be grouped back to parents.
 *
 * @see \Switon\Orm\Relation\AbstractRelation
 * @see \Switon\Orm\Attribute\HasMany
 * @see \Switon\Orm\Event\RelationDataInconsistency
 * @see \Switon\Orm\Exception\RelationFieldMissingException
 */
class HasManyRelation extends AbstractRelation
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    protected ?string $foreignKey = null;
    /** @var array<string, string> */
    protected array $orderBy;

    /**
     * Initialize the HasMany relationship handler.
     *
     * @param string $relatedEntity The related entity class name
     * @param string|null $foreignKey The foreign key field in the related entities.
     *                               If null, automatically inferred from current entity's referenced key.
     * @param array $orderBy Ordering specification for related entities (e.g., ['created_at' => SORT_DESC])
     */
    public function __construct(string $relatedEntity, ?string $foreignKey = null, array $orderBy = [])
    {
        $this->relatedEntityClass = $relatedEntity;
        $this->foreignKey = $foreignKey; // Store as-is, resolve later
        $this->orderBy = $orderBy;
    }

    /**
     * Get the foreign key, resolving it lazily if needed.
     *
     * @return string The foreign key field name
     */
    public function getForeignKey(): string
    {
        // Lazy initialization: resolve foreign key on first access
        return $this->foreignKey ??= $this->entityMetadata->getReferencedKey($this->selfEntityClass);
    }

    /**
     * Get a query builder for the related entities with ordering applied.
     *
     * @return QueryInterface Query builder with ordering configuration
     */
    public function getRelatedQuery(): QueryInterface
    {
        return parent::getRelatedQuery()->orderBy($this->orderBy);
    }

    /**
     * Perform eager loading of HasMany relationships for multiple entities.
     *
     * Efficiently loads related entities in a single query and groups them by
     * parent entity. Sets empty array for entities with no related records.
     *
     * **Orphaned Record Detection:**
     * This implementation detects and reports orphaned records (related entities
     * with foreign keys not matching any parent in the current batch). When orphans
     * are found, dispatches {@see RelationDataInconsistency} event for monitoring
     * and alerting. Orphaned records indicate potential data integrity issues.
     *
     * Note: BelongsTo/HasOne relations do not have orphan detection as null results
     * are semantically correct (parent/related entity may not exist). MorphMany
     * silently skips orphans (see MorphManyRelation for rationale).
     *
     * @param array $r Array of parent entity data
     * @param QueryInterface $relatedQuery Query builder for related entities
     * @param string $name The relationship property name
     * @return array Updated parent entity data with loaded relationships
     * @throws RelationFieldMissingException If foreign key field is missing from query results
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $foreignKey = $this->getForeignKey();
        $this->ensureLoadedFieldOnRows($r, $selfId, $name);

        $indexMap = [];
        foreach ($r as $index => $entity) {
            $indexMap[$entity[$selfId]][] = $index;
        }

        $selfIds = array_column($r, $selfId);
        $relatedQuery->whereIn($foreignKey, $selfIds);
        $relatedEntities = $relatedQuery->fetch();

        if (($firstEntity = array_first($relatedEntities)) !== null && !array_key_exists($foreignKey, $firstEntity)) {
            RelationFieldMissingException::raise('Missing field {field} in relation {name}', ['field' => $foreignKey, 'name' => $name]);
        }

        $groupedEntities = [];
        $orphanedForeignKeyValues = [];

        foreach ($relatedEntities as $relatedEntity) {
            $selfIdValue = $relatedEntity[$foreignKey];
            if ($selfIdValue === null || !array_key_exists($selfIdValue, $indexMap)) {
                // Track orphaned records for event dispatching
                $orphanedForeignKeyValues[] = $selfIdValue;
                continue;
            }
            foreach ($indexMap[$selfIdValue] as $parentIndex) {
                $groupedEntities[$parentIndex][] = $this->hydrateRelatedEntity($relatedEntity);
            }
        }

        // Dispatch event if orphaned records were found
        if ($orphanedForeignKeyValues !== []) {
            $this->eventDispatcher->dispatch(new RelationDataInconsistency(
                relationName: $name,
                parentEntityClass: $this->selfEntityClass,
                relatedEntityClass: $this->relatedEntityClass,
                foreignKeyField: $foreignKey,
                orphanedForeignKeyValues: $orphanedForeignKeyValues,
                orphanedCount: count($orphanedForeignKeyValues),
                totalRelatedRecords: count($relatedEntities),
            ));
        }

        foreach ($r as $index => $entity) {
            $r[$index][$name] = $groupedEntities[$index] ?? [];
        }

        return $r;
    }

    /**
     * Create a query for lazy loading the HasMany relationship.
     *
     * Builds a query to load related entities on-demand when the relationship
     * property is accessed. Configured to return an array of entities.
     *
     * @param Entity $entity The parent entity instance
     * @return QueryInterface Query configured for lazy loading the related entities
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $selfIdValue = $entity->$selfId;
        return $this->getRelatedQuery()->where([$this->getForeignKey() => $selfIdValue])->setFetchType(true);
    }
}
