<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Query\QueryInterface;
use Switon\Validating\ConstraintInterface;

/**
 * Contract for resolving metadata for one entity class.
 *
 * Road-signs:
 * - table and connection
 * - keys and fields
 * - columnMap fillable and types
 * - constraints repository and relations
 *
 * Guidance: Prefer attributes when schema naming differs from conventions so metadata stays deterministic.
 *
 * @see \Switon\Orm\EntityMetadata
 * @see \Switon\Orm\NamingStrategyInterface
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Orm\ShardingInterface
 */
interface EntityMetadataInterface
{
    /**
     * Gets the database table name for the entity.
     *
     * The returned table name is cached exactly as defined by {@see \Switon\Orm\Attribute\Table}
     * or inferred by the naming strategy.
     *
     * If <code>$base</code> is true, schema prefix and sharding suffix will be removed from the
     * returned value (e.g. <code>schema.users</code> -> <code>users</code>, <code>users:shard</code> -> <code>users</code>).
     * The cache still stores the full, original table name.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param bool $base Whether to return base table name without schema/sharding parts
     * @return string Table name
     * @see \Switon\Orm\ShardingInterface Typical consumer (table routing)
     */
    public function getTable(string $entityClass, bool $base = false): string;

    /**
     * Gets the database connection name for the entity.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return string Connection name (default: 'default')
     * @see \Switon\Orm\ShardingInterface Typical consumer (connection routing)
     */
    public function getConnection(string $entityClass): string;

    /**
     * Gets the primary key field name for the entity.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return string Primary key field name (e.g., 'id', 'user_id')
     */
    public function getPrimaryKey(string $entityClass): string;

    /**
     * Gets the referenced key field name (used in relationships).
     *
     * Defaults to primary key, or inferred from table name (e.g., 'user_id').
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return string Referenced key field name
     * @see \Switon\Orm\JunctionRepositoryTrait Typical consumer
     */
    public function getReferencedKey(string $entityClass): string;

    /**
     * Gets all field names (property names) for the entity.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return string[] Array of field names (e.g., ['id', 'name', 'email'])
     */
    public function getFields(string $entityClass): array;

    /**
     * Gets the property-to-column name mapping.
     *
     * Only includes mappings where property name differs from column name.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return array<string, string> Property name => column name mapping (e.g., ['userId' => 'user_id'])
     */
    public function getColumnMap(string $entityClass): array;

    /**
     * Gets fillable fields and their types.
     *
     * A property is fillable only if it has {@see \Switon\Orm\Attribute\Id} or {@see \Switon\Orm\Attribute\Fillable} with <code>fillable: true</code> (default). {@see \Switon\Orm\Attribute\Fillable}<code>(false)</code> means explicitly not fillable.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return array<string, string> Field name => type name mapping (e.g., ['name' => 'string', 'age' => 'int'])
     */
    public function getFillable(string $entityClass): array;

    /**
     * Gets field types for all entity fields.
     *
     * Missing types and composite types (union/intersection) are treated as <code>mixed</code>.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return array<string, string> Field name => type name mapping
     */
    public function getFieldTypes(string $entityClass): array;

    /**
     * Gets field type for a single entity field.
     *
     * Missing types and composite types (union/intersection) are treated as <code>mixed</code>.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param string $field Field name
     * @return string Type name for the field
     */
    public function getFieldType(string $entityClass, string $field): string;

    /**
     * Gets the ownership field used for identity-aware entity binding.
     *
     * Resolution order is explicit <code>#[Owner(...)]</code> on the entity class, then implicit
     * <code>created_by</code> when that field exists on the entity, then no owner field.
     *
     * <code>#[Owner(null)]</code> explicitly disables the implicit <code>created_by</code> fallback.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return string|null Ownership field name, or null when the entity has no ownership binding
     */
    public function getOwnerField(string $entityClass): ?string;

    /**
     * Gets the date format for a specific entity field.
     *
     * Field-level <code>#[DateFormat]</code> takes precedence. If no field-level attribute is present,
     * this falls back to class-level <code>#[DateFormat]</code>, then to default <code>'U'</code>.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param string $field Field name
     * @return string Date format (e.g., 'U' for Unix timestamp, 'Y-m-d H:i:s' for datetime string)
     * @see \Switon\Query\AbstractQuery Uses this for date/time field formatting in queries
     */
    public function getDateFormat(string $entityClass, string $field): string;

    /**
     * Gets the repository instance for the entity.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return RepositoryInterface Repository instance
     * @see \Switon\Orm\Relation\HasManyToManyRelation Uses this to get junction repository
     * @see \Switon\Orm\Relation\HasManyThroughRelation Uses this to get through repository
     * @see \Switon\Orm\JunctionRepositoryTrait Uses this for junction repository access
     */
    public function getRepository(string $entityClass): RepositoryInterface;

    /**
     * Creates a Query instance for the entity class using the repository's select() method.
     *
     * This method is used by Relations to create Query instances without directly accessing Repository.
     * It uses reflection to call the protected select() method, ensuring consistency even if
     * Repository subclasses override select().
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @param array $fields Fields to select (default: empty array = all fields)
     *                      Can include relation configs: `['field1', 'relation_name' => ['field2', 'field3']]`
     * @return \Switon\Query\QueryInterface Query instance for chaining
     */
    public function createQuery(string $entityClass, array $fields = []): QueryInterface;

    /**
     * Gets the naming strategy instance for the entity.
     *
     * Used internally for converting class/property names to table/column names.
     * Always returns a naming strategy instance - uses DefaultNamingStrategy if none specified.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return NamingStrategyInterface Naming strategy instance (never null)
     * @see \Switon\Orm\EntityMetadata Uses this internally for table/column name inference
     * @see \Switon\Orm\NamingStrategy\DefaultNamingStrategy Default naming strategy used when none specified
     */
    public function getNamingStrategy(string $entityClass): NamingStrategyInterface;

    /**
     * Gets validation constraints for all fields.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return array<string, ConstraintInterface[]> Field name => constraint array mapping
     * @see \Switon\Orm\AbstractEntityManager Uses this for entity validation
     * @see \Switon\Validating\ConstraintInterface Related interface for constraint definitions
     */
    public function getConstraints(string $entityClass): array;

    /**
     * Gets relationship definitions for the entity.
     *
     * @param class-string<Entity> $entityClass Entity class name
     * @return array<string, \Switon\Orm\RelationInterface> Relationship name => relation instance mapping
     * @see \Switon\Orm\EntityMetadata::getRelations() Default implementation
     * @see \Switon\Orm\EntityMetadata::buildRelations() Attribute discovery + bind() boundary
     * @see \Switon\Orm\Attribute\RelationAttribute Attribute marker + relation factory
     * @see \Switon\Orm\RelationInterface::bind() Relation binding contract
     * @see \Switon\Orm\Relation\HasManyToManyRelation Uses this to discover junction relations
     * @see \Switon\Orm\Relation\JunctionManyRelation Uses this to discover relations
     * @see \Switon\Orm\JunctionRepositoryTrait Uses this for junction relation discovery
     */
    public function getRelations(string $entityClass): array;

    /**
     * Warms up EntityMetadata internal caches for the given entity class.
     *
     * This is intended for long-running processes (e.g. coroutine workers) to avoid reflection
     * work on the first request.
     *
     * Warms up all metadata caches except relations. To include relations, call getRelations() separately.
     *
     * @param class-string<Entity> $entityClass
     */
    public function warmUp(string $entityClass): void;

}
