<?php

declare(strict_types=1);

namespace Switon\Orm\Relation;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\Entity;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\RelationInterface;
use Switon\Query\QueryInterface;

use function array_key_exists;
use function get_object_vars;

/**
 * Base class for concrete relation algorithms.
 *
 * Road-signs:
 * - bind sets self and related classes
 * - getRelatedQuery comes from metadata
 * - helpers read raw entity data safely
 * - subclasses own eager and lazy algorithms
 *
 * Guidance: Call <code>bind()</code> before relation use so self and related class context is available.
 *
 * @see \Switon\Orm\RelationInterface
 * @see \Switon\Orm\EntityMetadataInterface::createQuery()
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Orm\Relation\BelongsToRelation
 * @see \Switon\Orm\Relation\HasManyRelation
 */
abstract class AbstractRelation implements RelationInterface
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    /** @var EntityHydratorInterface<Entity> */
    #[Autowired] protected EntityHydratorInterface $entityHydrator;

    /** @var class-string<Entity> */
    protected string $selfEntityClass = '';
    /** @var class-string<Entity> */
    protected string $relatedEntityClass = '';

    /**
     * Bind this relation to the specified entity classes.
     *
     * @param class-string<Entity> $self The entity class that owns this relation
     * @param class-string<Entity>|'' $related The related entity class, or empty when the handler already knows it
     */
    public function bind(string $self, string $related): void
    {
        $this->selfEntityClass = $self;
        // Only set relatedEntityClass if not already set by constructor
        if ($related !== '' && $this->relatedEntityClass === '') {
            $this->relatedEntityClass = $related;
        }
    }

    public function isRelatedEntityKnown(): bool
    {
        return $this->relatedEntityClass !== '';
    }

    /**
     * Get a query builder for the related entity class.
     *
     * Creates a new query instance for the related entity, which can be used
     * to build queries for loading related data. This is the base query that
     * relationship implementations can further customize.
     *
     * @return QueryInterface<mixed> Query builder for the related entity
     */
    public function getRelatedQuery(): QueryInterface
    {
        return $this->entityMetadata->createQuery($this->relatedEntityClass);
    }

    /**
     * Hydrates an entity of the relation target class.
     *
     * @param array<string, mixed> $data
     *
     * @return Entity
     */
    protected function hydrateRelatedEntity(array $data): Entity
    {
        return $this->entityHydrator->hydrate($this->relatedEntityClass, $data);
    }

    /**
     * Hydrates an entity of an explicit class.
     *
     * @param class-string<Entity> $entityClass
     * @param array<string, mixed> $data
     *
     * @return Entity
     */
    protected function hydrateEntity(string $entityClass, array $data): Entity
    {
        return $this->entityHydrator->hydrate($entityClass, $data);
    }

    /**
     * Determine whether a loaded row still carries a specific field.
     *
     * Supports both raw array rows and partially hydrated Entity instances.
     * For entities, only initialized public properties are considered present.
     *
     * @param array<string, mixed>|Entity $row
     */
    protected function hasLoadedField(array|Entity $row, string $field): bool
    {
        if (is_array($row)) {
            return array_key_exists($field, $row);
        }

        return array_key_exists($field, get_object_vars($row));
    }

    /**
     * Ensure every source row carries the field needed to attach a relation.
     *
     * @param array<int, array<string, mixed>|Entity> $rows
     */
    protected function ensureLoadedFieldOnRows(array $rows, string $field, string $name): void
    {
        foreach ($rows as $row) {
            if (!$this->hasLoadedField($row, $field)) {
                RelationFieldMissingException::raise(
                    'Missing field {field} in relation {name}',
                    ['field' => $field, 'name' => $name]
                );
            }
        }
    }

    /**
     * Get the entity class that owns this relationship.
     *
     * @return class-string<Entity> The fully qualified class name of the owning entity
     */
    public function getSelfEntityClass(): string
    {
        return $this->selfEntityClass;
    }

    /**
     * Get the related entity class for this relationship.
     *
     * @return class-string<Entity> The fully qualified class name of the related entity
     */
    public function getRelatedEntityClass(): string
    {
        return $this->relatedEntityClass;
    }
}
