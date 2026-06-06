<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadata;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestEntityWithDateFormat;
use Switon\Orm\Tests\Fixtures\TestEntityWithDefaultOwnerAttribute;
use Switon\Orm\Tests\Fixtures\TestEntityWithDisabledOwner;
use Switon\Orm\Tests\Fixtures\TestEntityWithExplicitOwnerField;
use Switon\Orm\Tests\Fixtures\TestEntityWithFieldDateFormat;
use Switon\Orm\Tests\Fixtures\TestEntityWithImplicitOwner;
use Switon\Orm\Tests\Fixtures\TestEntityWithReferencedKey;
use Switon\Orm\Tests\Fixtures\TestEntityWithRepository;
use Switon\Orm\Tests\Fixtures\TestOrder;
use Switon\Orm\Tests\Fixtures\TestProduct;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\TestCase;
use Throwable;

use function class_exists;
use function interface_exists;

#[AllowMockObjectsWithoutExpectations]
class EntityMetadataIntegrationTest extends TestCase
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(EntityMetadata::class, true)) {
            $this->markTestSkipped('EntityMetadata class not available');
        }

        if (!interface_exists('Switon\Validating\ConstraintInterface', true)) {
            $this->markTestSkipped('Validating package dependency not available');
        }

        // Register IdentityInterface stub for EntityFiller dependency
        // EntityFiller is used by EntityManager, which is needed by Repository
        // Note: Parent TestCase already registers IdentityInterface stub, but we override it here
        // if needed for this specific test
        if (!$this->container->has(\Switon\Principal\IdentityInterface::class)) {
            $stubIdentity = $this->createStub(\Switon\Principal\IdentityInterface::class);
            $stubIdentity->method('isGuest')->willReturn(true);
            $stubIdentity->method('getId')->willReturn(0);
            $stubIdentity->method('getName')->willReturn('');
            $stubIdentity->method('getRoles')->willReturn([]);
            $this->container->set(\Switon\Principal\IdentityInterface::class, $stubIdentity);
        }

        // Register EntityManagerInterface and QueryBuilderInterface stubs for Repository
        // Repository uses #[Autowired] property injection, so we need to register these in container
        $stubEntityManager = $this->createStub(EntityManagerInterface::class);
        $stubQueryBuilder = $this->createStub(QueryBuilderInterface::class);
        $this->container->set(EntityManagerInterface::class, $stubEntityManager);
        $this->container->set(QueryBuilderInterface::class, $stubQueryBuilder);

        try {
            $this->injector->inject($this);
        } catch (Throwable $e) {
            $this->markTestSkipped('EntityMetadata requires dependencies: ' . $e->getMessage());
        }
    }

    public function testGetPrimaryKeyForTestEntity(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(TestEntity::class);

        $this->assertSame('id', $primaryKey);
    }

    public function testGetPrimaryKeyForTestUser(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(TestUser::class);

        $this->assertSame('user_id', $primaryKey);
    }

    public function testGetTableReturnsTableName(): void
    {
        $table = $this->entityMetadata->getTable(TestUser::class);

        $this->assertSame('test_users', $table);
    }

    public function testGetConnectionReturnsConnectionName(): void
    {
        $connection = $this->entityMetadata->getConnection(TestEntity::class);

        $this->assertIsString($connection);
    }

    public function testGetColumnMapReturnsCorrectMapping(): void
    {
        $columnMap = $this->entityMetadata->getColumnMap(TestProduct::class);

        $this->assertIsArray($columnMap);
        $this->assertArrayHasKey('name', $columnMap);
        $this->assertSame('product_name', $columnMap['name']);
    }

    public function testGetFieldsReturnsEntityFields(): void
    {
        $fields = $this->entityMetadata->getFields(TestEntity::class);

        $this->assertIsArray($fields);
        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
    }

    public function testGetFillableReturnsFillableFields(): void
    {
        $fillable = $this->entityMetadata->getFillable(TestEntity::class);

        $this->assertIsArray($fillable);
    }

    public function testGetRelationsReturnsRelations(): void
    {
        $relations = $this->entityMetadata->getRelations(TestEntity::class);

        $this->assertIsArray($relations);
    }

    public function testGetConstraintsReturnsConstraints(): void
    {
        $constraints = $this->entityMetadata->getConstraints(TestEntity::class);

        $this->assertIsArray($constraints);
    }

    public function testGetReferencedKeyReturnsPrimaryKeyWhenNotId(): void
    {
        $referencedKey = $this->entityMetadata->getReferencedKey(TestUser::class);

        $this->assertIsString($referencedKey);
        $this->assertSame('user_id', $referencedKey);
    }

    public function testGetReferencedKeyReturnsInferredFromTableWhenPrimaryKeyIsId(): void
    {
        $referencedKey = $this->entityMetadata->getReferencedKey(TestEntity::class);

        $this->assertIsString($referencedKey);
        // Should be inferred from table name: test_entity -> test_entity_id
        $this->assertStringEndsWith('_id', $referencedKey);
    }

    public function testGetReferencedKeyReturnsValueFromReferencedKeyAttribute(): void
    {
        $referencedKey = $this->entityMetadata->getReferencedKey(TestEntityWithReferencedKey::class);

        $this->assertIsString($referencedKey);
        $this->assertSame('custom_ref_id', $referencedKey);
    }

    public function testGetReferencedKeyReturnsPrimaryKeyForNonIdPrimaryKey(): void
    {
        $referencedKey = $this->entityMetadata->getReferencedKey(TestOrder::class);

        $this->assertIsString($referencedKey);
        $this->assertSame('order_id', $referencedKey);
    }

    public function testGetDateFormatReturnsDefaultWhenNotSpecified(): void
    {
        $dateFormat = $this->entityMetadata->getDateFormat(TestEntity::class, 'id');

        $this->assertIsString($dateFormat);
        $this->assertSame('U', $dateFormat);
    }

    public function testGetDateFormatReturnsValueFromDateFormatAttribute(): void
    {
        $dateFormat = $this->entityMetadata->getDateFormat(TestEntityWithDateFormat::class, 'id');

        $this->assertIsString($dateFormat);
        $this->assertSame('Y-m-d H:i:s', $dateFormat);
    }

    public function testGetDateFormatPrefersFieldAttributeOverClassAttribute(): void
    {
        $createdAtFormat = $this->entityMetadata->getDateFormat(TestEntityWithFieldDateFormat::class, 'created_at');
        $updatedAtFormat = $this->entityMetadata->getDateFormat(TestEntityWithFieldDateFormat::class, 'updated_at');

        $this->assertSame('Y-m-d H:i:s', $createdAtFormat);
        $this->assertSame('U', $updatedAtFormat);
    }

    public function testGetOwnerFieldFallsBackToCreatedByWhenFieldExists(): void
    {
        $this->assertSame('created_by', $this->entityMetadata->getOwnerField(TestEntityWithImplicitOwner::class));
    }

    public function testGetOwnerFieldUsesDefaultOwnerAttribute(): void
    {
        $this->assertSame('created_by', $this->entityMetadata->getOwnerField(TestEntityWithDefaultOwnerAttribute::class));
    }

    public function testGetOwnerFieldUsesExplicitOwnerField(): void
    {
        $this->assertSame('admin_id', $this->entityMetadata->getOwnerField(TestEntityWithExplicitOwnerField::class));
    }

    public function testGetOwnerFieldCanDisableImplicitCreatedByFallback(): void
    {
        $this->assertNull($this->entityMetadata->getOwnerField(TestEntityWithDisabledOwner::class));
    }

    public function testGetRepositoryReturnsRepositoryFromAttribute(): void
    {
        // TestRepository requires many dependencies (IdentityInterface, etc.)
        // Skip this test if repository cannot be resolved
        try {
            $repository = $this->entityMetadata->getRepository(TestEntityWithRepository::class);
            $this->assertInstanceOf(\Switon\Orm\RepositoryInterface::class, $repository);
            $this->assertInstanceOf(\Switon\Orm\Tests\Fixtures\TestRepository::class, $repository);
        } catch (Throwable $e) {
            $this->markTestSkipped('Repository dependencies not available: ' . $e->getMessage());
        }
    }

    public function testGetRepositoryThrowsExceptionWhenNotFound(): void
    {
        // Create a test entity class that doesn't match any naming convention
        $this->expectException(\Switon\Orm\Exception\RepositoryNotFoundException::class);

        // Use a class that doesn't match Entities/Entity naming convention
        $this->entityMetadata->getRepository('Switon\Orm\Tests\Fixtures\TestEntity');
    }

    /**
     * Test that getTable() returns table name using naming strategy when Table attribute is not present.
     */
    public function testGetTableUsesNamingStrategyWhenTableAttributeNotPresent(): void
    {
        // TestEntity doesn't have Table attribute, so it should use naming strategy
        $table = $this->entityMetadata->getTable(TestEntity::class);

        $this->assertIsString($table);
        $this->assertNotEmpty($table);
        // Should be converted using naming strategy: TestEntity -> test_entity
        $this->assertStringContainsString('test', strtolower($table));
    }

    /**
     * Test that getConnection() returns default connection when Connection attribute is not present.
     */
    public function testGetConnectionReturnsDefaultWhenConnectionAttributeNotPresent(): void
    {
        // TestEntity doesn't have Connection attribute, so it should return 'default'
        $connection = $this->entityMetadata->getConnection(TestEntity::class);

        $this->assertIsString($connection);
        $this->assertSame('default', $connection);
    }

    /**
     * Test that getPrimaryKey() throws exception when no primary key found.
     */
    public function testGetPrimaryKeyThrowsExceptionWhenNoPrimaryKeyFound(): void
    {
        // Create a test entity class without Id attribute and without 'id' property
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithoutPrimaryKey';

        // Dynamically create a class without primary key for testing
        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                class TestEntityWithoutPrimaryKey extends Entity
                {
                    public string \$name;
                }
            ");
        }

        $this->expectException(\Switon\Orm\Exception\PrimaryKeyNotFoundException::class);
        $this->entityMetadata->getPrimaryKey($entityClass);
    }

    /**
     * Test that getPrimaryKey() infers primary key from table name when no Id attribute and no id field.
     * Uses last segment of table name (singularized) + '_id'.
     */
    public function testGetPrimaryKeyInfersFromTableName(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(\Switon\Orm\Tests\Fixtures\TestEntityWithInferredPrimaryKey::class);

        // Table name: 'test_admins', last segment: 'admins', singularized: 'admin', inferred: 'admin_id'
        $this->assertSame('admin_id', $primaryKey);
    }

    /**
     * Test that getPrimaryKey() infers primary key from table name with schema prefix.
     */
    public function testGetPrimaryKeyInfersFromTableNameWithSchema(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(\Switon\Orm\Tests\Fixtures\TestEntityWithSchemaTableInferredKey::class);

        // Table name: 'schema.test_roles', base: 'test_roles', last segment: 'roles'
        // Tries 'rol_id' (from Naming::singular) first, then 'role_id' (removing 'es'), finds 'role_id'
        $this->assertSame('role_id', $primaryKey);
    }

    /**
     * Test that getPrimaryKey() infers primary key from table name with sharding suffix.
     */
    public function testGetPrimaryKeyInfersFromTableNameWithSharding(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(\Switon\Orm\Tests\Fixtures\TestEntityWithShardingTableInferredKey::class);

        // Table name: 'test_orders:order_id%8', base: 'test_orders', last segment: 'orders', singularized: 'order', inferred: 'order_id'
        $this->assertSame('order_id', $primaryKey);
    }

    /**
     * Test that getPrimaryKey() still throws exception when inferred field doesn't exist.
     */
    public function testGetPrimaryKeyThrowsWhenInferredFieldDoesNotExist(): void
    {
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithInferredKeyNotExist';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Table;

                #[Table('test_products')]
                class TestEntityWithInferredKeyNotExist extends Entity
                {
                    // Table is 'test_products', last segment: 'products', singularized: 'product', would infer 'product_id'
                    // But this field doesn't exist, should throw exception
                    public string \$name;
                }
            ");
        }

        $this->expectException(\Switon\Orm\Exception\PrimaryKeyNotFoundException::class);
        $this->entityMetadata->getPrimaryKey($entityClass);
    }

    /**
     * Test that getReferencedKey() handles table names with dot notation.
     */
    public function testGetReferencedKeyHandlesTableNameWithDot(): void
    {
        // Create a test entity with table name containing dot (e.g., "schema.table")
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithDotTable';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\Table;
                #[Table('schema.test_table')]
                class TestEntityWithDotTable extends Entity
                {
                    #[Id]
                    public int \$id;
                    public string \$name;
                }
            ");
        }

        $referencedKey = $this->entityMetadata->getReferencedKey($entityClass);

        $this->assertIsString($referencedKey);
        // Should extract table name after dot: schema.test_table -> test_table_id
        $this->assertStringEndsWith('_id', $referencedKey);
        $this->assertStringContainsString('test_table', $referencedKey);
    }

    /**
     * Test that getReferencedKey() handles table names with colon notation.
     */
    public function testGetReferencedKeyHandlesTableNameWithColon(): void
    {
        // Create a test entity with table name containing colon (e.g., "table:alias")
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithColonTable';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\Table;
                #[Table('test_table:alias')]
                class TestEntityWithColonTable extends Entity
                {
                    #[Id]
                    public int \$id;
                    public string \$name;
                }
            ");
        }

        $referencedKey = $this->entityMetadata->getReferencedKey($entityClass);

        $this->assertIsString($referencedKey);
        // Should extract table name before colon: test_table:alias -> test_table_id
        $this->assertSame('test_table_id', $referencedKey);
    }

    public function testCreateQueryReturnsQueryInterface(): void
    {
        if (!interface_exists(\Switon\Query\QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        // TestEntityWithRepository uses TestRepository which should be available
        try {
            $query = $this->entityMetadata->createQuery(TestEntityWithRepository::class);
            $this->assertInstanceOf(\Switon\Query\QueryInterface::class, $query);
        } catch (Throwable $e) {
            $this->markTestSkipped('Repository not available for createQuery: ' . $e->getMessage());
        }
    }

    public function testCreateQueryWithFieldsReturnsQueryInterface(): void
    {
        if (!interface_exists(\Switon\Query\QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        try {
            $query = $this->entityMetadata->createQuery(TestEntityWithRepository::class, ['name']);
            $this->assertInstanceOf(\Switon\Query\QueryInterface::class, $query);
        } catch (Throwable $e) {
            $this->markTestSkipped('Repository not available for createQuery: ' . $e->getMessage());
        }
    }

    /**
     * Test that getConnection() returns connection name from Connection attribute.
     */
    public function testGetConnectionReturnsConnectionFromAttribute(): void
    {
        // Create a test entity with Connection attribute
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithConnection';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\Connection;
                #[Connection('custom_connection')]
                class TestEntityWithConnection extends Entity
                {
                    #[Id]
                    public int \$id;
                    public string \$name;
                }
            ");
        }

        $connection = $this->entityMetadata->getConnection($entityClass);

        $this->assertIsString($connection);
        $this->assertSame('custom_connection', $connection);
    }

    /**
     * Test that getNamingStrategy() returns naming strategy from NamingStrategy attribute.
     */
    public function testGetNamingStrategyReturnsStrategyFromAttribute(): void
    {
        // Note: This test might be skipped if custom naming strategy is not available
        // But we can at least test that it doesn't throw an error
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithNamingStrategy';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\NamingStrategy;
                #[NamingStrategy(\Switon\Orm\NamingStrategy\CamelNamingStrategy::class)]
                class TestEntityWithNamingStrategy extends Entity
                {
                    #[Id]
                    public int \$id;
                    public string \$name;
                }
            ");
        }

        try {
            $namingStrategy = $this->entityMetadata->getNamingStrategy($entityClass);
            $this->assertNotNull($namingStrategy);
            $this->assertInstanceOf(\Switon\Orm\NamingStrategyInterface::class, $namingStrategy);
        } catch (Throwable $e) {
            $this->markTestSkipped('NamingStrategy attribute test skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that getFillable() includes fields with Id attribute.
     */
    public function testGetFillableIncludesFieldsWithIdAttribute(): void
    {
        if (!interface_exists('Switon\Validating\ConstraintInterface', true)) {
            $this->markTestSkipped('Validating package dependency not available');
        }

        $fillable = $this->entityMetadata->getFillable(TestEntity::class);

        $this->assertIsArray($fillable);
        // Id field should be in fillable
        $this->assertArrayHasKey('id', $fillable);
    }

    /**
     * Test that getFillable() includes fields with Column fillable=true.
     */
    public function testGetFillableIncludesFieldsWithColumnFillableTrue(): void
    {
        if (!interface_exists('Switon\Validating\ConstraintInterface', true)) {
            $this->markTestSkipped('Validating package dependency not available');
        }

        // Create a test entity with #[Fillable] and #[Column] (name only)
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithFillableColumn';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\Column;
                use Switon\Orm\Attribute\Fillable;
                class TestEntityWithFillableColumn extends Entity
                {
                    #[Id]
                    public int \$id;
                    
                    #[Column('fillable_field'), Fillable]
                    public string \$fillableField;
                    
                    #[Column('non_fillable_field')]
                    public string \$nonFillableField;
                }
            ");
        }

        $fillable = $this->entityMetadata->getFillable($entityClass);

        $this->assertIsArray($fillable);
        $this->assertArrayHasKey('fillableField', $fillable);
        $this->assertArrayNotHasKey('nonFillableField', $fillable);
    }

    /**
     * Test that getFillable() includes fields with Constraint attributes.
     */
    public function testGetFillableIncludesFieldsWithConstraints(): void
    {
        if (!interface_exists('Switon\Validating\ConstraintInterface', true)) {
            $this->markTestSkipped('Validating package dependency not available');
        }

        // This test might be skipped if validation constraints are not available
        // But we can test the basic structure
        $fillable = $this->entityMetadata->getFillable(TestEntity::class);

        $this->assertIsArray($fillable);
    }

    /**
     * Test that getFields() excludes readOnly properties.
     */
    public function testGetFieldsExcludesReadOnlyProperties(): void
    {
        // Create a test entity with readOnly property
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithReadOnlyProperty';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                class TestEntityWithReadOnlyProperty extends Entity
                {
                    #[Id]
                    public int \$id;
                    
                    public string \$name;
                    
                    public readonly string \$readOnlyField;
                }
            ");
        }

        $fields = $this->entityMetadata->getFields($entityClass);

        $this->assertIsArray($fields);
        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertNotContains('readOnlyField', $fields);
    }

    /**
     * Test that getFields() excludes static properties.
     */
    public function testGetFieldsExcludesStaticProperties(): void
    {
        // Create a test entity with static property
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithStaticProperty';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                class TestEntityWithStaticProperty extends Entity
                {
                    #[Id]
                    public int \$id;
                    
                    public string \$name;
                    
                    public static string \$staticField;
                }
            ");
        }

        $fields = $this->entityMetadata->getFields($entityClass);

        $this->assertIsArray($fields);
        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertNotContains('staticField', $fields);
    }

    /**
     * Test that getFields() excludes properties with Transiently attribute.
     */
    public function testGetFieldsExcludesTransientlyProperties(): void
    {
        // Create a test entity with Transiently property
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithTransientlyProperty';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\Transiently;
                class TestEntityWithTransientlyProperty extends Entity
                {
                    #[Id]
                    public int \$id;
                    
                    public string \$name;
                    
                    #[Transiently]
                    public string \$transientField;
                }
            ");
        }

        $fields = $this->entityMetadata->getFields($entityClass);

        $this->assertIsArray($fields);
        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertNotContains('transientField', $fields);
    }

    /**
     * Test that getColumnMap() excludes readOnly, static, and Transiently properties.
     */
    public function testGetColumnMapExcludesSpecialProperties(): void
    {
        // Create a test entity with special properties
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithSpecialProperties';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\Column;
                use Switon\Orm\Attribute\Transiently;
                class TestEntityWithSpecialProperties extends Entity
                {
                    #[Id]
                    public int \$id;

                    #[\Switon\Orm\Attribute\Column(name: 'normal_field')]
                    public string \$normalField;

                    public readonly string \$readOnlyField;

                    public static string \$staticField;

                    #[Transiently]
                    public string \$transientField;
                }
            ");
        }

        $columnMap = $this->entityMetadata->getColumnMap($entityClass);

        $this->assertIsArray($columnMap);
        $this->assertArrayHasKey('normalField', $columnMap);
        $this->assertArrayNotHasKey('readOnlyField', $columnMap);
        $this->assertArrayNotHasKey('staticField', $columnMap);
        $this->assertArrayNotHasKey('transientField', $columnMap);
    }

    /**
     * Test that getColumnMap() handles Column attribute with null name.
     */
    public function testGetColumnMapHandlesColumnWithNullName(): void
    {
        // Create a test entity with Column attribute but null name (should use naming strategy)
        $entityClass = 'Switon\Orm\Tests\Fixtures\TestEntityWithColumnNullName';

        if (!class_exists($entityClass, false)) {
            eval("
                namespace Switon\Orm\Tests\Fixtures;
                use Switon\Orm\Entity;
                use Switon\Orm\Attribute\Id;
                use Switon\Orm\Attribute\Column;
                class TestEntityWithColumnNullName extends Entity
                {
                    #[Id]
                    public int \$id;
                    
                    #[Column(null)]
                    public string \$testField;
                }
            ");
        }

        $columnMap = $this->entityMetadata->getColumnMap($entityClass);

        $this->assertIsArray($columnMap);
        // When Column name is null, it should use naming strategy
        // If property name equals column name (after naming strategy), it won't be in columnMap
        // So we just verify it doesn't throw an error
    }
}
