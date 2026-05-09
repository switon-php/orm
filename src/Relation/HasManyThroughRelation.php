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
use function array_unique;

/**
 * Relation algorithm for has-many-through traversal.
 *
 * Road-signs:
 * - firstKey reaches the through entity
 * - secondKey reaches the target entity
 * - eager load performs two-hop grouping
 * - lazy load follows the same chain
 *
 * Guidance: Keep through and target selects carrying the key fields used by the two-hop mapping.
 *
 * @see \Switon\Orm\Attribute\HasManyThrough
 * @see \Switon\Orm\Event\RelationDataInconsistency
 * @see \Switon\Orm\Exception\RelationFieldMissingException
 */
class HasManyThroughRelation extends AbstractRelation
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    protected string $throughEntityClass;
    protected ?string $firstKey;
    protected ?string $secondKey;
    /** @var array<string, string> */
    protected array $orderBy;

    /**
     * Creates a new HasManyThrough relation instance.
     *
     * @param string $targetEntity The target entity class name (required)
     * @param string $throughEntity The intermediate entity class name (required)
     * @param string|null $firstKey The foreign key from current entity to intermediate entity
     * @param string|null $secondKey The foreign key from intermediate entity to target entity
     * @param array $orderBy Ordering specification for related entities
     */
    public function __construct(
        string  $targetEntity,
        string  $throughEntity,
        ?string $firstKey = null,
        ?string $secondKey = null,
        array   $orderBy = []
    )
    {
        $this->relatedEntityClass = $targetEntity;
        $this->throughEntityClass = $throughEntity;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->orderBy = $orderBy;
    }

    protected function getFirstKey(): string
    {
        return $this->firstKey ??= $this->entityMetadata->getReferencedKey($this->selfEntityClass);
    }

    protected function getSecondKey(): string
    {
        return $this->secondKey ??= $this->entityMetadata->getReferencedKey($this->throughEntityClass);
    }

    /**
     * {@inheritDoc}
     */
    public function getRelatedQuery(): QueryInterface
    {
        return parent::getRelatedQuery()->orderBy($this->orderBy);
    }

    /**
     * {@inheritDoc}
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $throughId = $this->entityMetadata->getPrimaryKey($this->throughEntityClass);
        $firstKey = $this->getFirstKey();
        $secondKey = $this->getSecondKey();

        // Build index map for fast lookup: selfId -> index
        $indexMap = [];
        foreach ($r as $index => $entity) {
            $indexMap[$entity[$selfId]][] = $index;
        }

        // Get all self entity IDs
        $selfIds = array_column($r, $selfId);

        // Step 1: Load intermediate entities (through entity)
        // E.g., get all Posts where Post.user_id IN (user_ids)
        $throughQuery = $this->entityMetadata->createQuery($this->throughEntityClass, [$throughId, $firstKey]);
        $throughQuery->whereIn($firstKey, $selfIds);
        $throughEntities = $throughQuery->execute();

        if (empty($throughEntities)) {
            // No intermediate entities found, return empty arrays
            foreach ($r as $index => $entity) {
                $r[$index][$name] = [];
            }
            return $r;
        }

        if (($firstThrough = array_first($throughEntities)) !== null && !isset($firstThrough[$firstKey])) {
            RelationFieldMissingException::raise('Missing field {field} in relation {name}', ['field' => $firstKey, 'name' => $name]);
        }

        if ($firstThrough !== null && !isset($firstThrough[$throughId])) {
            RelationFieldMissingException::raise('Missing field {field} in relation {name}', ['field' => $throughId, 'name' => $name]);
        }

        // Step 2: Group through entity IDs by self entity ID
        // E.g., group post_ids by user_id: user_id -> [post_ids]
        $throughIdsBySelfId = [];
        foreach ($throughEntities as $throughEntity) {
            $selfIdValue = $throughEntity[$firstKey];
            $throughIdValue = $throughEntity[$throughId];
            $throughIdsBySelfId[$selfIdValue][] = $throughIdValue;
        }

        // Step 3: Get all unique through entity IDs
        $allThroughIds = array_unique(array_column($throughEntities, $throughId));

        // Step 4: Load target entities
        // E.g., get all Comments where Comment.post_id IN (post_ids)
        $relatedQuery->whereIn($secondKey, $allThroughIds);
        $relatedEntities = $relatedQuery->fetch();

        if (($firstRelated = array_first($relatedEntities)) !== null && !isset($firstRelated[$secondKey])) {
            RelationFieldMissingException::raise('Missing field {field} in relation {name}', ['field' => $secondKey, 'name' => $name]);
        }

        // Step 5: Group target entities by through entity ID
        // E.g., group comments by post_id: post_id -> [comments]
        $targetRowsByThroughId = [];
        foreach ($relatedEntities as $relatedEntity) {
            $throughIdValue = $relatedEntity[$secondKey];
            $targetRowsByThroughId[$throughIdValue][] = $relatedEntity;
        }

        // Step 6: Map target entities back to self entities
        // E.g., user_id -> [all comments from all posts]
        $groupedEntities = [];
        $orphanedForeignKeyValues = [];

        foreach ($throughIdsBySelfId as $selfIdValue => $throughIds) {
            if (!isset($indexMap[$selfIdValue])) {
                // Track orphaned records for event dispatching
                $orphanedForeignKeyValues[] = $selfIdValue;
                continue;
            }
            foreach ($indexMap[$selfIdValue] as $parentIndex) {
                $groupedEntities[$parentIndex] ??= [];

                foreach ($throughIds as $throughIdValue) {
                    if (isset($targetRowsByThroughId[$throughIdValue])) {
                        foreach ($targetRowsByThroughId[$throughIdValue] as $relatedEntity) {
                            $groupedEntities[$parentIndex][] = $this->hydrateRelatedEntity($relatedEntity);
                        }
                    }
                }
            }
        }

        // Dispatch event if orphaned records were found
        if ($orphanedForeignKeyValues !== []) {
            $this->eventDispatcher->dispatch(new RelationDataInconsistency(
                relationName: $name,
                parentEntityClass: $this->selfEntityClass,
                relatedEntityClass: $this->relatedEntityClass,
                foreignKeyField: $firstKey,
                orphanedForeignKeyValues: $orphanedForeignKeyValues,
                orphanedCount: count($orphanedForeignKeyValues),
                totalRelatedRecords: count($throughEntities),
            ));
        }

        // Step 7: Assign grouped entities to result
        foreach ($r as $index => $entity) {
            $r[$index][$name] = $groupedEntities[$index] ?? [];
        }

        return $r;
    }

    /**
     * {@inheritDoc}
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $throughId = $this->entityMetadata->getPrimaryKey($this->throughEntityClass);
        $firstKey = $this->getFirstKey();
        $secondKey = $this->getSecondKey();

        $selfIdValue = $entity->$selfId;

        // Step 1: Get all through entity IDs for this self entity
        // E.g., get all post_ids where Post.user_id = user_id
        $throughRepository = $this->entityMetadata->getRepository($this->throughEntityClass);
        $throughIds = $throughRepository->values([$firstKey => $selfIdValue], $throughId);

        $relatedQuery = $this->getRelatedQuery();
        $relatedQuery->whereIn($secondKey, $throughIds);
        return $relatedQuery->setFetchType(true);
    }
}
