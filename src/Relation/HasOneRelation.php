<?php

declare(strict_types=1);

namespace Switon\Orm\Relation;

use Switon\Orm\Entity;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Query\QueryInterface;

use function array_column;
use function array_first;
use function array_unique;

/**
 * One-to-one relation implementation.
 *
 * Use when the related table stores a foreign key back to the current entity and the current side should expose at most one row.
 *
 * Road-signs:
 * - foreign key can be inferred
 * - eager load queries by whereIn
 * - attach one related row or null
 * - lazy load returns single-result query
 *
 * Guidance: Keep eager relation selects including the foreign key column so rows can be mapped back to parents.
 *
 * @see \Switon\Orm\Relation\AbstractRelation
 * @see \Switon\Orm\Attribute\HasOne
 * @see \Switon\Orm\Relation\BelongsToRelation
 */
class HasOneRelation extends AbstractRelation
{
    protected ?string $foreignKey = null;

    /**
     * Initialize the HasOne relationship handler.
     *
     * @param string|null $foreignKey The foreign key field in the related entity.
     *                               If null, automatically inferred from current entity's referenced key.
     */
    public function __construct(?string $foreignKey = null)
    {
        $this->foreignKey = $foreignKey;
    }

    /**
     * Get the foreign key, resolving it lazily if needed.
     *
     * @return string The foreign key field name
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey ??= $this->entityMetadata->getReferencedKey($this->selfEntityClass);
    }

    /**
     * Perform eager loading of HasOne relationships for multiple entities.
     *
     * Efficiently loads related entities in a single query and maps them back
     * to their parent entities. Sets null for entities with no related record.
     *
     * **No Orphan Detection:**
     * Unlike {@see HasManyRelation}, HasOne does not dispatch events for missing
     * related entities. Returning null is semantically correct - a parent may
     * legitimately not have the optional related entity (e.g., User without Profile).
     *
     * @param array<int, array<string, mixed>|Entity> $r Array of parent entity data
     * @param QueryInterface<mixed> $relatedQuery Query builder for related entities
     * @param string $name The relationship property name
     *
     * @return array<int, array<string, mixed>|Entity> Updated parent entity data with loaded relationships
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $foreignKey = $this->getForeignKey();
        $this->ensureLoadedFieldOnRows($r, $selfId, $name);

        $selfIds = array_unique(array_column($r, $selfId));
        $relatedQuery->whereIn($foreignKey, $selfIds);
        $relatedEntities = $relatedQuery->indexBy($foreignKey)->fetch();

        if (($firstEntity = array_first($relatedEntities)) !== null && !isset($firstEntity[$foreignKey])) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $foreignKey, 'name' => $name]
            );
        }

        foreach ($r as $index => $entity) {
            $selfIdValue = $entity[$selfId];
            $r[$index][$name] = isset($relatedEntities[$selfIdValue])
                ? $this->hydrateRelatedEntity($relatedEntities[$selfIdValue])
                : null;
        }

        return $r;
    }

    /**
     * Create a query for lazy loading the HasOne relationship.
     *
     * Builds a query to load the related entity on-demand when the relationship
     * property is accessed. Configured to return a single entity or null.
     *
     * @param Entity $entity The parent entity instance
     *
     * @return QueryInterface<mixed> Query configured for lazy loading the related entity
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $foreignKey = $this->getForeignKey();
        $selfIdValue = $entity->$selfId;
        return $this->getRelatedQuery()->where([$foreignKey => $selfIdValue])->setFetchType(false);
    }
}
