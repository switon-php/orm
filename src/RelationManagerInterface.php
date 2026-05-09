<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Query\QueryInterface;

/**
 * Contract for resolving and loading ORM relations.
 *
 * Road-signs:
 * - has and get inspect relation metadata
 * - getQuery builds one related query
 * - earlyLoad attaches batches
 * - lazyLoad loads one relation
 *
 * Guidance: Keep eager payloads in <code>relation =&gt; (array|QueryInterface|callable)</code> shape and express child relations with nested arrays.
 *
 * @see \Switon\Orm\RelationManager
 * @see \Switon\Orm\EntityMetadataInterface::getRelations()
 * @see \Switon\Orm\RelationInterface
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Query\QueryInterface::with()
 */
interface RelationManagerInterface
{
    /**
     * Checks if an entity has a specific relationship.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param string $name Relationship name
     *
     * @return bool True if relationship exists, false otherwise
     */
    public function has(string $entityClass, string $name): bool;

    /**
     * Gets a relationship definition for an entity.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param string $name Relationship name
     *
     * @return RelationInterface|null Relationship instance, or null if not found
     */
    public function get(string $entityClass, string $name): ?RelationInterface;

    /**
     * Performs eager loading of relationships for multiple entities.
     *
     * Loads specified relationships for all entities in the array to avoid N+1 query problems.
     * This method modifies the entities in-place by setting relationship properties.
     *
     * **Usage:**
     * <code>
     * // Load multiple relationships
     * $users = $relationManager->earlyLoad(User::class, $userArray, [
     *     'roles' => [],  // Load all role fields
     *     'profile' => ['name', 'avatar'],  // Load specific profile fields
     *     'posts' => ['title', 'created_at']  // Load specific post fields
     * ]);
     * </code>
     *
     * **Note:** Entity objects implement ArrayAccess, so relationship implementations can use
     * array syntax (`$entity[$field]`) to access properties. This allows the same code to work
     * with both Entity objects and raw arrays.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param array<Entity> $r Array of entity instances to load relationships for.
     *                         Accessed via ArrayAccess interface using array syntax.
     * @param array<string, array|QueryInterface|callable> $withs
     *        Array of relationship names to supported eager-load payloads
     *
     * @return array<Entity> Array of entities with relationships loaded
     * @see \Switon\Orm\RelationManager::earlyLoad() Default implementation
     * @see \Switon\Orm\RelationInterface::earlyLoad() Relation attach boundary
     * @see \Switon\Query\QueryInterface::with() Nested eager-load config
     */
    public function earlyLoad(string $entityClass, array $r, array $withs): array;

    /**
     * Creates a lazy loading query for a specific relationship.
     *
     * Returns a Query instance that can be further customized before execution.
     * This allows for dynamic relationship loading with additional filters or constraints.
     *
     * **Usage:**
     * <code>
     * // Basic lazy loading
     * $rolesQuery = $relationManager->lazyLoad($user, 'roles');
     * $roles = $rolesQuery->all();
     *
     * // Lazy loading with additional constraints
     * $activeRoles = $relationManager->lazyLoad($user, 'roles')
     *     ->where(['status' => 1])
     *     ->orderBy(['priority' => 'DESC'])
     *     ->all();
     * </code>
     *
     * @param Entity $entity Entity instance to load relationship for
     * @param string $relationName Relationship name
     *
     * @return QueryInterface Query instance for the relationship
     * @see \Switon\Orm\RelationManager::lazyLoad() Default implementation
     * @see \Switon\Orm\RelationInterface::lazyLoad() Relation query boundary
     */
    public function lazyLoad(Entity $entity, string $relationName): QueryInterface;

    /**
     * Creates a query for related entities based on relationship data.
     *
     * This method is used internally by relationship implementations to create queries
     * for related entities. It handles the complex logic of building queries based on
     * relationship type and configuration.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param string $name Relationship name
     * @param mixed $data Relation payload data. Supported types: null, array, callable.
     *
     * @return QueryInterface Query instance for related entities
     * @see \Switon\Orm\RelationManager::getQuery() Default implementation
     * @see \Switon\Orm\RelationInterface::getRelatedQuery() Relation base query entry
     */
    public function getQuery(string $entityClass, string $name, mixed $data): QueryInterface;
}
