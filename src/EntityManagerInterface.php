<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Query\QueryInterface;

/**
 * Contract for entity persistence operations.
 *
 * Road-signs:
 * - query entry
 * - create update delete
 * - put bypasses part of the normal write pipeline
 * - transactions and events sit around writes
 *
 * Guidance: Use <code>put()</code> only for trusted complete data; keep normal writes on create/update/delete.
 *
 * @see \Switon\Orm\AbstractEntityManager
 * @see \Switon\Orm\EntityManager
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\Attribute\Transactional
 */
interface EntityManagerInterface
{
    /**
     * Create a query for an entity class.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param string|null $alias Optional table/collection alias
     * @return QueryInterface Query instance ready for chaining
     */
    public function query(string $entityClass, ?string $alias = null): QueryInterface;

    /**
     * Create one entity with validation, auto-fill, and lifecycle events.
     *
     * Guidance: If shard routing depends on the primary key, do not use <code>#[Id(strategy: 'auto')]</code>.
     *
     * @param Entity $entity Entity instance to create
     * @return Entity Created entity
     * @see \Switon\Orm\EntityManager::create() Default implementation
     * @see \Switon\Orm\AbstractEntityManager::validate() ORM validation execution boundary
     * @see \Switon\Orm\EntityMetadataInterface::getConstraints() Constraint collection cache
     * @see \Switon\Orm\EntityMetadata::warmupConstraints() Attribute-driven constraint discovery
     * @see \Switon\Validating\Validation::validate() Constraint execution
     */
    public function create(Entity $entity): Entity;

    /**
     * Create multiple entities with batch persistence.
     *
     * All entities should share the same type and compatible column set.
     * Guidance: Use only for homogeneous single-shard batches; if shard routing depends on the primary key, do not use <code>#[Id(strategy: 'auto')]</code>.
     * Guidance: Do not mix different populated field sets in one batch; <code>createMany()</code> expects homogeneous rows/entities.
     *
     * @param array<Entity> $entities Entities to create (same type, same fields populated)
     * @return array<Entity> Created entities
     * @see \Switon\Orm\EntityManager::createMany() Default implementation
     * @see \Switon\Orm\AbstractEntityManager::validate() ORM validation execution boundary
     */
    public function createMany(array $entities): array;

    /**
     * Inserts a full entity row without the same persistence pipeline as {@see self::create()}.
     *
     * Skips constraint validation ({@see \Switon\Orm\AbstractEntityManager::validate()}), skips
     * {@see \Switon\Orm\EntityFillerInterface::onCreating()}, and does not run the post-INSERT read that
     * hydrates database default values for omitted non-primary-key columns (see {@see \Switon\Orm\EntityManager::create()}).
     * ID resolution, shard routing, and {@see \Switon\Orm\Event\EntityCreating}/{@see \Switon\Orm\Event\EntityCreated} dispatch still apply.
     *
     * Guidance: Use only for trusted migration or restore paths; use {@see self::create()} for normal writes.
     *
     * @param Entity $entity Entity with complete data (may include primary key)
     * @return Entity Inserted entity
     * @see \Switon\Orm\EntityManager::put() Default implementation
     * @see \Switon\Orm\EntityManager::create() Validation, onCreating, default-column reload
     */
    public function put(Entity $entity): Entity;

    /**
     * Update one entity with validation, auto-fill, and lifecycle events.
     *
     * Null semantics: this method uses patch semantics, so <code>null</code> means "not provided", not SQL <code>NULL</code>.
     *
     * Guidance: Pass only fields you intend to change.
     *
     * @param Entity $entity Entity instance with updated data
     * @param Entity $original Original entity instance for change detection
     * @return Entity Updated entity
     * @see \Switon\Orm\EntityManager::update() Default implementation
     * @see \Switon\Orm\AbstractEntityManager::validate() ORM validation execution boundary
     * @see \Switon\Orm\EntityMetadataInterface::getConstraints() Constraint collection cache
     * @see \Switon\Orm\EntityMetadata::warmupConstraints() Attribute-driven constraint discovery
     * @see \Switon\Validating\Validation::validate() Constraint execution
     */
    public function update(Entity $entity, Entity $original): Entity;

    /**
     * Delete one entity with lifecycle events.
     *
     * @param Entity $entity Entity instance to delete
     * @return Entity Deleted entity snapshot
     * @see \Switon\Orm\EntityManager::delete() Default implementation
     */
    public function delete(Entity $entity): Entity;
}
