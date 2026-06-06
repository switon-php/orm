<?php

declare(strict_types=1);

namespace Switon\Orm\Relation;

use Switon\Orm\Entity;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Query\QueryInterface;

use function array_column;
use function array_first;
use function array_key_exists;
use function array_unique;

/**
 * Many-to-one relation implementation.
 *
 * Use when the current entity stores a foreign key that points to one related parent row.
 *
 * Road-signs:
 * - foreign key can be inferred
 * - eager load parents by whereIn
 * - attach one parent or null
 * - lazy load returns single-result query
 *
 * Guidance: Keep child rows carrying the foreign key and keep the parent primary key selectable.
 *
 * @see \Switon\Orm\Relation\AbstractRelation
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Orm\Relation\HasOneRelation
 * @see \Switon\Orm\Relation\HasManyRelation
 */
class BelongsToRelation extends AbstractRelation
{
    protected ?string $foreignKey = null;

    /**
     * Initialize the BelongsTo relationship handler.
     *
     * @param string|null $foreignKey The foreign key field in the current entity.
     *                               If null, automatically inferred from related entity's referenced key.
     */
    public function __construct(?string $foreignKey = null)
    {
        $this->foreignKey = $foreignKey;
    }

    /**
     * Get the foreign key field name for this relationship.
     * Resolves it lazily if not explicitly set.
     *
     * @return string The foreign key field name
     */
    public function getForeignKey(): string
    {
        // Lazy initialization: resolve foreign key on first access
        return $this->foreignKey ??= $this->entityMetadata->getReferencedKey($this->relatedEntityClass);
    }

    /**
     * Perform eager loading of BelongsTo relationships for multiple entities.
     *
     * Efficiently loads parent entities in a single query and maps them back
     * to their child entities. Sets null for entities with no parent (orphaned).
     *
     * **No Orphan Detection:**
     * Unlike {@see HasManyRelation}, BelongsTo does not dispatch events for missing
     * parents. Returning null is semantically correct - a child entity may legitimately
     * reference a deleted/non-existent parent. Applications should handle null checks
     * in business logic if parent existence is required.
     *
     * @param array<int, array<string, mixed>|Entity> $r Array of child entity data
     * @param QueryInterface<mixed> $relatedQuery Query builder for parent entities
     * @param string $name The relationship property name
     *
     * @return array<int, array<string, mixed>|Entity> Updated child entity data with loaded relationships
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $foreignKey = $this->getForeignKey();
        $referenceId = $this->entityMetadata->getPrimaryKey($this->relatedEntityClass);

        $this->ensureLoadedFieldOnRows($r, $foreignKey, $name);

        $referenceIds = array_unique(array_column($r, $foreignKey));
        $relatedQuery->whereIn($referenceId, $referenceIds);
        $referencedEntities = $relatedQuery->indexBy($referenceId)->fetch();

        if (($firstEntity = array_first($referencedEntities)) !== null && !array_key_exists($referenceId, $firstEntity)) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $referenceId, 'name' => $name]
            );
        }

        foreach ($r as $index => $entity) {
            $referenceIdValue = $entity[$foreignKey];
            if ($referenceIdValue === null || !array_key_exists($referenceIdValue, $referencedEntities)) {
                $r[$index][$name] = null;
                continue;
            }
            $r[$index][$name] = $this->hydrateRelatedEntity($referencedEntities[$referenceIdValue]);
        }

        return $r;
    }

    /**
     * Create a query for lazy loading the BelongsTo relationship.
     *
     * Builds a query to load the parent entity on-demand when the relationship
     * property is accessed. Configured to return a single entity or null.
     *
     * @param Entity $entity The child entity instance
     *
     * @return QueryInterface<mixed> Query configured for lazy loading the parent entity
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        $foreignKey = $this->getForeignKey();
        $referenceId = $this->entityMetadata->getPrimaryKey($this->relatedEntityClass);
        $referenceIdValue = $entity->$foreignKey;
        return $this->getRelatedQuery()->where([$referenceId => $referenceIdValue])->setFetchType(false);
    }
}
