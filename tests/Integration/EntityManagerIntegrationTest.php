<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\ContainerInterface;
use Switon\Orm\EntityFillerInterface;
use Switon\Orm\EntityManager;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\ShardingInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestItemWithMappedPrimaryKey;
use Switon\Orm\Tests\Fixtures\TestOrderWithMappedPrimaryKey;
use Switon\Orm\Tests\Fixtures\TestProduct;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;
use Switon\Validating\ValidatorInterface;

/**
 * Integration tests for EntityManager class.
 *
 * Tests EntityManager functionality with mocked dependencies.
 * Integration tests allow EntityManager to work with QueryBuilder.
 */
#[AllowMockObjectsWithoutExpectations]
class EntityManagerIntegrationTest extends TestCase
{
    protected EntityManagerInterface $entityManager;
    protected MockObject|QueryBuilderInterface $mockQueryBuilder;
    protected MockObject|QueryInterface $mockQuery;
    protected MockObject|EntityFillerInterface $mockEntityFiller;
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|ShardingInterface $mockSharding;
    protected MockObject|RelationManagerInterface $mockRelationManager;
    protected MockObject|EventDispatcherInterface $mockEventDispatcher;
    protected MockObject|ValidatorInterface $mockValidator;
    protected MockObject|ContainerInterface $mockContainer;
    protected MockObject|\Switon\Di\NamedLookupInterface $mockNamedLookup;
    protected MockObject|\Switon\Db\ClientInterface $mockDbClient;
    protected MockObject|\Switon\Orm\IdGenerator $mockIdGeneratorManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Query package is not available
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        // Skip if Validating package is not available
        if (!interface_exists(ValidatorInterface::class, true)) {
            $this->markTestSkipped('Validating package dependency not available');
        }

        // Create mocks
        $this->mockQueryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);
        $this->mockEntityFiller = $this->createMock(EntityFillerInterface::class);
        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockSharding = $this->createMock(ShardingInterface::class);
        $this->mockRelationManager = $this->createMock(RelationManagerInterface::class);
        $this->mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mockValidator = $this->createMock(ValidatorInterface::class);
        $this->mockContainer = $this->createMock(ContainerInterface::class);
        $this->mockNamedLookup = $this->createMock(\Switon\Di\NamedLookupInterface::class);
        $this->mockDbClient = $this->createMock(\Switon\Db\ClientInterface::class);
        $this->mockIdGeneratorManager = $this->createMock(\Switon\Orm\IdGeneratorInterface::class);

        // Setup default mock behavior
        $this->mockQueryBuilder->method('create')
            ->willReturn($this->mockQuery);

        // Setup NamedLookup to return mock DbClient
        $this->mockNamedLookup->method('by')
            ->willReturn($this->mockDbClient);

        // Register all dependencies in container
        // Remove services if already resolved to avoid ServiceAlreadyResolvedException
        $services = [
            QueryBuilderInterface::class => $this->mockQueryBuilder,
            EntityFillerInterface::class => $this->mockEntityFiller,
            EntityMetadataInterface::class => $this->mockEntityMetadata,
            ShardingInterface::class => $this->mockSharding,
            RelationManagerInterface::class => $this->mockRelationManager,
            EventDispatcherInterface::class => $this->mockEventDispatcher,
            ValidatorInterface::class => $this->mockValidator,
            ContainerInterface::class => $this->mockContainer,
            \Switon\Di\NamedLookupInterface::class => $this->mockNamedLookup,
            \Switon\Orm\IdGeneratorInterface::class => $this->mockIdGeneratorManager,
        ];
        foreach ($services as $interface => $instance) {
            if ($this->container->has($interface)) {
                $this->container->remove($interface);
            }
            $this->container->set($interface, $instance);
        }

        // Create EntityManager instance using container (no reflection needed)
        // Container automatically handles #[Autowired] property injection
        $this->entityManager = $this->container->make(EntityManager::class);
    }

    /**
     * Test that EntityManager can be instantiated.
     */
    public function testEntityManagerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(EntityManagerInterface::class, $this->entityManager);
        $this->assertInstanceOf(EntityManager::class, $this->entityManager);
    }

    /**
     * Test that validate() validates entity fields using constraints.
     */
    public function testValidateValidatesEntityFields(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test Entity']);

        // Mock constraints
        $mockConstraint = $this->createMock(\Switon\Validating\ConstraintInterface::class);
        $this->mockEntityMetadata->method('getConstraints')
            ->with(TestEntity::class)
            ->willReturn(['name' => [$mockConstraint]]);

        // Mock validation context
        $mockValidation = $this->createMock(\Switon\Validating\Validation::class);
        $mockValidation->field = 'name';
        $mockValidation->value = 'Test Entity';

        $mockValidation->expects($this->once())
            ->method('validate')
            ->with($mockConstraint)
            ->willReturn(true);

        $mockValidation->expects($this->once())
            ->method('hasError')
            ->with('name')
            ->willReturn(false);

        $this->mockValidator->expects($this->once())
            ->method('beginValidate')
            ->with($entity)
            ->willReturn($mockValidation);

        $this->mockValidator->expects($this->once())
            ->method('endValidate')
            ->with($mockValidation);

        // validate() should not throw exception
        $this->callProtectedMethod($this->entityManager, 'validate', [$entity, ['name']]);
    }

    /**
     * Test that validate() skips fields without constraints.
     */
    public function testValidateSkipsFieldsWithoutConstraints(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test Entity']);

        // Mock no constraints for the field
        $this->mockEntityMetadata->method('getConstraints')
            ->with(TestEntity::class)
            ->willReturn([]);

        $mockValidation = $this->createMock(\Switon\Validating\Validation::class);
        $this->mockValidator->expects($this->once())
            ->method('beginValidate')
            ->with($entity)
            ->willReturn($mockValidation);

        $this->mockValidator->expects($this->once())
            ->method('endValidate')
            ->with($mockValidation);

        // validate() should not throw exception even with no constraints
        $this->callProtectedMethod($this->entityManager, 'validate', [$entity, ['name']]);
    }

    /**
     * Test that validate() updates entity field when validation passes.
     */
    public function testValidateUpdatesEntityFieldWhenValidationPasses(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Original Name']);

        $mockConstraint = $this->createMock(\Switon\Validating\ConstraintInterface::class);
        $this->mockEntityMetadata->method('getConstraints')
            ->with(TestEntity::class)
            ->willReturn(['name' => [$mockConstraint]]);

        $mockValidation = $this->createMock(\Switon\Validating\Validation::class);
        $mockValidation->field = 'name';
        $mockValidation->value = 'Updated Name';

        $mockValidation->expects($this->once())
            ->method('validate')
            ->with($mockConstraint)
            ->willReturn(true);

        $mockValidation->expects($this->once())
            ->method('hasError')
            ->with('name')
            ->willReturn(false);

        $this->mockValidator->expects($this->once())
            ->method('beginValidate')
            ->with($entity)
            ->willReturn($mockValidation);

        $this->mockValidator->expects($this->once())
            ->method('endValidate')
            ->with($mockValidation);

        $this->callProtectedMethod($this->entityManager, 'validate', [$entity, ['name']]);

        // Note: Since we're using a mock, the entity field won't actually be updated
        // The real implementation updates $entity->$field = $validation->value
        // This test verifies the flow, not the actual assignment (which requires a real Validation object)
    }

    /**
     * Test that dispatchEvent() calls entity's onEvent method and dispatches event.
     */
    public function testDispatchEventCallsEntityOnEventAndDispatchesEvent(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test Entity']);
        $event = $this->createMock(\Switon\Orm\Event\EntityEventInterface::class);

        $event->expects($this->atLeastOnce())
            ->method('getEntity')
            ->willReturn($entity);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event);

        // dispatchEvent should call entity->onEvent() and eventDispatcher->dispatch()
        $this->callProtectedMethod($this->entityManager, 'dispatchEvent', [$event]);
    }

    /**
     * Test related query() method returns QueryInterface instance.
     */
    public function testQueryMethodReturnsQueryInterface(): void
    {
        // Act
        $query = $this->entityManager->query(TestEntity::class);

        // Assert
        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    /**
     * Test related query() method with alias returns QueryInterface instance.
     */
    public function testQueryMethodWithAliasReturnsQueryInterface(): void
    {
        // Act
        $query = $this->entityManager->query(TestEntity::class, 't');

        // Assert
        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    /**
     * Test related query() uses QueryBuilder to create query.
     */
    public function testQueryUsesQueryBuilderToCreateQuery(): void
    {
        // Arrange
        $this->mockQueryBuilder->expects($this->once())
            ->method('create')
            ->with(TestEntity::class, null)
            ->willReturn($this->mockQuery);

        // Act
        $this->entityManager->query(TestEntity::class);
    }

    /**
     * Test related query() with alias uses QueryBuilder with alias.
     */
    public function testQueryWithAliasUsesQueryBuilderWithAlias(): void
    {
        // Arrange
        $alias = 't';
        $this->mockQueryBuilder->expects($this->once())
            ->method('create')
            ->with(TestEntity::class, $alias)
            ->willReturn($this->mockQuery);

        // Act
        $this->entityManager->query(TestEntity::class, $alias);
    }

    /**
     * Test that create() method creates entity with auto-generated primary key.
     */
    public function testCreateGeneratesPrimaryKeyWhenNotSet(): void
    {
        // Arrange
        $entity = new TestEntity(['name' => 'Test Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];
        $generatedId = 123;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(TestEntity::class)
            ->willReturn(['name' => 'string']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockIdGeneratorManager->expects($this->once())
            ->method('fillId')
            ->with($entity)
            ->willReturnCallback(function ($entity) use ($generatedId) {
                $entity->id = $generatedId;
            });
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['id' => $generatedId, 'name' => 'Test Entity'])
            ->willReturn($generatedId);

        // Act
        $result = $this->entityManager->create($entity);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame($generatedId, $entity->id);
    }

    /**
     * Test that create() method uses existing primary key when set.
     */
    public function testCreateUsesExistingPrimaryKey(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 456, 'name' => 'Test Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(TestEntity::class)
            ->willReturn(['name' => 'string']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['id' => 456, 'name' => 'Test Entity'], false)
            ->willReturn(1);

        // Act
        $result = $this->entityManager->create($entity);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame(456, $entity->id);
    }

    /**
     * Test that update() method throws exception when primary key is missing.
     */
    public function testUpdateThrowsExceptionWhenPrimaryKeyMissing(): void
    {
        // Arrange
        $entity = new TestEntity(['name' => 'Updated Entity']);
        $original = new TestEntity(['id' => 1, 'name' => 'Original Entity']);
        $primaryKey = 'id';

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);

        // Assert
        $this->expectException(\Switon\Orm\Exception\PrimaryKeyMissingException::class);

        // Act
        $this->entityManager->update($entity, $original);
    }

    /**
     * Test that update() method throws exception when primary key changes.
     */
    public function testUpdateThrowsExceptionWhenPrimaryKeyChanges(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 2, 'name' => 'Updated Entity']);
        $original = new TestEntity(['id' => 1, 'name' => 'Original Entity']);
        $primaryKey = 'id';

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);

        // Assert
        $this->expectException(\Switon\Orm\Exception\PrimaryKeyImmutableException::class);

        // Act
        $this->entityManager->update($entity, $original);
    }

    /**
     * Test that update() method returns entity unchanged when no fields changed.
     */
    public function testUpdateReturnsUnchangedEntityWhenNoFieldsChanged(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Same Name']);
        $original = new TestEntity(['id' => 1, 'name' => 'Same Name']);
        $primaryKey = 'id';
        $fields = ['id', 'name', 'status'];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);

        // Act
        $result = $this->entityManager->update($entity, $original);

        // Assert
        $this->assertSame($entity, $result);
        $this->mockDbClient->expects($this->never())->method('update');
    }

    /**
     * Test that delete() method throws exception when primary key is missing.
     */
    public function testDeleteThrowsExceptionWhenPrimaryKeyMissing(): void
    {
        // Arrange
        $entity = new TestEntity(['name' => 'Entity']);
        $primaryKey = 'id';

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);

        // Assert
        $this->expectException(\Switon\Orm\Exception\PrimaryKeyMissingException::class);

        // Act
        $this->entityManager->delete($entity);
    }

    /**
     * Test that delete() method deletes entity from database.
     */
    public function testDeleteRemovesEntityFromDatabase(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $columnMap = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('delete')
            ->with($table, [$primaryKey => 1])
            ->willReturn(1);

        // Act
        $result = $this->entityManager->delete($entity);

        // Assert
        $this->assertSame($entity, $result);
    }

    /**
     * Test that delete() method uses column map for primary key when mapping exists.
     */
    public function testDeleteUsesColumnMapForPrimaryKey(): void
    {
        // Arrange
        $entity = new TestItemWithMappedPrimaryKey(['id' => 123, 'name' => 'Test Item']);
        $primaryKey = 'id';
        $mappedColumn = 'item_id';
        $connection = 'default';
        $table = 'test_items';
        $columnMap = ['id' => 'item_id'];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestItemWithMappedPrimaryKey::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestItemWithMappedPrimaryKey::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestItemWithMappedPrimaryKey::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('delete')
            ->with($table, [$mappedColumn => 123])
            ->willReturn(1);

        // Act
        $result = $this->entityManager->delete($entity);

        // Assert
        $this->assertSame($entity, $result);
    }

    /**
     * Test that delete() method works without column map for primary key (backward compatibility).
     */
    public function testDeleteWorksWithoutColumnMapForPrimaryKey(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $columnMap = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('delete')
            ->with($table, [$primaryKey => 1])
            ->willReturn(1);

        // Act
        $result = $this->entityManager->delete($entity);

        // Assert
        $this->assertSame($entity, $result);
    }

    /**
     * Test that delete() method works when other fields have column map but primary key does not.
     */
    public function testDeleteWorksWhenOtherFieldsHaveColumnMapButPrimaryKeyDoesNot(): void
    {
        // Arrange
        $entity = new TestProduct(['product_id' => 456, 'name' => 'Product']);
        $primaryKey = 'product_id';
        $connection = 'default';
        $table = 'test_products';
        $columnMap = ['name' => 'product_name', 'price' => 'product_price'];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestProduct::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestProduct::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestProduct::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('delete')
            ->with($table, [$primaryKey => 456])
            ->willReturn(1);

        // Act
        $result = $this->entityManager->delete($entity);

        // Assert
        $this->assertSame($entity, $result);
    }

    /**
     * Test that put() method creates entity without validation.
     */
    public function testPutCreatesEntityWithoutValidation(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 789, 'name' => 'Test Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['id' => 789, 'name' => 'Test Entity'], false)
            ->willReturn(1);
        $this->mockValidator->expects($this->never())->method('beginValidate');

        // Act
        $result = $this->entityManager->put($entity);

        // Assert
        $this->assertSame($entity, $result);
    }

    /**
     * Test that create() method retrieves default values from database.
     */
    public function testCreateRetrievesDefaultValuesFromDatabase(): void
    {
        // Arrange
        $entity = new TestEntity(['name' => 'Test Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];
        $generatedId = 123;
        $defaultStatus = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(TestEntity::class)
            ->willReturn(['name' => 'string']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['name' => 'Test Entity'], true)
            ->willReturn($generatedId);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['status'])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with([$primaryKey => $generatedId])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('execute')
            ->willReturn([['status' => $defaultStatus]]);

        // Act
        $result = $this->entityManager->create($entity);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame($generatedId, $entity->id);
        $this->assertSame($defaultStatus, $entity->status);
    }

    /**
     * Test that create() method handles column mapping correctly.
     */
    public function testCreateHandlesColumnMapping(): void
    {
        // Arrange
        $entity = new \Switon\Orm\Tests\Fixtures\TestProduct(['name' => 'Test Product', 'price' => 99.99]);
        $primaryKey = 'product_id';
        $connection = 'default';
        $table = 'test_products';
        $fields = ['product_id', 'name', 'price', 'stock'];
        $columnMap = ['name' => 'product_name', 'price' => 'product_price'];
        $generatedId = 456;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn(['name' => 'string', 'price' => 'float']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['product_name' => 'Test Product', 'product_price' => 99.99], true)
            ->willReturn($generatedId);

        // Act
        $result = $this->entityManager->create($entity);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame($generatedId, $entity->product_id);
    }

    /**
     * Test that create() method retrieves default values when primary key has column mapping.
     */
    public function testCreateRetrievesDefaultValuesWithMappedPrimaryKey(): void
    {
        // Arrange
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-001']);
        $primaryKey = 'id';
        $mappedPrimaryKeyColumn = 'order_id';
        $connection = 'default';
        $table = 'test_orders';
        $fields = ['id', 'order_no', 'status'];
        $columnMap = ['id' => 'order_id'];
        $generatedId = 789;
        $defaultStatus = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn(['order_no' => 'string']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['order_no' => 'ORD-001'], true)
            ->willReturn($generatedId);

        $this->mockQueryBuilder->expects($this->once())
            ->method('create')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('setTable')
            ->with(\PHPUnit\Framework\Assert::callback(function ($table) use ($connection) {
                return $table instanceof \Switon\Query\Table
                    && $table->table === 'test_orders'
                    && $table->connection === $connection;
            }))
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setColumnMap')
            ->with($columnMap)
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['status'])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with([$mappedPrimaryKeyColumn => $generatedId])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('execute')
            ->willReturn([['status' => $defaultStatus]]);

        // Act
        $result = $this->entityManager->create($entity);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame($generatedId, $entity->id);
        $this->assertSame($defaultStatus, $entity->status);
    }

    /**
     * Test that create() method handles empty result when querying default values.
     */
    public function testCreateHandlesEmptyResultWhenQueryingDefaultValues(): void
    {
        // Arrange
        $entity = new TestEntity(['name' => 'Test Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];
        $generatedId = 123;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(TestEntity::class)
            ->willReturn(['name' => 'string']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['name' => 'Test Entity'], true)
            ->willReturn($generatedId);

        $this->mockQueryBuilder->expects($this->once())
            ->method('create')
            ->with(TestEntity::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('setTable')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('setColumnMap')
            ->with($columnMap)
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['status'])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('execute')
            ->willReturn([]); // Empty result

        // Act
        $result = $this->entityManager->create($entity);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame($generatedId, $entity->id);
        // status should remain unset since query returned empty
        $this->assertFalse(isset($entity->status));
    }

    /**
     * Test that update() method handles original entity with unset fields.
     */
    public function testUpdateHandlesOriginalEntityWithUnsetFields(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Updated Name']);
        $original = new TestEntity(['id' => 1, 'name' => 'Original Name']);
        // status is not set in original entity
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('update')
            ->with($table, ['name' => 'Updated Name'], [$primaryKey => 1])
            ->willReturn(1);

        // Act
        $result = $this->entityManager->update($entity, $original);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame('Updated Name', $entity->name);
        // status should remain unset (uninitialized) since it was not set in original
        $this->assertFalse(isset($entity->status));
    }

    /**
     * Test that update() method updates changed fields correctly.
     */
    public function testUpdateUpdatesChangedFields(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Updated Name', 'status' => 2]);
        $original = new TestEntity(['id' => 1, 'name' => 'Original Name', 'status' => 1]);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(TestEntity::class)
            ->willReturn(['name' => 'string', 'status' => 'int']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('update')
            ->with($table, ['name' => 'Updated Name', 'status' => 2], [$primaryKey => 1])
            ->willReturn(1);

        // Act
        $result = $this->entityManager->update($entity, $original);

        // Assert
        $this->assertSame($entity, $result);
    }

    /**
     * Test that update() method handles column mapping correctly.
     */
    public function testUpdateHandlesColumnMapping(): void
    {
        // Arrange
        $entity = new \Switon\Orm\Tests\Fixtures\TestProduct(['product_id' => 1, 'name' => 'Updated Product', 'price' => 199.99]);
        $original = new \Switon\Orm\Tests\Fixtures\TestProduct(['product_id' => 1, 'name' => 'Original Product', 'price' => 99.99, 'stock' => 100]);
        $primaryKey = 'product_id';
        $connection = 'default';
        $table = 'test_products';
        $fields = ['product_id', 'name', 'price', 'stock'];
        $columnMap = ['name' => 'product_name', 'price' => 'product_price', 'product_id' => 'product_id'];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getFillable')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn(['name' => 'string', 'price' => 'float']);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(\Switon\Orm\Tests\Fixtures\TestProduct::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('update')
            ->with($table, ['product_name' => 'Updated Product', 'product_price' => 199.99], ['product_id' => 1])
            ->willReturn(1);

        // Act
        $result = $this->entityManager->update($entity, $original);

        // Assert
        $this->assertSame($entity, $result);
    }

    /**
     * Test that put() method generates primary key when not set.
     */
    public function testPutGeneratesPrimaryKeyWhenNotSet(): void
    {
        // Arrange
        $entity = new TestEntity(['name' => 'Test Entity']);
        $primaryKey = 'id';
        $connection = 'default';
        $table = 'test_entities';
        $fields = ['id', 'name', 'status'];
        $columnMap = [];
        $generatedId = 999;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn($primaryKey);
        $this->mockEntityMetadata->method('getFields')
            ->with(TestEntity::class)
            ->willReturn($fields);
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestEntity::class)
            ->willReturn($columnMap);
        $this->mockSharding->method('getUniqueShard')
            ->with(TestEntity::class, $entity)
            ->willReturn([$connection, $table]);
        $this->mockDbClient->expects($this->once())
            ->method('insert')
            ->with($table, ['name' => 'Test Entity'], true)
            ->willReturn($generatedId);

        // Act
        $result = $this->entityManager->put($entity);

        // Assert
        $this->assertSame($entity, $result);
        $this->assertSame($generatedId, $entity->id);
    }

    private function callProtectedMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $args);
    }
}


