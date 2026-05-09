<?php

declare(strict_types=1);

namespace Switon\Orm\Relation;

use ReflectionClass;
use Switon\Core\Exception\MisuseException;
use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Entity;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Query\QueryInterface;
use function array_column;
use function array_first;
use function array_unique;

/**
 * Relation algorithm that reads related rows through the current junction entity.
 *
 * Road-signs:
 * - infer fields via BelongsTo
 * - missing inference raises misuse
 * - eager load goes through pivot rows
 * - lazy load derives related ids from pivot rows
 *
 * Guidance: Define stable <code>BelongsTo</code> mappings on the junction entity before relying on automatic inference.
 *
 * @see \Switon\Orm\Attribute\JunctionMany
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Core\Exception\MisuseException
 */
class JunctionManyRelation extends AbstractRelation
{
    protected bool $initialized = false;

    protected ?string $selfField = null;
    protected ?string $selfValue = null;
    /** @var array<string, string> */
    protected array $orderBy;

    /**
     * Creates a new JunctionManyRelation instance.
     *
     * Note: inference depends on relation binding (self/related entity classes), so we initialize lazily.
     *
     * @param array $orderBy Ordering specification for related entities
     */
    public function __construct(array $orderBy = [])
    {
        $this->orderBy = $orderBy;
    }

    protected function initializeFields(): void
    {
        if ($this->initialized) {
            return;
        }

        [$inferredSelfField, $inferredSelfValue] = $this->inferFromRelations(
            $this->selfEntityClass,
            $this->relatedEntityClass
        );

        if ($inferredSelfField === null || $inferredSelfValue === null) {
            MisuseException::raise(
                'Cannot infer junction relation from {junction}: define two BelongsTo relations.',
                ['junction' => $this->selfEntityClass]
            );
        }

        $this->selfField = $inferredSelfField;
        $this->selfValue = $inferredSelfValue;
        $this->initialized = true;
    }

    /**
     * Infers selfField and selfValue from BelongsTo relationships on the junction entity.
     *
     * Note: Uses reflection directly instead of getRelations() to avoid circular dependency.
     * Calling getRelations() here would cause infinite loop since getRelations() is currently
     * creating this JunctionManyRelation instance.
     *
     * @param string $junctionEntity The junction entity class name
     * @param string $targetEntity The target entity class name (from property type)
     *
     * @return array [selfField, selfValue]
     */
    protected function inferFromRelations(string $junctionEntity, string $targetEntity): array
    {
        $selfField = null;
        $selfValue = null;

        // Use reflection directly to avoid circular dependency with getRelations()
        $rClass = new ReflectionClass($junctionEntity);
        foreach ($rClass->getProperties() as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            // Check if property has BelongsTo attribute
            $belongsToAttributes = $property->getAttributes(BelongsTo::class);
            if ($belongsToAttributes === []) {
                continue;
            }

            $belongsToAttribute = $belongsToAttributes[0];

            // Get foreign key from attribute instance
            $belongsToInstance = $belongsToAttribute->newInstance();
            $foreignKey = $belongsToInstance->foreignKey;

            // Get related entity class from property type
            $type = $property->getType();
            if ($type && !$type->isBuiltin()) {
                $relatedEntityClass = $type->getName();

                // If foreignKey not specified, infer from referenced key of related entity
                if ($foreignKey === null) {
                    $foreignKey = $this->entityMetadata->getReferencedKey($relatedEntityClass);
                }

                // Find the BelongsTo pointing to target entity (this is selfValue)
                if ($relatedEntityClass === $targetEntity) {
                    $selfValue = $foreignKey;
                } else {
                    // This is the other BelongsTo (this is selfField)
                    $selfField = $foreignKey;
                }
            }
        }

        return [$selfField, $selfValue];
    }

    /**
     * {@inheritDoc}
     */
    public function getRelatedQuery(): QueryInterface
    {
        $this->initializeFields();
        return parent::getRelatedQuery()->orderBy($this->orderBy);
    }

    /**
     * {@inheritDoc}
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $this->initializeFields();

        $selfField = $this->selfField;
        $relatedId = $this->entityMetadata->getPrimaryKey($this->relatedEntityClass);

        $groupingIds = array_unique(array_column($r, $selfField));
        $pivotQuery = $this->entityMetadata->createQuery($this->selfEntityClass, [$selfField, $this->selfValue]);
        $pivotQuery->whereIn($selfField, $groupingIds);
        $pivotData = $pivotQuery->execute();

        if (($firstPivot = array_first($pivotData)) !== null && !isset($firstPivot[$selfField])) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $selfField, 'name' => $name]
            );
        }

        if ($firstPivot !== null && !isset($firstPivot[$this->selfValue])) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $this->selfValue, 'name' => $name]
            );
        }

        $relatedIds = array_unique(array_column($pivotData, $this->selfValue));
        $relatedQuery->whereIn($relatedId, $relatedIds);
        $relatedEntities = $relatedQuery->indexBy($relatedId)->fetch();

        if (($firstEntity = array_first($relatedEntities)) !== null && !isset($firstEntity[$relatedId])) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $relatedId, 'name' => $name]
            );
        }

        $groupedEntities = [];
        foreach ($pivotData as $pivotRecord) {
            $relatedIdValue = $pivotRecord[$this->selfValue];
            $groupingIdValue = $pivotRecord[$selfField];

            if (isset($relatedEntities[$relatedIdValue])) {
                $groupedEntities[$groupingIdValue][] = $this->hydrateRelatedEntity($relatedEntities[$relatedIdValue]);
            }
        }

        foreach ($r as $index => $entity) {
            $groupingIdValue = $entity[$selfField];
            $r[$index][$name] = $groupedEntities[$groupingIdValue] ?? [];
        }

        return $r;
    }

    /**
     * {@inheritDoc}
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        $this->initializeFields();

        $selfField = $this->selfField;
        $groupingIdValue = $entity->$selfField;

        $pivotRepository = $this->entityMetadata->getRepository($this->selfEntityClass);
        $relatedIds = $pivotRepository->values([$selfField => $groupingIdValue], $this->selfValue);

        $relatedQuery = $this->getRelatedQuery();
        $relatedQuery->whereIn($this->entityMetadata->getPrimaryKey($this->relatedEntityClass), $relatedIds);
        return $relatedQuery->setFetchType(true);
    }
}
