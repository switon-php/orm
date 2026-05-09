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
 * Relation algorithm for many-to-many traversal through a junction entity.
 *
 * Road-signs:
 * - related entity and keys come from junction BelongsTo
 * - eager load resolves junction then related rows
 * - lazy load builds related ids from junction rows
 *
 * Guidance: Define two junction <code>BelongsTo</code> relations first; <code>foreignEntity</code> only overrides the target class.
 *
 * @see \Switon\Orm\Attribute\HasManyToMany
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Orm\Exception\RelationFieldMissingException
 */
class HasManyToManyRelation extends AbstractRelation
{
    protected string $junctionEntity;
    protected ?string $junctionSelfField = null;
    protected ?string $junctionRelatedField = null;
    protected ?string $foreignEntity;
    /** @var array<string, string> */
    protected array $orderBy;

    /**
     * Creates a new HasManyToMany relation instance.
     *
     * @param string $junctionEntity The junction entity class name (required)
     * @param ?string $foreignEntity Optional related entity class override after junction keys are inferred.
     * @param array $orderBy Ordering specification for related entities (default: empty array)
     */
    public function __construct(
        string  $junctionEntity,
        ?string $foreignEntity = null,
        array   $orderBy = []
    )
    {
        $this->junctionEntity = $junctionEntity;
        $this->foreignEntity = $foreignEntity;
        $this->orderBy = $orderBy;
    }

    protected function getJunctionSelfField(): string
    {
        if ($this->junctionSelfField !== null) {
            return $this->junctionSelfField;
        }

        $this->initializeFields();
        return $this->junctionSelfField;
    }

    protected function getJunctionRelatedField(): string
    {
        if ($this->junctionRelatedField !== null) {
            return $this->junctionRelatedField;
        }

        $this->initializeFields();
        return $this->junctionRelatedField;
    }

    protected function initializeFields(): void
    {
        [$inferredRelatedEntity, $inferredSelfField, $inferredRelatedField] = $this->inferFromRelations(
            $this->junctionEntity,
            $this->selfEntityClass
        );

        if ($inferredRelatedEntity === null || $inferredSelfField === null || $inferredRelatedField === null) {
            MisuseException::raise(
                'Cannot infer many-to-many relation from {junction}: define two BelongsTo relations.',
                ['junction' => $this->junctionEntity]
            );
        }

        $this->relatedEntityClass = $this->foreignEntity
            ?? ($this->relatedEntityClass !== '' ? $this->relatedEntityClass : $inferredRelatedEntity);
        $this->junctionSelfField = $inferredSelfField;
        $this->junctionRelatedField = $inferredRelatedField;
    }

    /**
     * {@inheritDoc}
     */
    public function getRelatedQuery(): QueryInterface
    {
        // Ensure relatedEntityClass is initialized before calling parent
        if ($this->relatedEntityClass === '') {
            $this->initializeFields();
        }
        return parent::getRelatedQuery()->orderBy($this->orderBy);
    }

    /**
     * Infers related entity class and foreign key fields from junction entity's BelongsTo relationships.
     *
     * Note: Uses reflection directly instead of getRelations() to avoid circular dependency.
     * Calling getRelations() here would cause infinite loop if the junction entity also has
     * JunctionMany or HasManyToMany relations.
     *
     * @return array{0: class-string<Entity>|null, 1: string|null, 2: string|null}
     */
    protected function inferFromRelations(string $junctionEntity, string $selfEntity): array
    {
        $relatedEntityClass = null;
        $junctionSelfField = null;
        $junctionRelatedField = null;

        $rClass = new ReflectionClass($junctionEntity);

        foreach ($rClass->getProperties() as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

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
                $targetEntityClass = $type->getName();

                // Skip if selfEntity is empty (not injected yet)
                if ($selfEntity === '') {
                    continue;
                }

                // Check if this relationship points to the current entity (self)
                if ($targetEntityClass === $selfEntity) {
                    $junctionSelfField = $foreignKey ?? $this->entityMetadata->getReferencedKey($selfEntity);
                } else {
                    // This must be the relationship pointing to the related entity
                    if ($relatedEntityClass === null) {
                        $relatedEntityClass = $targetEntityClass;
                        $junctionRelatedField = $foreignKey ?? $this->entityMetadata->getReferencedKey($targetEntityClass);
                    }
                }
            }
        }

        return [$relatedEntityClass, $junctionSelfField, $junctionRelatedField];
    }

    /**
     * {@inheritDoc}
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $junctionSelfField = $this->getJunctionSelfField();
        $junctionRelatedField = $this->getJunctionRelatedField();

        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $selfIds = array_unique(array_column($r, $selfId));

        $junctionQuery = $this->entityMetadata->createQuery($this->junctionEntity, [$junctionSelfField, $junctionRelatedField]);
        $junctionQuery->whereIn($junctionSelfField, $selfIds);
        $junctionData = $junctionQuery->execute();

        if (($firstJunction = array_first($junctionData)) !== null && !isset($firstJunction[$junctionSelfField])) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $junctionSelfField, 'name' => $name]
            );
        }

        if ($firstJunction !== null && !isset($firstJunction[$junctionRelatedField])) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $junctionRelatedField, 'name' => $name]
            );
        }

        $relatedIds = array_unique(array_column($junctionData, $junctionRelatedField));

        $relatedId = $this->entityMetadata->getPrimaryKey($this->relatedEntityClass);
        $relatedQuery->whereIn($relatedId, $relatedIds);
        $relatedEntities = $relatedQuery->indexBy($relatedId)->fetch();

        if (($firstEntity = array_first($relatedEntities)) !== null && !isset($firstEntity[$relatedId])) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $relatedId, 'name' => $name]
            );
        }

        $groupedEntities = [];
        foreach ($junctionData as $junctionRecord) {
            $relatedIdValue = $junctionRecord[$junctionRelatedField];
            $selfIdValue = $junctionRecord[$junctionSelfField];

            if (isset($relatedEntities[$relatedIdValue])) {
                $groupedEntities[$selfIdValue][] = $this->hydrateRelatedEntity($relatedEntities[$relatedIdValue]);
            }
        }

        foreach ($r as $index => $entity) {
            $selfIdValue = $entity[$selfId];
            $r[$index][$name] = $groupedEntities[$selfIdValue] ?? [];
        }

        return $r;
    }

    /**
     * {@inheritDoc}
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        $selfId = $this->entityMetadata->getPrimaryKey($this->selfEntityClass);
        $selfIdValue = $entity->$selfId;

        $junctionRepository = $this->entityMetadata->getRepository($this->junctionEntity);
        $relatedIds = $junctionRepository->values(
            [$this->getJunctionSelfField() => $selfIdValue],
            $this->getJunctionRelatedField()
        );

        $relatedQuery = $this->getRelatedQuery();
        $relatedQuery->whereIn($this->entityMetadata->getPrimaryKey($this->relatedEntityClass), $relatedIds);
        return $relatedQuery->setFetchType(true);
    }
}
