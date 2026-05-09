<?php

declare(strict_types=1);

namespace Switon\Orm\Relation;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use Switon\Orm\Entity;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Query\QueryInterface;
use Switon\Query\Table;
use Throwable;
use function array_first;
use function is_int;
use function is_string;
use function str_contains;
use function strpos;
use function substr;

/**
 * Polymorphic many-to-one relation implementation.
 *
 * Road-signs:
 * - resolve type value to class
 * - normalize schema and shard table
 * - eager load by type groups
 * - lazy load by type and id
 *
 * Guidance: Keep <code>morphs</code> synchronized with stored type values so relation resolution stays deterministic.
 *
 * @see \Switon\Orm\Relation\AbstractRelation
 * @see \Switon\Orm\Attribute\MorphTo
 * @see \Switon\Orm\Relation\MorphManyRelation
 * @see \Switon\Core\Exception\RuntimeException
 */
class MorphToRelation extends AbstractRelation
{
    /**
     * Explicit allow-list of entity types that can be resolved by MorphTo.
     *
     * Supported formats:
     * - <code>[0 => App\Entity\Post::class]</code>: numeric key means "this is an entity class"; the table name is inferred via {@see \Switon\Orm\EntityMetadataInterface::getTable()}.
     * - <code>['posts' => App\Entity\Post::class]</code>: string key means explicit mapping (table => entity class).
     *
     * @var array<int|string, class-string<Entity>>
     */
    #[Autowired]
    protected array $morphs = [];

    /**
     * Initialize the MorphTo relationship handler.
     *
     * @param string $tableField The field storing the related table name
     * @param string $idField The field storing the related entity ID
     */
    public function __construct(
        protected string $tableField,
        protected string $idField
    )
    {
    }

    /**
     * Get the table field name.
     *
     * @return string The field name storing the related table name
     */
    public function getTableField(): string
    {
        return $this->tableField;
    }

    /**
     * Get the ID field name.
     *
     * @return string The field name storing the related entity ID
     */
    public function getIdField(): string
    {
        return $this->idField;
    }

    /**
     * Resolve entity class from type value.
     *
     * Supports both table names and fully-qualified class names:
     * - Contains `\\` → class name (e.g., `App\\Entity\\Post`)
     * - Otherwise → table name (e.g., `posts`, `blog_posts`)
     *
     * This allows flexibility in storing either format in the database:
     * - **Table name (recommended)**: `posts`, `blog_posts` - Switon convention
     * - **Class name (supported)**: `App\\Entity\\Post` - for compatibility
     *
     * @param string $typeValue The type value (table name or class name)
     * @return string The resolved fully-qualified entity class name
     */
    protected function resolveEntityClass(string $typeValue): string
    {
        // Contains backslash = definitely a class name
        if (str_contains($typeValue, '\\')) {
            return $typeValue;
        }

        // Otherwise treat as table name
        $baseTable = $this->extractBaseTableName($typeValue);

        foreach ($this->morphs as $k => $entityClass) {
            $configuredTable = is_int($k)
                ? $this->entityMetadata->getTable($entityClass, true)
                : (string)$k;

            if ($this->extractBaseTableName($configuredTable) === $baseTable) {
                return $entityClass;
            }
        }

        RuntimeException::raise(
            'No entity class registered for table: {table}. Configure MorphToRelation::$morphs for MorphTo relations.',
            ['table' => $typeValue]
        );
    }

    /**
     * Extracts base table name from table string.
     * Handles schema.table and table:sharding formats.
     */
    protected function extractBaseTableName(string $table): string
    {
        // Remove schema prefix (e.g., "schema.table" -> "table")
        if (($pos = strpos($table, '.')) !== false) {
            $table = substr($table, $pos + 1);
        }

        // Remove sharding suffix (e.g., "table:sharding" -> "table")
        if (($pos = strpos($table, ':')) !== false) {
            $table = substr($table, 0, $pos);
        }

        return $table;
    }

    /**
     * Perform eager loading of MorphTo relationships for multiple entities.
     *
     * Groups entities by their target table, executes one query per table type,
     * then maps results back to the original entities.
     *
     * @param array $r Array of parent entity data
     * @param QueryInterface $relatedQuery Base query configuration applied to each resolved MorphTo target.
     * @param string $name The relationship property name
     * @return array Updated entity data with loaded relationships
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array
    {
        $this->ensureLoadedFieldOnRows($r, $this->tableField, $name);
        $this->ensureLoadedFieldOnRows($r, $this->idField, $name);

        // Group by type value (table name or class name)
        $grouped = [];
        foreach ($r as $entity) {
            $typeValue = $entity[$this->tableField];
            $id = $entity[$this->idField];

            if ($typeValue !== null && !is_string($typeValue)) {
                RuntimeException::raise(
                    'MorphTo type field "{field}" must be string when present.',
                    ['field' => $this->tableField]
                );
            }

            if ($typeValue !== null && $id !== null) {
                $grouped[$typeValue][$id] = true;
            }
        }

        // Load entities for each type
        $loaded = [];
        foreach ($grouped as $typeValue => $ids) {
            $entityClass = $this->resolveEntityClass($typeValue);
            $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

            $query = $this->createTypedQuery($relatedQuery, $entityClass);
            $query->whereIn($primaryKey, array_keys($ids));
            $results = $query->fetch();

            if (($firstResult = array_first($results)) !== null && !isset($firstResult[$primaryKey])) {
                RelationFieldMissingException::raise(
                    'Missing field {field} in relation {name}',
                    ['field' => $primaryKey, 'name' => $name]
                );
            }

            foreach ($results as $result) {
                $loaded[$typeValue][$result[$primaryKey]] = $this->hydrateEntity($entityClass, $result);
            }
        }

        // Map back to entities
        foreach ($r as $index => $entity) {
            $typeValue = $entity[$this->tableField];
            $id = $entity[$this->idField];

            $r[$index][$name] = ($typeValue !== null && $id !== null)
                ? ($loaded[$typeValue][$id] ?? null)
                : null;
        }

        return $r;
    }

    /**
     * Create a lazy loading query for the MorphTo relationship.
     *
     * Returns the related entity directly (not a Query) since MorphTo
     * always returns a single entity or null.
     *
     * @param Entity $entity The current entity instance
     * @return QueryInterface Query configured for lazy loading
     */
    public function lazyLoad(Entity $entity): QueryInterface
    {
        if (!$this->hasLoadedField($entity, $this->tableField)) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $this->tableField, 'name' => $this->tableField]
            );
        }
        if (!$this->hasLoadedField($entity, $this->idField)) {
            RelationFieldMissingException::raise(
                'Missing field {field} in relation {name}',
                ['field' => $this->idField, 'name' => $this->idField]
            );
        }

        $typeValue = $entity->{$this->tableField};
        $id = $entity->{$this->idField};

        if (!is_string($typeValue) || $typeValue === '') {
            RuntimeException::raise(
                'MorphTo type field "{field}" must be non-empty string.',
                ['field' => $this->tableField]
            );
        }

        $entityClass = $this->resolveEntityClass($typeValue);
        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

        return $this->entityMetadata->createQuery($entityClass)
            ->where([$primaryKey => $id])
            ->setFetchType(false);
    }

    /**
     * Clone caller-provided query config and retarget it to the resolved MorphTo entity class.
     */
    protected function createTypedQuery(QueryInterface $relatedQuery, string $entityClass): QueryInterface
    {
        try {
            $query = clone $relatedQuery;
            $table = $this->entityMetadata->getTable($entityClass);
            $connection = $this->entityMetadata->getConnection($entityClass);
            $columnMap = $this->entityMetadata->getColumnMap($entityClass);

            return $query
                ->setEntityClass($entityClass)
                ->setTable(Table::of($table, $connection))
                ->setColumnMap($columnMap);
        } catch (Throwable) {
            // Fallback for non-cloneable / non-retargetable query doubles.
            // Production queries should use the clone-and-retarget path above.
            return $this->entityMetadata->createQuery($entityClass);
        }
    }

    /**
     * Get a query builder for the related entity class.
     *
     * For MorphTo, this returns null since the related entity type is dynamic.
     *
     * @return QueryInterface|null Null for MorphTo (dynamic type)
     */
    public function getRelatedQuery(): QueryInterface
    {
        // MorphTo doesn't have a fixed target entity class
        // Return a query for self entity as placeholder
        return $this->entityMetadata->createQuery($this->selfEntityClass);
    }

    /**
     * Get the related entity class.
     *
     * For MorphTo, this returns empty string since the type is dynamic.
     *
     * @return string Empty string (dynamic type)
     */
    public function getRelatedEntityClass(): string
    {
        // MorphTo doesn't have a fixed target entity class
        return '';
    }
}
