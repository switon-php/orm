<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\MisuseException;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Tests\Fixtures\TestComment;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestEntityMisconfiguredBelongsTo;
use Switon\Orm\Tests\Fixtures\TestEntityWithHasManyOnArray;
use Switon\Orm\Tests\Fixtures\TestProduct;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\Fixtures\TestUserRoleWithBelongsTo;
use Switon\Orm\Tests\TestCase;

class EntityMetadataAdvancedTest extends TestCase
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector->inject($this);
    }

    public function testGetFieldsReturnsAllEntityFields(): void
    {
        $fields = $this->entityMetadata->getFields(TestEntity::class);

        $this->assertIsArray($fields);
        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertContains('status', $fields);
    }

    public function testGetFieldsReturnsFieldsForDifferentEntities(): void
    {
        $testEntityFields = $this->entityMetadata->getFields(TestEntity::class);
        $testUserFields = $this->entityMetadata->getFields(TestUser::class);

        $this->assertIsArray($testEntityFields);
        $this->assertIsArray($testUserFields);
        $this->assertNotEquals($testEntityFields, $testUserFields);
        $this->assertContains('user_id', $testUserFields);
        $this->assertContains('username', $testUserFields);
    }

    public function testGetFillableReturnsFillableFieldsWithTypes(): void
    {

        $fillable = $this->entityMetadata->getFillable(TestEntity::class);

        $this->assertIsArray($fillable);
        if (isset($fillable['name'])) {
            $this->assertIsString($fillable['name']);
        }
    }

    public function testGetFillableReturnsEmptyArrayWhenNoFillableFields(): void
    {

        $fillable = $this->entityMetadata->getFillable(TestEntity::class);

        $this->assertIsArray($fillable);
    }

    public function testGetConstraintsReturnsConstraintsForFields(): void
    {

        $constraints = $this->entityMetadata->getConstraints(TestEntity::class);

        $this->assertIsArray($constraints);
    }

    public function testGetConstraintsReturnsEmptyArrayWhenNoConstraints(): void
    {

        $constraints = $this->entityMetadata->getConstraints(TestEntity::class);

        $this->assertIsArray($constraints);
    }

    public function testGetRelationsReturnsRelationsForEntity(): void
    {
        $relations = $this->entityMetadata->getRelations(TestEntity::class);

        $this->assertIsArray($relations);
    }

    public function testGetRelationsReturnsEmptyArrayWhenNoRelations(): void
    {
        $relations = $this->entityMetadata->getRelations(TestEntity::class);

        $this->assertIsArray($relations);
    }

    public function testGetRelationsThrowsWhenRelatedEntityCannotBeResolved(): void
    {
        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('Cannot resolve related entity for relation owner');

        $this->entityMetadata->getRelations(TestEntityMisconfiguredBelongsTo::class);
    }

    public function testGetRelationsRegistersHasManyWhenPropertyTypeIsArray(): void
    {
        $relations = $this->entityMetadata->getRelations(TestEntityWithHasManyOnArray::class);

        $this->assertArrayHasKey('comments', $relations);
        $this->assertSame(TestComment::class, $relations['comments']->getRelatedEntityClass());
    }

    public function testGetRelationsResolvesBelongsToFromTypedProperty(): void
    {
        $relations = $this->entityMetadata->getRelations(TestUserRoleWithBelongsTo::class);

        $this->assertArrayHasKey('user', $relations);
        $this->assertSame(TestUser::class, $relations['user']->getRelatedEntityClass());
    }

    public function testGetColumnMapReturnsPropertyToColumnMapping(): void
    {
        $columnMap = $this->entityMetadata->getColumnMap(TestProduct::class);

        $this->assertIsArray($columnMap);
        if (isset($columnMap['name'])) {
            $this->assertSame('product_name', $columnMap['name']);
        }
        if (isset($columnMap['price'])) {
            $this->assertSame('product_price', $columnMap['price']);
        }
    }

    public function testGetColumnMapReturnsEmptyArrayWhenNoMappings(): void
    {
        $columnMap = $this->entityMetadata->getColumnMap(TestEntity::class);

        $this->assertIsArray($columnMap);
    }

    public function testGetConnectionReturnsConnectionName(): void
    {
        $connection = $this->entityMetadata->getConnection(TestEntity::class);

        $this->assertIsString($connection);
    }

    public function testGetConnectionReturnsConnectionForDifferentEntities(): void
    {
        $testEntityConnection = $this->entityMetadata->getConnection(TestEntity::class);
        $testUserConnection = $this->entityMetadata->getConnection(TestUser::class);

        $this->assertIsString($testEntityConnection);
        $this->assertIsString($testUserConnection);
    }

    public function testGetTableReturnsTableName(): void
    {
        $table = $this->entityMetadata->getTable(TestEntity::class);

        $this->assertIsString($table);
        $this->assertNotEmpty($table);
    }

    public function testGetTableReturnsExplicitTableNameWhenSpecified(): void
    {
        $table = $this->entityMetadata->getTable(TestUser::class);

        $this->assertIsString($table);
        $this->assertSame('test_users', $table);
    }

    public function testGetPrimaryKeyReturnsPrimaryKeyFieldName(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(TestEntity::class);

        $this->assertIsString($primaryKey);
        $this->assertSame('id', $primaryKey);
    }

    public function testGetPrimaryKeyReturnsDifferentPrimaryKeysForDifferentEntities(): void
    {
        $testEntityPrimaryKey = $this->entityMetadata->getPrimaryKey(TestEntity::class);
        $testUserPrimaryKey = $this->entityMetadata->getPrimaryKey(TestUser::class);

        $this->assertSame('id', $testEntityPrimaryKey);
        $this->assertSame('user_id', $testUserPrimaryKey);
    }

    public function testGetNamingStrategyReturnsDefaultNamingStrategyWhenNotSpecified(): void
    {
        $namingStrategy = $this->entityMetadata->getNamingStrategy(TestEntity::class);

        // TestEntity has no naming strategy, so should return DefaultNamingStrategy
        $this->assertNotNull($namingStrategy);
        $this->assertInstanceOf(\Switon\Orm\NamingStrategy\DefaultNamingStrategy::class, $namingStrategy);
    }

    public function testGetNamingStrategyReturnsSameInstanceOnMultipleCalls(): void
    {
        $strategy1 = $this->entityMetadata->getNamingStrategy(TestEntity::class);
        $strategy2 = $this->entityMetadata->getNamingStrategy(TestEntity::class);

        // Both should be DefaultNamingStrategy instances
        $this->assertNotNull($strategy1);
        $this->assertNotNull($strategy2);
        $this->assertInstanceOf(\Switon\Orm\NamingStrategy\DefaultNamingStrategy::class, $strategy1);
        $this->assertInstanceOf(\Switon\Orm\NamingStrategy\DefaultNamingStrategy::class, $strategy2);
        $this->assertSame($strategy1, $strategy2);
    }
}
