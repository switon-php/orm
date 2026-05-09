<?php

declare(strict_types=1);

namespace Switon\Orm;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use Switon\Db\Fragment\Increment;
use Switon\Orm\Entity as T;
use Switon\Orm\Event\EntitiesLoaded;
use Switon\Orm\Event\EntityLoaded;
use Switon\Orm\Exception\EntityNotFoundException;
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Query\Paginator;
use Switon\Query\QueryInterface;
use Switon\Query\Table;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;

/**
 * Default repository base for one entity type.
 *
 * Road-signs:
 * - infer entity class from repository name
 * - select is the query entry
 * - where normalizes filters
 * - fields split into columns and relations
 * Guidance: Keep query construction inside repository methods or protected select(); do not expose raw QueryInterface to callers.
 * @template T of Entity
 * @implements RepositoryInterface<T>
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\FilterPreprocessorInterface
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Query\QueryInterface
 */
abstract class AbstractRepository implements RepositoryInterface
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected RelationManagerInterface $relationManager;
    #[Autowired] protected FilterPreprocessorInterface $filterPreprocessor;
    #[Autowired] protected EntityHydratorInterface $entityHydrator;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    /** @var class-string<T> */
    protected string $entityClass;

    public function __construct()
    {
        // Initialize entity class if not already set by subclass
        if (!isset($this->entityClass)) {
            $this->entityClass = $this->inferEntityClass();
        }
    }

    /**
     * Infers the entity class name from repository class name.
     *
     * Default convention: <code>App\Repository\UserRepository</code> → <code>App\Entity\User</code>
     *
     * Subclasses can override this method to implement custom naming conventions.
     *
     * @return class-string<T> The inferred entity class name
     */
    protected function inferEntityClass(): string
    {
        // Default convention: App\Repository\UserRepository -> App\Entity\User
        if (preg_match('#^(.*)\\\\Repository\\\\(.*)Repository$#', static::class, $match) === 1) {
            return $match[1] . '\\Entity\\' . $match[2];
        }
        // Fallback: if pattern doesn't match, throw exception
        RuntimeException::raise('Cannot infer entity class from repository "{class}": expected naming pattern Namespace\\Repository\\XxxRepository', ['class' => static::class]);
    }

    /**
     * Gets the entity class name managed by this repository.
     *
     * This method is protected as it's primarily used internally by Repository subclasses
     * and traits (e.g., JunctionRepositoryTrait).
     *
     * @return class-string<T> The fully qualified entity class name
     */
    protected function getEntityClass(): string
    {
        return $this->entityClass;
    }

    abstract protected function getEntityManager(): EntityManagerInterface;

    /**
     * Gets the query builder instance for creating queries.
     *
     * @return \Switon\Orm\QueryBuilderInterface Query builder instance
     */
    abstract protected function getQueryBuilder(): QueryBuilderInterface;

    /**
     * Creates a Query instance for the repository's entity class.
     *
     * **Important**: This method is protected to prevent direct access from user code.
     * Repository subclasses can override it to customize query construction (e.g., field filtering,
     * permission checks, default field selection). All Repository query methods call this method
     * to ensure consistency.
     *
     * Framework internal code (Relations) accesses this method through EntityMetadata using reflection.
     *
     * Relation configs in fields array are automatically handled by Query's `select()` method.
     *
     * @param array $fields Fields to select (default: empty array = all fields)
     *                      Can include relation configs: `['field1', 'relation_name' => ['field2', 'field3']]`
     * @return QueryInterface<T> Query instance for chaining
     */
    protected function select(array $fields = []): QueryInterface
    {
        return $this->selectRaw($fields);
    }

    /**
     * Creates a raw Query instance for the repository's entity class.
     *
     * This method contains the baseline query setup only (table, column map, and field selection)
     * so subclasses can build custom wrappers without duplicating this logic.
     *
     * @param array $fields Fields to select (default: empty array = all fields)
     * @return QueryInterface<T> Query instance for chaining
     */
    protected function selectRaw(array $fields = []): QueryInterface
    {
        $query = $this->getQueryBuilder()->create($this->entityClass);

        // Get metadata from EntityMetadata and set to Query
        $connection = $this->entityMetadata->getConnection($this->entityClass);
        $table = $this->entityMetadata->getTable($this->entityClass);
        $columnMap = $this->entityMetadata->getColumnMap($this->entityClass);

        // Set table (connection and table together)
        $query->setTable(Table::of($table, $connection));

        // Set column map
        $query->setColumnMap($columnMap);

        // If no fields specified, use all entity fields to avoid SELECT * (which returns all database columns)
        if (empty($fields)) {
            $fields = $this->entityMetadata->getFields($this->entityClass);
        }

        return $query->select($fields);
    }

    /**
     * Creates a Query instance with filters applied.
     *
     * Helper method that combines `select()` and `where()` for convenience.
     *
     * @param array $filters Filter array. See {@see \Switon\Orm\RepositoryInterface RepositoryInterface}
     *                      class-level "Filter Format" section.
     * @return QueryInterface<T> Query instance with filters applied
     *
     * @see \Switon\Orm\FilterPreprocessorInterface
     * @see \Switon\Http\RequestInterface::filters()
     * @see \Switon\Query\AbstractConditionBuilder::where()
     */
    protected function where(array $filters): QueryInterface
    {
        $filters = $this->filterPreprocessor->preprocess($filters, $this->entityClass);

        return $this->select()->where($filters);
    }

    /** {@inheritDoc} */
    public function all(array $filters = [], array $fields = [], array $orders = []): array
    {
        $relations = [];
        foreach ($fields as $k => $v) {
            if (!is_string($v)) {
                $relations[$k] = $v;
                unset($fields[$k]);
            }
        }

        if ($relations !== [] && $fields !== []) {
            $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);
            if (!in_array($primaryKey, $fields, true)) {
                $fields[] = $primaryKey;
            }
        }

        $filters = $this->filterPreprocessor->preprocess($filters, $this->entityClass);
        $query = $this->select($fields)->where($filters)->orderBy($orders);
        $rows = $query->fetch();

        if (!$rows) {
            return [];
        }

        // Convert array rows to Entity objects
        $entities = $this->hydrateEntities($rows);

        if ($relations !== []) {
            $entities = $this->relationManager->earlyLoad($this->entityClass, $entities, $relations);
        }

        return $entities;
    }

    /** {@inheritDoc} */
    public function allBy(array $filters, string $keyField, array $fields = []): array
    {
        $entities = $this->all($filters, $fields);
        $dict = [];

        foreach ($entities as $entity) {
            if (isset($entity->$keyField)) {
                $dict[$entity->$keyField] = $entity;
            }
        }

        return $dict;
    }

    /** {@inheritDoc} */
    public function paginate(Page $page, array $filters = [], array $fields = [], array $orders = []): Paginator
    {
        $relations = [];
        foreach ($fields as $k => $v) {
            if (!is_string($v)) {
                $relations[$k] = $v;
                unset($fields[$k]);
            }
        }

        if ($relations !== [] && $fields !== []) {
            $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);
            if (!in_array($primaryKey, $fields, true)) {
                $fields[] = $primaryKey;
            }
        }

        $filters = $this->filterPreprocessor->preprocess($filters, $this->entityClass);
        $query = $this->select($fields)
            ->where($filters)
            ->orderBy($orders);

        $paginator = $query->paginate($page->getPage(), $page->getLimit());

        if ($paginator->items) {
            // Convert array rows to Entity objects
            $entities = $this->hydrateEntities($paginator->items);

            if ($relations !== []) {
                $entities = $this->relationManager->earlyLoad($this->entityClass, $entities, $relations);
            }

            $paginator->items = $entities;
        }

        return $paginator;
    }

    /** {@inheritDoc} */
    public function get(int|string $id, array $fields = []): Entity
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);

        return $this->firstOrFail([$primaryKey => $id], $fields);
    }

    /** {@inheritDoc} */
    public function find(int|string $id, array $fields = []): ?Entity
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);

        return $this->first([$primaryKey => $id], $fields);
    }

    /** {@inheritDoc} */
    public function first(array $filters, array $fields = []): ?Entity
    {
        $relations = [];
        foreach ($fields as $k => $v) {
            if (!is_string($v)) {
                $relations[$k] = $v;
                unset($fields[$k]);
            }
        }

        if ($relations !== [] && $fields !== []) {
            $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);
            if (!in_array($primaryKey, $fields, true)) {
                $fields[] = $primaryKey;
            }
        }

        $filters = $this->filterPreprocessor->preprocess($filters, $this->entityClass);
        $query = $this->select($fields)->where($filters)->limit(1)->setFetchType(false);
        $row = $query->fetch();

        if (!$row) {
            return null;
        }

        // Convert array row to Entity object
        $entity = $this->hydrateEntity($row);

        if ($relations !== []) {
            $entities = $this->relationManager->earlyLoad($this->entityClass, [$entity], $relations);
            return $entities[0] ?? null;
        }

        return $entity;
    }

    /** {@inheritDoc} */
    public function firstOrFail(array $filters, array $fields = []): Entity
    {
        if (($entity = $this->first($filters, $fields)) === null) {
            EntityNotFoundException::raiseForEntityNotFound($this->entityClass, $filters);
        }

        return $entity;
    }

    /** {@inheritDoc} */
    public function value(array $filters, string $field): mixed
    {
        $filters = $this->filterPreprocessor->preprocess($filters, $this->entityClass);
        $rs = $this->select([$field])->where($filters)->limit(1)->execute();
        return ($rs && isset($rs[0][$field])) ? $rs[0][$field] : null;
    }

    /** {@inheritDoc} */
    public function valueOrFail(array $filters, string $field): mixed
    {
        $filters = $this->filterPreprocessor->preprocess($filters, $this->entityClass);
        $rs = $this->select([$field])->where($filters)->limit(1)->execute();
        if (!$rs) {
            EntityNotFoundException::raiseForEntityNotFound($this->entityClass, $filters);
        }

        return $rs[0][$field] ?? null;
    }

    /** {@inheritDoc} */
    public function valueOrDefault(array $filters, string $field, mixed $default): mixed
    {
        return $this->value($filters, $field) ?? $default;
    }

    /** {@inheritDoc} */
    public function values(array $filters, string $field): array
    {
        return $this->where($filters)->orderBy([$field => SORT_ASC])->values($field);
    }

    /** {@inheritDoc} */
    public function pluck(array $filters, string $valueField, ?string $keyField = null): array
    {
        $filters = $this->filterPreprocessor->preprocess($filters, $this->entityClass);
        $key = $keyField ?? $this->entityMetadata->getPrimaryKey($this->entityClass);
        $dict = [];

        foreach ($this->select([$key, $valueField])->where($filters)->execute() as $row) {
            if (isset($row[$key])) {
                $dict[$row[$key]] = $row[$valueField] ?? null;
            }
        }

        return $dict;
    }

    /** {@inheritDoc} */
    public function exists(array $filters): bool
    {
        return $this->where($filters)->exists();
    }

    /** {@inheritDoc} */
    public function existsById(int|string $id): bool
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);

        return $this->exists([$primaryKey => $id]);
    }

    /** {@inheritDoc} */
    public function count(array $filters = []): int
    {
        return $this->where($filters)->count();
    }

    /**
     * {@inheritDoc}
     *
     * **Security:** When creating from array (typically user input), the primary key is always
     * unset after filling to prevent user-injected ID values.
     */
    public function create(Entity|array $entity): Entity
    {
        if (is_array($entity)) {
            $entity = $this->fill($entity);
            // Unset primary key for security - prevent user-provided primary key values
            $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);
            unset($entity->$primaryKey);
        }

        return $this->getEntityManager()->create($entity);
    }

    /**
     * {@inheritDoc}
     *
     * Guidance: Keep batch items the same shape and avoid sharding schemes that depend on IDs generated during this call.
     */
    public function createMany(array $entities): array
    {
        if (empty($entities)) {
            return [];
        }

        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);

        foreach ($entities as $index => $entity) {
            if (is_array($entity)) {
                $entity = $this->fill($entity);
                unset($entity->$primaryKey);
                $entities[$index] = $entity;
            }
        }

        return $this->getEntityManager()->createMany($entities);
    }

    /** {@inheritDoc} */
    public function save(Entity|array $entity): Entity
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);
        if (is_array($entity) ? isset($entity[$primaryKey]) : isset($entity->$primaryKey)) {
            return $this->update($entity);
        } else {
            return $this->create($entity);
        }
    }

    /** {@inheritDoc} */
    public function put(Entity|array $entity): Entity
    {
        if (is_array($entity)) {
            $entity = $this->entityHydrator->hydrate($this->entityClass, $entity);
        }

        return $this->getEntityManager()->put($entity);
    }

    /** {@inheritDoc} */
    public function update(Entity|array $entity): Entity
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);

        if (is_array($entity)) {
            // Array update: must query original entity (array doesn't contain original state)
            if (!isset($entity[$primaryKey])) {
                PrimaryKeyMissingException::raise('Cannot update {entity}: primary key "{primaryKey}" is missing from data array', ['entity' => $this->entityClass, 'primaryKey' => $primaryKey]);
            }
            $original = $this->get($entity[$primaryKey]);
            $entity = $this->fill($entity);
            // Restore primary key from original to ensure it matches (even if fill() filled it from array data)
            $entity->$primaryKey = $original->$primaryKey;
        } else {
            if (!isset($entity->$primaryKey)) {
                PrimaryKeyMissingException::raise('Cannot update {entity}: primary key "{primaryKey}" property is not set', ['entity' => $this->entityClass, 'primaryKey' => $primaryKey]);
            }
            // Query original entity for change detection
            $original = $this->get($entity->$primaryKey);
        }

        return $this->getEntityManager()->update($entity, $original);
    }

    /** {@inheritDoc} */
    public function updateById(int|string $id, array $data): Entity
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);
        $data[$primaryKey] = $id;

        return $this->update($data);
    }

    /** {@inheritDoc} */
    public function updateAll(array $filters, array $data): int
    {
        return $this->where($filters)->update($data);
    }

    /** {@inheritDoc} */
    public function incrementById(int|string $id, array $counters): int
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey($this->entityClass);

        $data = [];
        foreach ($counters as $field => $value) {
            $data[$field] = new Increment($value);
        }

        return $this->where([$primaryKey => $id])->update($data);
    }


    /** {@inheritDoc} */
    public function delete(Entity $entity): Entity
    {
        return $this->getEntityManager()->delete($entity);
    }

    /** {@inheritDoc} */
    public function deleteById(int|string $id): ?Entity
    {
        try {
            $entity = $this->get($id);
            $this->delete($entity);
            return $entity;
        } catch (EntityNotFoundException) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     *
     * **Type Conversion:**
     * This implementation uses {@see \Switon\Orm\EntityHydratorInterface} and applies
     * metadata-driven casts for scalar fields.
     *
     * **Null Handling (Important):**
     * This ORM intentionally does **not** support setting fields to <code>null</code> via <code>fill()</code>/<code>create()</code>/<code>update()</code>.
     * If a field value is <code>null</code>, it is treated as "not provided" and will be ignored.
     */
    public function fill(array $data): Entity
    {
        $fillable = array_keys($this->entityMetadata->getFillable($this->entityClass));
        $filtered = [];

        foreach ($fillable as $field) {
            if (($value = $data[$field] ?? null) === null) {
                continue;
            }

            $filtered[$field] = $value;
        }

        return $this->entityHydrator->hydrate($this->entityClass, $filtered, $fillable);
    }

    /**
     * Hydrates a single entity from array data.
     *
     * @param array<string, mixed> $data
     * @return T
     */
    protected function hydrateEntity(array $data): Entity
    {
        $entity = $this->entityHydrator->hydrate($this->entityClass, $data);
        $this->eventDispatcher->dispatch(new EntityLoaded($entity));
        return $entity;
    }

    /**
     * Hydrates multiple entities from array rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, T>
     */
    protected function hydrateEntities(array $rows): array
    {
        $entities = [];
        foreach ($rows as $row) {
            $entities[] = $this->hydrateEntity($row);
        }

        $this->eventDispatcher->dispatch(new EntitiesLoaded($this->entityClass, $entities));

        return $entities;
    }

    /** {@inheritDoc} */
    public function deleteAll(array $filters): int
    {
        return $this->where($filters)->delete();
    }
}
