<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Orm\Entity as T;
use Switon\Orm\Exception\EntityNotFoundException;
use Switon\Query\Paginator;

/**
 * Contract for querying and mutating one entity type through the repository boundary.
 *
 * Road-signs:
 * - filters normalize before Query
 * - fields may include eager relations
 * - paginate uses Page plus Paginator
 * - get and firstOrFail are not-found boundaries
 *
 * Guidance: Keep callers on repository methods instead of exposing raw query objects in application code.
 *
 * @template T of Entity
 *
 * @see \Switon\Orm\AbstractRepository
 * @see \Switon\Orm\FilterPreprocessorInterface
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Orm\Page
 */
interface RepositoryInterface
{
    /**
     * Retrieves all entities matching the filters.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     *                      (default: empty array = all entities)
     * @param array $fields Fields to select (default: empty array = all fields).
     *                      Use array keys for eager loading: `['roles' => ['role_id', 'role_name']]`
     * @param array $orders Ordering specification (default: empty array = no ordering).
     *                      Format: `['field' => 'ASC']` or `['field' => 'DESC']` or `['field' => SORT_ASC]` or `['field' => SORT_DESC]`.
     *                      Multiple fields: `['created_at' => 'DESC', 'id' => 'ASC']`
     *
     * @return array<T> Array of entities matching the filters
     * @see \Switon\Orm\AbstractRepository::all() Default implementation
     * @see \Switon\Orm\AbstractRepository::select() Query builder entry
     * @see \Switon\Orm\AbstractRepository::where() Filter application entry
     * @see \Switon\Query\QueryInterface::fetch() SQL execution boundary
     * @see \Switon\Orm\RelationManagerInterface::earlyLoad() Eager relations entry
     */
    public function all(array $filters = [], array $fields = [], array $orders = []): array;

    /**
     * Get all matched entities keyed by one field value.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param string $keyField Field name whose value will be used as array key
     * @param array $fields Fields to select (default: empty array = all fields)
     *
     * @return array<T> Entities as an associative array keyed by the specified field value
     * @see \Switon\Orm\AbstractRepository::allBy() Default implementation
     * @see \Switon\Orm\AbstractRepository::all() Query entry
     */
    public function allBy(array $filters, string $keyField, array $fields = []): array;

    /**
     * Paginates query results.
     *
     * @param Page $page Page object containing page number and page size
     * @param array $filters Filter array. See class-level "Filter Format" section.
     *                      (default: empty array = all entities)
     * @param array $fields Fields to select. Use array keys for eager loading relationships
     *                      (default: empty array = all fields)
     * @param array $orders Ordering specification (default: empty array = no ordering).
     *                      Format: `['field' => 'ASC']` or `['field' => 'DESC']` or `['field' => SORT_ASC]` or `['field' => SORT_DESC]`.
     *                      Multiple fields: `['created_at' => 'DESC', 'id' => 'ASC']`
     *
     * @return Paginator Paginator object containing items, total count, page info, etc.
     * @see \Switon\Orm\AbstractRepository::paginate() Default implementation
     * @see \Switon\Orm\AbstractRepository::select() Query builder entry
     * @see \Switon\Orm\AbstractRepository::where() Filter application entry
     * @see \Switon\Query\QueryInterface::paginate() Pagination boundary
     * @see \Switon\Orm\RelationManagerInterface::earlyLoad() Eager relations entry
     */
    public function paginate(Page $page, array $filters = [], array $fields = [], array $orders = []): Paginator;

    /**
     * Retrieves a single entity by its primary key.
     *
     * Throws {@see \Switon\Orm\Exception\EntityNotFoundException EntityNotFoundException} if the entity is not found.
     * Use {@see self::find() find()} if you want to handle missing entities gracefully (returns null instead).
     *
     * @param int|string $id The primary key value
     * @param array $fields Fields to select (default: empty array = all fields)
     *
     * @return T The entity with the given ID
     * @throws EntityNotFoundException
     * @see \Switon\Orm\AbstractRepository::get() Default implementation
     * @see \Switon\Orm\AbstractRepository::firstOrFail() Not-found boundary
     */
    public function get(int|string $id, array $fields = []): Entity;

    /**
     * Finds an entity by its primary key (returns null if not found).
     *
     * Unlike {@see self::get() get()}, this method returns `null` instead of throwing an exception
     * when the entity is not found. Use this method when you want to handle missing entities gracefully.
     *
     * @param int|string $id The primary key value
     * @param array $fields Fields to select (default: empty array = all fields)
     *
     * @return ?T The entity with the given ID, or `null` if not found
     * @see \Switon\Orm\AbstractRepository::find() Default implementation
     * @see \Switon\Orm\AbstractRepository::first() Query boundary (nullable)
     */
    public function find(int|string $id, array $fields = []): ?Entity;

    /**
     * Retrieves the first entity matching the filters.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param array $fields Fields to select (default: empty array = all fields)
     *
     * @return ?T The first matching entity, or `null` if not found
     * @see \Switon\Orm\AbstractRepository::first() Default implementation
     */
    public function first(array $filters, array $fields = []): ?Entity;

    /**
     * Retrieves the first entity matching the filters, or throws exception if not found.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param array $fields Fields to select (default: empty array = all fields)
     *
     * @return T The first matching entity
     * @throws EntityNotFoundException When no entity matches the filters
     * @see \Switon\Orm\AbstractRepository::firstOrFail() Default implementation
     * @see \Switon\Orm\Exception\EntityNotFoundException Not-found boundary
     */
    public function firstOrFail(array $filters, array $fields = []): Entity;

    /**
     * Gets a single field value from the first matching entity.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param string $field Field name to retrieve
     *
     * @return mixed The field value, or `null` if not found
     * @see \Switon\Orm\AbstractRepository::value() Default implementation
     * @see \Switon\Orm\AbstractRepository::select() Query builder entry
     * @see \Switon\Query\QueryInterface::execute() SQL execution boundary
     */
    public function value(array $filters, string $field): mixed;

    /**
     * Gets a single field value from the first matching entity, or throws exception if not found.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param string $field Field name to retrieve
     *
     * @return mixed The field value
     * @throws EntityNotFoundException When no entity matches the filters
     * @see \Switon\Orm\AbstractRepository::valueOrFail() Default implementation
     * @see \Switon\Orm\Exception\EntityNotFoundException Not-found boundary
     */
    public function valueOrFail(array $filters, string $field): mixed;

    /**
     * Gets a single field value from the first matching entity, or returns default when the resolved value is null.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param string $field Field name to retrieve
     * @param mixed $default Default value to return when no row is found or the field value is null
     *
     * @return mixed The field value, or `$default` when the resolved value is null
     * @see \Switon\Orm\AbstractRepository::valueOrDefault() Default implementation
     * @see \Switon\Orm\AbstractRepository::value() Query boundary (nullable)
     */
    public function valueOrDefault(array $filters, string $field, mixed $default): mixed;

    /**
     * Gets an array of values for a specific field from all matching entities.
     *
     * Results are automatically sorted by the field in ascending order.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     *                      (default: empty array = all entities)
     * @param string $field Field name to retrieve values from
     *
     * @return array Array of field values
     * @see \Switon\Orm\AbstractRepository::values() Default implementation
     * @see \Switon\Orm\AbstractRepository::where() Filter application entry
     * @see \Switon\Query\QueryInterface::values() Column projection boundary
     */
    public function values(array $filters, string $field): array;

    /**
     * Gets a dictionary (key-value mapping) from matching entities.
     *
     * This method supports two usage patterns:
     * 1. Primary key => field value: `pluck($filters, 'field_name')` returns `[id => value, ...]`
     * 2. Specified key => specified value: `pluck($filters, 'value_field', 'key_field')` returns `[key => value, ...]`
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param string $valueField Field name to use as value
     * @param string|null $keyField Field name to use as key (default: primary key)
     *
     * @return array Dictionary indexed by key field, with values from value field
     * @see \Switon\Orm\AbstractRepository::pluck() Default implementation
     * @see \Switon\Orm\AbstractRepository::select() Query builder entry
     * @see \Switon\Query\QueryInterface::execute() SQL execution boundary
     */
    public function pluck(array $filters, string $valueField, ?string $keyField = null): array;

    /**
     * Checks if any entity exists matching the filters.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     *
     * @return bool `true` if at least one entity exists, `false` otherwise
     * @see \Switon\Orm\AbstractRepository::exists() Default implementation
     * @see \Switon\Orm\AbstractRepository::where() Filter application entry
     * @see \Switon\Query\QueryInterface::exists() SQL existence boundary
     */
    public function exists(array $filters): bool;

    /**
     * Checks if an entity exists by its primary key.
     *
     * @param int|string $id The primary key value
     *
     * @return bool `true` if entity exists, `false` otherwise
     * @see \Switon\Orm\AbstractRepository::existsById() Default implementation
     * @see \Switon\Orm\AbstractRepository::exists() Query boundary
     */
    public function existsById(int|string $id): bool;

    /**
     * Counts entities matching the filters.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     *                      (default: empty array = count all entities)
     *
     * @return int Number of entities matching the filters
     * @see \Switon\Orm\AbstractRepository::count() Default implementation
     * @see \Switon\Orm\AbstractRepository::where() Filter application entry
     * @see \Switon\Query\QueryInterface::count() SQL aggregation boundary
     */
    public function count(array $filters = []): int;

    /**
     * Creates a new entity instance and fills it with data from array.
     *
     * Null semantics: for <code>fill()</code> and higher-level writes that rely on it, <code>null</code> means "not provided", not SQL <code>NULL</code>.
     *
     * **⚠️ WARNING: Internal Method - Prefer Using create() or update()**
     *
     * This method is primarily used internally by `create()` and `update()` methods.
     * **Prefer using `create()` or `update()` directly** instead of calling `fill()` manually.
     *
     * Only fillable fields (defined by entity metadata) are filled. Non-fillable fields are ignored.
     *
     * **Null Handling (Important):**
     * This ORM intentionally does **not** support setting fields to <code>null</code> via <code>fill()</code>/<code>create()</code>/<code>update()</code>.
     * - If a key exists in <code>$data</code> but its value is <code>null</code>, it is treated as "not provided" and will be ignored.
     * - For entity-object updates, a property value of <code>null</code> is treated as "unset" (because change detection uses <code>isset()</code>).
     *
     * **Valid Use Cases:**
     * - When you need to create an entity instance without persisting it
     * - When you need to validate filled data before saving
     * - Framework internal use
     *
     * @param array $data Array of field names and values
     *
     * @return T New entity instance filled with data
     */
    public function fill(array $data): Entity;

    /**
     * Saves an entity (creates if new, updates if exists).
     *
     * Automatically determines whether to create or update based on primary key presence.
     *
     * @param T|array $entity Entity instance or array of data
     *
     * @return T Saved entity (with generated primary key if newly created)
     * @see \Switon\Orm\AbstractRepository::save() Default implementation
     * @see \Switon\Orm\AbstractRepository::create() Array input primary key unset
     * @see \Switon\Orm\AbstractRepository::update() Original entity reload for change detection
     * @see \Switon\Orm\EntityManager::create() Persistence + validation/events
     * @see \Switon\Orm\EntityManager::update() Persistence + validation/events
     */
    public function save(Entity|array $entity): Entity;

    /**
     * Creates a new entity in the database.
     *
     * Null semantics: this method treats <code>field => null</code> as "not provided", so it will not write SQL <code>NULL</code>.
     *
     * Guidance: If shard routing depends on the primary key, do not use <code>#[Id(strategy: 'auto')]</code>.
     *
     * If entity is created from array via {@see self::fill() fill()}, the primary key is automatically
     * unset after filling for security reasons - to prevent user-provided primary key values (even if
     * primary key is fillable). This ensures that primary keys are always controlled by the system,
     * not by user input. Entity events (EntityCreating, EntityCreated) and validation are automatically triggered.
     *
     * **Key Features:**
     * - Performs fillable checking (only fillable fields are accepted)
     * - Performs validation (data is validated before insertion)
     * - Auto-generates primary key if not provided
     * - Triggers EntityCreating/EntityCreated events
     *
     * **Null Handling:**
     * This ORM intentionally does **not** support setting fields to <code>null</code> via create/update.
     * If the input array contains <code>field => null</code>, that field will be treated as "not provided" and ignored.
     *
     * **Note:** For trusted complete data (e.g., data migration, restoration), use {@see self::put() put()}
     * which skips fillable checks and validation.
     *
     * @param T|array $entity Entity instance or array of data
     *
     * @return T Created entity (with generated primary key)
     * @see \Switon\Orm\AbstractRepository::create() Default implementation
     * @see \Switon\Orm\AbstractRepository::fill() Fillable + type conversion
     * @see \Switon\Orm\EntityManager::create() Persistence + validation/events
     */
    public function create(Entity|array $entity): Entity;

    /**
     * Create multiple entities using batch persistence.
     *
     * All entities should share compatible field sets and resolve to one shard.
     *
     * Guidance: Pass homogeneous single-shard rows/entities only; if shard routing depends on the primary key, do not use <code>#[Id(strategy: 'auto')]</code>.
     * Guidance: Do not mix different populated field sets in one batch; <code>createMany()</code> expects homogeneous rows/entities.
     *
     * @param array<Entity|array> $entities Entities to create (can be Entity objects or data arrays, same fields populated)
     * @return array<Entity> Created entities with generated IDs and auto-filled fields
     */
    public function createMany(array $entities): array;

    /**
     * Puts an entity into the database using existing complete data.
     *
     * **⚠️ WARNING: Advanced Usage Only - Use with Trusted Data**
     *
     * Inserts the entity directly **without fillable checks or validation**.
     * Use this method **only** for trusted, complete data such as:
     * - Data migration between tables
     * - Restoring entities from history/archive tables
     * - Copying records to another table
     * - Duplicating entities with or without preserving IDs
     *
     * **⚠️ Security Warning:**
     * - **No fillable checking** - All fields are accepted, even non-fillable ones
     * - **No validation** - Data is assumed to be valid
     * - **Can preserve primary key** - User-provided IDs are accepted
     *
     * **Key Differences from {@see self::create() create()}:**
     * - No fillable checking (all fields are accepted)
     * - No validation (data is assumed to be valid)
     * - No auto-filling (data is assumed to be complete, preserving original audit fields)
     * - No post-INSERT reload of database default values for omitted columns ({@see \Switon\Orm\EntityManager::create()} does)
     * - Can preserve original primary key if provided
     * - Triggers EntityCreating/EntityCreated events (same as create())
     *
     * **For User Input:**
     * Always use {@see self::create() create()} which performs validation and fillable checking
     * to prevent mass assignment vulnerabilities.
     *
     * **Example:**
     * ```php
     * // ✅ Valid: Data migration
     * $repository->put($migratedEntity);
     *
     * // ❌ Invalid: User input (use create() instead)
     * $repository->put($request->all());  // Security risk!
     * ```
     *
     * @param T|array $entity Entity with complete data (can include primary key)
     *
     * @return T Entity put into database (with original or generated primary key)
     * @see \Switon\Orm\AbstractRepository::put() Default implementation
     * @see \Switon\Orm\EntityManager::put() Persistence entry (no fillable/validation)
     * @see \Switon\Orm\EntityManager::create() Safe entry for user input (fillable/validation)
     */
    public function put(Entity|array $entity): Entity;

    /**
     * Updates an existing entity in the database.
     *
     * Null semantics: this method uses patch semantics, so <code>null</code> means "not provided", not SQL <code>NULL</code>.
     *
     * Entity must exist (have a valid primary key). Entity events (EntityUpdating, EntityUpdated)
     * and validation are automatically triggered.
     *
     * Guidance: Pass only fields you intend to change.
     *
     * @param T|array $entity Entity instance or array of data (array must include primary key)
     *
     * @return T Updated entity
     * @see \Switon\Orm\AbstractRepository::update() Default implementation
     * @see \Switon\Orm\AbstractRepository::fill() Fillable + type conversion
     * @see \Switon\Orm\EntityManager::update() Persistence + validation/events
     */
    public function update(Entity|array $entity): Entity;

    /**
     * Updates an entity by its primary key.
     *
     * Validation and entity events are automatically triggered.
     *
     * @param int|string $id The primary key value
     * @param array $data Array of field names and values to update
     *
     * @return T Updated entity
     * @see \Switon\Orm\AbstractRepository::updateById() Default implementation
     * @see \Switon\Orm\AbstractRepository::update() Update entry (events/validation)
     */
    public function updateById(int|string $id, array $data): Entity;

    /**
     * Updates all entities matching the filters (bulk update).
     *
     * This method does not trigger entity events or validation. Use {@see self::update() update()}
     * or {@see self::updateById() updateById()} for individual entity updates if you need events/validation.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     * @param array $data Array of field names and values to update
     *
     * @return int Number of entities updated
     * @see \Switon\Orm\AbstractRepository::updateAll() Default implementation
     * @see \Switon\Orm\AbstractRepository::where() Query builder helper
     * @see \Switon\Query\QueryInterface::update() Bulk SQL update boundary
     */
    public function updateAll(array $filters, array $data): int;

    /**
     * Increments or decrements field value(s) by ID (atomic operation).
     *
     * This method does not trigger entity events or validation. Use {@see self::updateById() updateById()}
     * if you need to update other fields with events/validation.
     *
     * @param int|string $id The primary key value
     * @param array $counters Array of field => value pairs to increment/decrement. Use negative values to decrement.
     *
     * @return int Number of entities updated (0 or 1)
     * @see \Switon\Orm\AbstractRepository::incrementById() Default implementation
     * @see \Switon\Db\Fragment\Increment Atomic counter expression
     * @see \Switon\Query\QueryInterface::update() Bulk SQL update boundary
     */
    public function incrementById(int|string $id, array $counters): int;

    /**
     * Deletes an entity from the database.
     *
     * Entity events (EntityDeleting, EntityDeleted) are automatically triggered.
     * This performs a hard delete - the entity is permanently removed from the database.
     *
     * @param T $entity Entity to delete
     *
     * @return T Deleted entity (represents the entity that was deleted)
     * @see \Switon\Orm\AbstractRepository::delete() Default implementation
     * @see \Switon\Orm\EntityManager::delete() Persistence + events
     */
    public function delete(Entity $entity): Entity;

    /**
     * Deletes an entity by its primary key.
     *
     * Entity events (EntityDeleting, EntityDeleted) and validation are automatically triggered.
     * Returns `null` if entity doesn't exist (doesn't throw exception).
     *
     * @param int|string $id The primary key value
     *
     * @return ?T The deleted entity, or `null` if not found
     * @see \Switon\Orm\AbstractRepository::deleteById() Default implementation
     * @see \Switon\Orm\AbstractRepository::get() Not-found boundary
     * @see \Switon\Orm\EntityManager::delete() Persistence + events
     */
    public function deleteById(int|string $id): ?Entity;

    /**
     * Deletes all entities matching the filters (bulk delete).
     *
     * This method does not trigger entity events or validation. Use {@see self::delete() delete()}
     * or {@see self::deleteById() deleteById()} for individual entity deletes if you need events/validation.
     *
     * @param array $filters Filter array. See class-level "Filter Format" section.
     *
     * @return int Number of entities deleted
     * @see \Switon\Orm\AbstractRepository::deleteAll() Default implementation
     * @see \Switon\Orm\AbstractRepository::where() Query builder helper
     * @see \Switon\Query\QueryInterface::delete() Bulk SQL delete boundary
     */
    public function deleteAll(array $filters): int;
}
