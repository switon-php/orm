<?php

declare(strict_types=1);

namespace Switon\Orm\Relation;

use Switon\Orm\Entity;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Query\QueryInterface;
use function array_column;
use function array_first;

/**
 * Reverse polymorphic one-to-many relation implementation.
 *
 * Road-signs:
 * - resolve self base table name
 * - query by tableField and idField
 * - group rows by parent id
 * - lazy load uses table plus id
 *
 * Guidance: Keep stored morph type values aligned with base table names so lookup and grouping stay stable.
 *
 * @see \Switon\Orm\Relation\AbstractRelation
 * @see \Switon\Orm\Attribute\MorphMany
 * @see \Switon\Orm\Relation\MorphToRelation
 * @see \Switon\Orm\Exception\RelationFieldMissingException
 */
class MorphManyRelation extends AbstractRelation
{
    /**
     * Initialize the MorphMany relationship handler.
     *
     * @param string $relatedEntity The related entity class name
     * @param string $tableField The field in related entity storing the parent table name
     * @param string $idField The field in related entity storing the parent entity ID
     * @param array $orderBy Ordering specification for related entities
     */
    public function __construct(
        string           $relatedEntity,
        protected string $tableField,
        protected string $idField,
        /** @var array<string, string> */
        protected array  $orderBy = []
    )
    {
        $this->relatedEntityClass = $relatedEntity;
    }

    /**
     * Get the table field name.
     *
     * @return string The field name storing the parent table name
     */
    public function getTableField(): string
    {
        return $this->tableField;
    }

    /**
     * Get the ID field name.
     *
     * @return string The field name storing the parent entity ID
     */
    public function getIdField(): string
    {
        return $this->idField;
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
     * Perform eager loading of MorphMany relationships for multiple entities.
     *
     * Efficiently loads related entities in a single query filtered by parent table name,
     * then groups them by parent entity ID.
     *
     * **Orphaned Record Handling:**
     * Unlike {@see HasManyRelation}, this implementation silently skips orphaned records
     * (records with parent IDs not in the current batch) without dispatching events.
     * This design choice reflects that polymorphic relationships may legitimately have
     * records pointing to deleted/archived parents across different entity types.
     *
     * If orphan detection is needed, consider:
     * - Adding EventDispatcherInterface dependency (like HasManyRelation)
     * - Dispatching RelationDataInconsistency events
     * - Performance impact of additional event dispatching
     *
     * @param array $r Array of parent entity data
     * @param QueryInterface $relatedQuery Query builder for related entities
     * @param string $name The relationship property name
     * @return array Updated parent entity data with loaded relationships
     * @throws RelationFieldMissingException If required fields are missing from query results
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $selfTable = $this->entityMetadata->getTable($this->selfEntityClass, true);
        $this->ensureLoadedFieldOnRows($r, $selfId, $name);

        // Build index map
        $indexMap = [];
        foreach ($r as $index => $entity) {
            $indexMap[$entity[$selfId]][] = $index;
        }

        $selfIds = array_column($r, $selfId);

        // Query with polymorphic conditions
        $relatedQuery->where([$this->tableField => $selfTable])
            ->whereIn($this->idField, $selfIds);

        $relatedEntities = $relatedQuery->fetch();

        // Validate required fields exist.
        if (($firstEntity = array_first($relatedEntities)) !== null) {
            if (!isset($firstEntity[$this->idField])) {
                RelationFieldMissingException::raise('Missing field {field} in relation {name}', ['field' => $this->idField, 'name' => $name]);
            }
            if (!isset($firstEntity[$this->tableField])) {
                RelationFieldMissingException::raise('Missing field {field} in relation {name}', ['field' => $this->tableField, 'name' => $name]);
            }
        }

        // Group entities by parent ID
        $groupedEntities = [];
        foreach ($relatedEntities as $relatedEntity) {
            $parentId = $relatedEntity[$this->idField];
            if (!isset($indexMap[$parentId])) {
                continue;
            }
            foreach ($indexMap[$parentId] as $parentIndex) {
                $groupedEntities[$parentIndex][] = $this->hydrateRelatedEntity($relatedEntity);
            }
        }

        // Attach to parent entities
        foreach ($r as $index => $entity) {
            $r[$index][$name] = $groupedEntities[$index] ?? [];
        }

        return $r;
    }

    /**
     * Create a query for lazy loading the MorphMany relationship.
     *
     * Builds a query to load related entities on-demand with polymorphic conditions.
     *
     * @param Entity $entity The parent entity instance
     * @return QueryInterface Query configured for lazy loading the related entities
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $selfIdValue = $entity->$selfId;
        $selfTable = $this->entityMetadata->getTable($this->selfEntityClass, true);

        return $this->getRelatedQuery()
            ->where([
                $this->tableField => $selfTable,
                $this->idField => $selfIdValue
            ])
            ->setFetchType(true);
    }
}
