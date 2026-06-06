<?php

declare(strict_types=1);

namespace Switon\Orm;

use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\MisuseException;
use Switon\Core\Exception\RuntimeException;
use Switon\Core\MakerInterface;
use Switon\Core\Naming;
use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\Connection;
use Switon\Orm\Attribute\DateFormat;
use Switon\Orm\Attribute\Fillable;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\JunctionMany;
use Switon\Orm\Attribute\NamingStrategy;
use Switon\Orm\Attribute\Owner;
use Switon\Orm\Attribute\ReferencedKey;
use Switon\Orm\Attribute\RelationAttribute;
use Switon\Orm\Attribute\Repository;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Attribute\Transiently;
use Switon\Orm\Exception\PrimaryKeyNotFoundException;
use Switon\Orm\Exception\RepositoryNotFoundException;
use Switon\Orm\NamingStrategy\DefaultNamingStrategy;
use Switon\Query\QueryInterface;
use Switon\Validating\AbstractConstraint;
use Switon\Validating\ConstraintInterface;

use function in_array;
use function preg_match;
use function strpos;
use function substr;

/**
 * Reflection-backed metadata cache for ORM entities.
 *
 * Road-signs:
 * - resolve table and connection
 * - cache keys fields and types
 * - cache constraints and date formats
 * - build repository and relations
 *
 * Guidance: When inference mismatches schema, prefer explicit attributes so cached metadata stays predictable.
 *
 * @see \Switon\Orm\EntityMetadataInterface
 * @see \Switon\Orm\NamingStrategyInterface
 * @see \Switon\Orm\Attribute\Connection
 * @see \Switon\Orm\Attribute\Repository
 * @see \Switon\Orm\Attribute\RelationAttribute
 * @see \Switon\Orm\Attribute\Table
 */
class EntityMetadata implements EntityMetadataInterface
{
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected MakerInterface $maker;

    /** @var array<string, ReflectionClass<Entity>> Cached reflection classes per entity class */
    protected array $rClass = [];

    /** @var array<string, string> Cached table names per entity class */
    protected array $table = [];

    /** @var array<string, string> Cached connection names per entity class */
    protected array $connection = [];

    /** @var array<string, string> Cached primary key names per entity class */
    protected array $primaryKey = [];

    /** @var array<string, string> Cached referenced key names per entity class */
    protected array $referencedKey = [];

    /** @var array<string, string[]> Cached field names per entity class */
    protected array $fields = [];

    /** @var array<string, array<string, string>> Cached column maps per entity class */
    protected array $columnMap = [];

    /** @var array<string, array<string, string>> Cached fillable fields per entity class */
    protected array $fillable = [];

    /** @var array<string, array<string, string>> Cached field types per entity class */
    protected array $fieldTypes = [];

    /** @var array<string, string|null> Cached ownership fields per entity class */
    protected array $ownerField = [];

    /** @var array<string, string> Cached class-level default date format per entity class */
    protected array $defaultDateFormat = [];

    /** @var array<string, array<string, string>> Cached per-field date format overrides per entity class */
    protected array $dateFormat = [];

    /** @var array<string, string> Cached repository class names per entity class */
    protected array $repository = [];

    /** @var array<string, string> Cached naming strategy class names per entity class */
    protected array $namingStrategy = [];

    /** @var array<string, array<string, AbstractConstraint[]>> Cached validation constraints per entity class */
    protected array $constraints = [];

    /** @var array<string, array<string, RelationInterface>> Cached relations per entity class */
    protected array $relations = [];

    /** @var array<string, ReflectionMethod> Cached reflection methods for Repository::select() calls */
    protected array $selectMethods = [];

    /** @var array<string, bool> Tracks entities currently being warmed up to prevent recursion */
    protected array $warmingUp = [];

    /**
     * Returns the cached reflection class for the entity.
     *
     * @param class-string<Entity> $entityClass Entity class name
     *
     * @return ReflectionClass<Entity> Reflection class instance
     */
    protected function getClassReflection(string $entityClass): ReflectionClass
    {
        if (($rClass = $this->rClass[$entityClass] ?? null) === null) {
            $rClass = $this->rClass[$entityClass] = new ReflectionClass($entityClass);
        }
        return $rClass;
    }

    /**
     * Returns one class-level attribute instance when present.
     *
     * @template T of object
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param class-string<T> $name Attribute class name
     *
     * @return T|null Attribute instance or null if not found
     */
    protected function getClassAttribute(string $entityClass, string $name): ?object
    {
        $rClass = $this->getClassReflection($entityClass);
        $attributes = $rClass->getAttributes($name, ReflectionAttribute::IS_INSTANCEOF);
        if (($attribute = $attributes[0] ?? null) !== null) {
            return $attribute->newInstance();
        } else {
            return null;
        }
    }

    /**
     * Returns one property-level attribute instance when present.
     *
     * @template T of object
     *
     * @param ReflectionProperty $property
     * @param class-string<T> $name
     *
     * @return T|null
     */
    protected function getPropertyAttribute(ReflectionProperty $property, string $name): ?object
    {
        if (($attribute = $property->getAttributes($name, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null) !== null) {
            return $attribute->newInstance();
        } else {
            return null;
        }
    }

    /**
     * @param class-string<Entity> $entityClass
     *
     * @inheritDoc
     */
    public function getTable(string $entityClass, bool $base = false): string
    {
        if (($table = $this->table[$entityClass] ?? null) === null) {
            if (($attribute = $this->getClassAttribute($entityClass, Table::class)) !== null) {
                /** @var Table $attribute */
                $table = $attribute->name;
            } else {
                $namingStrategy = $this->getNamingStrategy($entityClass);
                $rClass = $this->getClassReflection($entityClass);
                $className = $rClass->getShortName();
                $table = $namingStrategy->classToTableName($className);
            }
            $this->table[$entityClass] = $table;
        }

        return $base ? $this->extractBaseTableName($table) : $table;
    }

    /**
     * @param class-string<Entity> $entityClass
     *
     * @inheritDoc
     */
    public function getConnection(string $entityClass): string
    {
        if (($connection = $this->connection[$entityClass] ?? null) === null) {
            $connection = $this->connection[$entityClass] = $this->getConnectionInternal($entityClass);
        }

        return $connection;
    }

    /**
     * Resolves the configured connection name for the entity, defaulting to <code>default</code>.
     *
     * @param class-string<Entity> $entityClass Entity class name
     *
     * @return string Connection name
     */
    protected function getConnectionInternal(string $entityClass): string
    {
        if (($attribute = $this->getClassAttribute($entityClass, Connection::class)) !== null) {
            /** @var Connection $attribute */
            return $attribute->name;
        } else {
            return 'default';
        }
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    /** @inheritDoc */
    public function getPrimaryKey(string $entityClass): string
    {
        if (!isset($this->primaryKey[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->primaryKey[$entityClass];
    }

    /**
     * Infers the primary key field name from the base table name when no explicit <code>#[Id]</code> mapping exists.
     * Uses the last segment of table name (singularized) + '_id' if such property exists.
     *
     * For example:
     * - 'test_admins' -> 'admin_id' (last segment: 'admins', singularized: 'admin')
     * - 'schema.test_roles' -> 'role_id' (last segment: 'roles', singularized: 'role')
     * - 'test_orders:sharding' -> 'order_id' (last segment: 'orders', singularized: 'order')
     * - 'test_classes' -> 'class_id' (last segment: 'classes', singularized: 'class')
     *
     * @param class-string<Entity> $entityClass Entity class name
     *
     * @return string|null Inferred primary key field name or null if not found
     */
    protected function inferPrimaryKeyFromTableName(string $entityClass): ?string
    {
        $baseTableName = $this->getTable($entityClass, true);

        // Get last segment of table name (split by '_')
        $segments = explode('_', $baseTableName);
        $lastSegment = array_last($segments);

        // Convert plural form to singular form using Naming::singular()
        $singularSegment = Naming::singular($lastSegment);
        $inferredKey = $singularSegment . '_id';

        // Check if the inferred key property exists and is not readonly/static
        $rClass = $this->getClassReflection($entityClass);
        if ($rClass->hasProperty($inferredKey)) {
            $property = $rClass->getProperty($inferredKey);
            if (!$property->isReadOnly() && !$property->isStatic()) {
                return $inferredKey;
            }
        }

        return null;
    }

    /**
     * Extracts the base table name from schema-prefixed or sharding-suffixed table strings.
     * Handles schema.table and table:sharding formats.
     *
     * @param string $table Table name (may contain schema.table or table:sharding)
     *
     * @return string Base table name without schema and sharding suffix
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
     * Warms up referenced-key metadata for the entity.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupReferencedKey(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (isset($this->referencedKey[$entityClass])) {
            return;
        }

        if (($attribute = $this->getClassAttribute($entityClass, ReferencedKey::class)) !== null) {
            /** @var ReferencedKey $attribute */
            $this->referencedKey[$entityClass] = $attribute->name;
            return;
        }

        $primaryKey = $this->primaryKey[$entityClass] ?? null;
        if ($primaryKey === null) {
            $this->warmupPrimaryKey($entityClass, $rClass, $properties);
            $primaryKey = $this->primaryKey[$entityClass];
        }

        if ($primaryKey !== 'id') {
            $this->referencedKey[$entityClass] = $primaryKey;
        } else {
            $this->referencedKey[$entityClass] = $this->getTable($entityClass, true) . '_id';
        }
    }

    /** @inheritDoc */
    public function getReferencedKey(string $entityClass): string
    {
        if (!isset($this->referencedKey[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->referencedKey[$entityClass];
    }

    /** @inheritDoc */
    public function getFields(string $entityClass): array
    {
        if (!isset($this->fields[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->fields[$entityClass];
    }

    /** @inheritDoc */
    public function getColumnMap(string $entityClass): array
    {
        if (!isset($this->columnMap[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->columnMap[$entityClass];
    }

    /** @inheritDoc */
    public function getFillable(string $entityClass): array
    {
        if (!isset($this->fillable[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->fillable[$entityClass];
    }

    /** @inheritDoc */
    public function getFieldTypes(string $entityClass): array
    {
        if (!isset($this->fieldTypes[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->fieldTypes[$entityClass];
    }

    /** @inheritDoc */
    public function getFieldType(string $entityClass, string $field): string
    {
        $fieldTypes = $this->getFieldTypes($entityClass);
        return $fieldTypes[$field] ?? 'mixed';
    }

    /** @inheritDoc */
    public function getOwnerField(string $entityClass): ?string
    {
        if (!array_key_exists($entityClass, $this->ownerField)) {
            $this->warmUp($entityClass);
        }

        return $this->ownerField[$entityClass];
    }

    /**
     * Gets effective date format for one entity field.
     *
     * Resolution order: field override → class default → <code>'U'</code>.
     *
     * @inheritDoc
     */
    public function getDateFormat(string $entityClass, string $field): string
    {
        if (!isset($this->defaultDateFormat[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->dateFormat[$entityClass][$field] ?? $this->defaultDateFormat[$entityClass];
    }

    /** @inheritDoc */
    public function getRepository(string $entityClass): RepositoryInterface
    {
        if (($repository = $this->repository[$entityClass] ?? null) === null) {
            if (($attribute = $this->getClassAttribute($entityClass, Repository::class)) !== null) {
                $repository = $attribute->name;
            } elseif (preg_match('#^(.*)\\\\Entity\\\\(\\w+)$#', $entityClass, $match) === 1) {
                $repository = $match[1] . '\\Repository\\' . $match[2] . 'Repository';
            } else {
                RepositoryNotFoundException::raise('Repository class not found for entity {entity}', ['entity' => $entityClass]);
            }

            // Load repository class first, which also loads its implemented interfaces
            if (class_exists($repository)) {
                // Check if corresponding interface exists (no autoload, already loaded with class)
                $repositoryInterface = $repository . 'Interface';
                if (interface_exists($repositoryInterface, false)) {
                    $repository = $repositoryInterface;
                }
            }

            $this->repository[$entityClass] = $repository;
        }

        return $this->container->get($repository);
    }

    /** @inheritDoc */
    public function createQuery(string $entityClass, array $fields = []): QueryInterface
    {
        // Get Repository instance
        $repository = $this->getRepository($entityClass);

        $repositoryClass = $repository::class;
        if (!isset($this->selectMethods[$repositoryClass])) {
            $reflection = new ReflectionClass($repository);
            $method = $reflection->getMethod('select');
            $this->selectMethods[$repositoryClass] = $method;
        }

        // Call protected select() method using cached reflection
        return $this->selectMethods[$repositoryClass]->invoke($repository, $fields);
    }

    /** @inheritDoc */
    public function getNamingStrategy(string $entityClass): NamingStrategyInterface
    {
        if (($namingStrategy = $this->namingStrategy[$entityClass] ?? null) === null) {
            if (($attribute = $this->getClassAttribute($entityClass, NamingStrategy::class)) !== null) {
                $strategyClass = $attribute->strategy;
                $this->namingStrategy[$entityClass] = $strategyClass;
                return $this->container->get($strategyClass);
            } else {
                // No naming strategy specified - use DefaultNamingStrategy
                $this->namingStrategy[$entityClass] = DefaultNamingStrategy::class;
                return $this->container->get(DefaultNamingStrategy::class);
            }
        }

        return $this->container->get($namingStrategy);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, array<int, ConstraintInterface>>
     */
    public function getConstraints(string $entityClass): array
    {
        if (!isset($this->constraints[$entityClass])) {
            $this->warmUp($entityClass);
        }

        return $this->constraints[$entityClass];
    }

    /** @inheritDoc */
    public function getRelations(string $entityClass): array
    {
        if (($relations = $this->relations[$entityClass] ?? null) === null) {
            $relations = $this->buildRelations($entityClass, $this->getClassReflection($entityClass)->getProperties());
            $this->relations[$entityClass] = $relations;
        }

        return $relations;
    }

    /** @inheritDoc */
    public function warmUp(string $entityClass): void
    {
        // Prevent recursive warmup (e.g., if getNamingStrategy triggers EntityMetadata resolution)
        if (isset($this->warmingUp[$entityClass])) {
            return;
        }

        $this->warmingUp[$entityClass] = true;

        try {
            // Warm up simple metadata that doesn't require property reflection
            $this->getTable($entityClass);
            $this->getConnection($entityClass);
            $this->warmupDateFormats($entityClass, $this->getClassReflection($entityClass)->getProperties());

            // Get class reflection and properties once for all warmup methods
            $rClass = $this->getClassReflection($entityClass);
            $properties = $rClass->getProperties();

            // Call all warmup methods explicitly
            $this->warmupPrimaryKey($entityClass, $rClass, $properties);
            $this->warmupReferencedKey($entityClass, $rClass, $properties);
            $this->warmupFields($entityClass, $rClass, $properties);
            $this->warmupOwnerField($entityClass, $rClass, $properties);
            $this->warmupColumnMap($entityClass, $rClass, $properties);
            $this->warmupFillable($entityClass, $rClass, $properties);
            $this->warmupFieldTypes($entityClass, $rClass, $properties);
            $this->warmupConstraints($entityClass, $rClass, $properties);

            // Relations are excluded from warmUp because they may instantiate relation handlers
            // which could have side effects. Call getRelations() separately if needed.
        } finally {
            unset($this->warmingUp[$entityClass]);
        }
    }

    /**
     * Warms up class-level and field-level date format metadata.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupDateFormats(string $entityClass, array $properties): void
    {
        if (isset($this->defaultDateFormat[$entityClass])) {
            return;
        }

        $default = 'U';
        if (($attribute = $this->getClassAttribute($entityClass, DateFormat::class)) !== null) {
            /** @var DateFormat $attribute */
            $default = $attribute->get();
        }

        $this->defaultDateFormat[$entityClass] = $default;

        $fieldFormats = [];
        foreach ($properties as $property) {
            if (($attribute = $this->getPropertyAttribute($property, DateFormat::class)) !== null) {
                /** @var DateFormat $attribute */
                $fieldFormats[$property->getName()] = $attribute->get();
            }
        }

        $this->dateFormat[$entityClass] = $fieldFormats;
    }

    /**
     * Warms up primary key metadata.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupPrimaryKey(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (isset($this->primaryKey[$entityClass])) {
            return;
        }

        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            if ($property->getAttributes(Id::class) !== []) {
                $this->primaryKey[$entityClass] = $property->getName();
                return;
            }
        }

        if ($rClass->hasProperty('id')) {
            $property = $rClass->getProperty('id');
            if (!$property->isReadOnly() && !$property->isStatic()) {
                $this->primaryKey[$entityClass] = 'id';
                return;
            }
        }

        // Try to infer from table name: table_name_id
        $inferredPrimaryKey = $this->inferPrimaryKeyFromTableName($entityClass);
        if ($inferredPrimaryKey !== null) {
            $this->primaryKey[$entityClass] = $inferredPrimaryKey;
            return;
        }

        PrimaryKeyNotFoundException::raise('Primary key not defined for entity {entity}, use #[Id] attribute', ['entity' => $entityClass]);
    }

    /**
     * Warms up fields metadata.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupFields(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (isset($this->fields[$entityClass])) {
            return;
        }

        $fields = [];
        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            if ($property->getAttributes(Transiently::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                continue;
            }

            $fields[] = $property->getName();
        }

        $this->fields[$entityClass] = $fields;
    }

    /**
     * Warms up ownership-field metadata.
     *
     * Resolution order:
     * - explicit <code>#[Owner('field')]</code>
     * - explicit <code>#[Owner(null)]</code> disables fallback
     * - implicit <code>created_by</code> when the entity exposes that field
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupOwnerField(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (array_key_exists($entityClass, $this->ownerField)) {
            return;
        }

        $fields = $this->fields[$entityClass] ?? null;
        if ($fields === null) {
            $this->warmupFields($entityClass, $rClass, $properties);
            $fields = $this->fields[$entityClass];
        }

        if (($attribute = $this->getClassAttribute($entityClass, Owner::class)) !== null) {
            /** @var Owner $attribute */
            if ($attribute->field === null) {
                $this->ownerField[$entityClass] = null;
                return;
            }

            if (!in_array($attribute->field, $fields, true)) {
                RuntimeException::raise(
                    'Owner field "{field}" not found on entity "{entity}".',
                    ['field' => $attribute->field, 'entity' => $entityClass]
                );
            }

            $this->ownerField[$entityClass] = $attribute->field;
            return;
        }

        $this->ownerField[$entityClass] = in_array('created_by', $fields, true) ? 'created_by' : null;
    }

    /**
     * Warms up column map metadata.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupColumnMap(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (isset($this->columnMap[$entityClass])) {
            return;
        }

        $columnMap = [];
        $namingStrategy = $this->getNamingStrategy($entityClass);

        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            if ($property->getAttributes(Transiently::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                continue;
            }

            $propertyName = $property->getName();

            if (($attribute = $this->getPropertyAttribute($property, Column::class)) !== null) {
                $columnName = $attribute->name;
                if ($columnName === null) {
                    $columnName = $namingStrategy->propertyToColumnName($propertyName);
                }
            } else {
                $columnName = $namingStrategy->propertyToColumnName($propertyName);
            }

            if ($propertyName !== $columnName) {
                $columnMap[$propertyName] = $columnName;
            }
        }

        $this->columnMap[$entityClass] = $columnMap;
    }

    /**
     * Warms up fillable fields metadata.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupFillable(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (isset($this->fillable[$entityClass])) {
            return;
        }

        $fillable = [];
        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            if ($property->getAttributes(Transiently::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                continue;
            }

            $name = $property->getName();

            $rType = $property->getType();
            if ($rType instanceof ReflectionNamedType) {
                $type = $rType->getName();
            } else {
                $type = 'mixed';
            }

            if ($property->getAttributes(Id::class) !== []) {
                $fillable[$name] = $type;
            } elseif (($fillableAttr = $this->getPropertyAttribute($property, Fillable::class)) !== null
                && $fillableAttr->fillable
            ) {
                $fillable[$name] = $type;
            }
        }

        $this->fillable[$entityClass] = $fillable;
    }

    /**
     * Warms up field types metadata.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupFieldTypes(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (isset($this->fieldTypes[$entityClass])) {
            return;
        }

        $fieldTypes = [];
        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            if ($property->getAttributes(Transiently::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                continue;
            }

            $rType = $property->getType();
            if ($rType instanceof ReflectionNamedType) {
                $fieldTypes[$property->getName()] = $rType->getName();
            } else {
                $fieldTypes[$property->getName()] = 'mixed';
            }
        }

        $this->fieldTypes[$entityClass] = $fieldTypes;
    }

    /**
     * Warms up constraints metadata.
     *
     * @param class-string<Entity> $entityClass
     * @param ReflectionClass<Entity> $rClass
     * @param ReflectionProperty[] $properties
     */
    protected function warmupConstraints(string $entityClass, ReflectionClass $rClass, array $properties): void
    {
        if (isset($this->constraints[$entityClass])) {
            return;
        }

        $constraints = [];
        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            $propertyConstraints = [];
            if (($attributes = $property->getAttributes(
                ConstraintInterface::class,
                ReflectionAttribute::IS_INSTANCEOF
            )) !== []
            ) {
                foreach ($attributes as $attribute) {
                    $attributeName = $attribute->getName();
                    $attributeArguments = $attribute->getArguments();

                    $constraint = $attributeArguments === []
                        ? $this->container->get($attributeName)
                        : $this->maker->make($attributeName, $attributeArguments);

                    $propertyConstraints[] = $constraint;
                }
            }

            if ($propertyConstraints !== []) {
                $constraints[$property->getName()] = $propertyConstraints;
            }
        }

        $this->constraints[$entityClass] = $constraints;
    }

    /**
     * Infers JunctionMany target entity class from junction entity BelongsTo relationships.
     *
     * The relation name is expected to be the plural form of a BelongsTo property name.
     * Example: relation property "roles" expects a BelongsTo property "role".
     *
     * @param ReflectionProperty[] $properties
     */
    protected function inferJunctionManyTargetEntityClass(array $properties, string $relation): string
    {
        $expectedBelongsToPropertyName = Naming::singular($relation);

        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }

            if ($property->getName() !== $expectedBelongsToPropertyName) {
                continue;
            }

            if ($property->getAttributes(BelongsTo::class) === []) {
                continue;
            }

            $type = $property->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $type->getName();
            }
        }

        return '';
    }

    /**
     * @param class-string<Entity> $entityClass
     * @param ReflectionProperty[] $properties
     *
     * @return array<string, RelationInterface>
     */
    protected function buildRelations(string $entityClass, array $properties): array
    {
        $relations = [];

        foreach ($properties as $property) {
            if ($property->isReadOnly() || $property->isStatic()) {
                continue;
            }
            if (($attributes = $property->getAttributes(
                RelationAttribute::class,
                ReflectionAttribute::IS_INSTANCEOF
            )) === []
            ) {
                continue;
            }

            $attribute = $attributes[0];

            $relation = $property->getName();
            $attributeInstance = $attribute->newInstance();

            // Create relation handler from attribute
            $relationHandler = $attributeInstance->createRelation($this->maker);

            // Infer relatedEntityClass from property type for relations like BelongsTo and HasOne
            $relatedEntityClass = '';
            $rType = $property->getType();
            if ($rType instanceof ReflectionNamedType && !$rType->isBuiltin()) {
                $relatedEntityClass = $rType->getName();
            }

            // JunctionMany usually uses array property; infer target entity from BelongsTo by naming convention.
            if ($relatedEntityClass === '' && $attribute->getName() === JunctionMany::class) {
                $relatedEntityClass = $this->inferJunctionManyTargetEntityClass($properties, $relation);
            }

            // Bind relation to entity classes (related may be '' when the handler already set it in createRelation)
            $bindRelated = $relatedEntityClass !== '' && is_a($relatedEntityClass, Entity::class, true)
                ? $relatedEntityClass
                : '';
            $relationHandler->bind($entityClass, $bindRelated);

            if (!$relationHandler->isRelatedEntityKnown()) {
                MisuseException::raise(
                    'Cannot resolve related entity for relation {relation} on {entity}: use a typed Entity property or declare the related class on the relation attribute.',
                    ['relation' => $relation, 'entity' => $entityClass],
                );
            }

            $relations[$relation] = $relationHandler;
        }

        return $relations;
    }

}
