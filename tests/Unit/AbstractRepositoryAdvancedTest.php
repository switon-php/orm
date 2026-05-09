<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\EntityHydrator;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\EntityNotFoundException;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestRepositoryAdvanced;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

/**
 * Test repository subclass that exposes protected methods for testing.
 */
class TestableAdvancedRepository extends TestRepositoryAdvanced
{
    /**
     * Expose protected where method for testing.
     */
    public function testWhere(array $filters): void
    {
        $this->where($filters);
    }
}

#[AllowMockObjectsWithoutExpectations]
class AbstractRepositoryAdvancedTest extends TestCase
{
    protected TestableAdvancedRepository $repository;
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|RelationManagerInterface $mockRelationManager;
    protected MockObject|EntityManagerInterface $mockEntityManager;
    protected MockObject|QueryBuilderInterface $mockQueryBuilder;
    protected MockObject|QueryInterface $mockQuery;
    protected EntityHydratorInterface $entityHydrator;

    protected function setUp(): void
    {
        parent::setUp();


        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockRelationManager = $this->createMock(RelationManagerInterface::class);
        $this->mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockQueryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getConnection')
            ->willReturn('default');
        $this->mockEntityMetadata->method('getTable')
            ->willReturn('test_entities');
        $this->mockEntityMetadata->method('getColumnMap')
            ->willReturn([]);
        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('id');
        $this->mockEntityMetadata->method('getFieldType')
            ->willReturnCallback(static function (string $entityClass, string $field): string {
                return match ($field) {
                    'id' => 'int',
                    'name' => 'string',
                    'status' => 'int',
                    'price' => 'float',
                    'active', 'disabled', 'enabled', 'locked', 'active1', 'active2', 'active3', 'active4', 'active5', 'active6' => 'bool',
                    default => '',
                };
            });

        $this->container->set(EntityMetadataInterface::class, $this->mockEntityMetadata);
        $this->entityHydrator = $this->make(EntityHydrator::class);

        $this->mockQueryBuilder->method('create')
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('setTable')
            ->willReturnSelf();
        $this->mockQuery->method('setColumnMap')
            ->willReturnSelf();
        $this->mockQuery->method('select')
            ->willReturnSelf();
        $this->mockQuery->method('where')
            ->willReturnSelf();
        $this->mockQuery->method('orderBy')
            ->willReturnSelf();
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $this->repository = new TestableAdvancedRepository(
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator
        );
    }

    public function testFirstReturnsFirstEntityMatchingFilters(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $filters = ['status' => 1];
        $fields = [];

        $this->mockQuery->method('fetch')
            ->willReturn($row);

        $result = $this->repository->first($filters, $fields);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('Entity 1', $result->name);
    }

    public function testFirstReturnsNullWhenNoMatch(): void
    {
        $filters = ['status' => 999];
        $fields = [];

        $this->mockQuery->method('fetch')
            ->willReturn(null);

        $result = $this->repository->first($filters, $fields);

        $this->assertNull($result);
    }

    public function testFirstOrFailReturnsEntityWhenFound(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $filters = ['status' => 1];
        $fields = [];

        $this->mockQuery->method('fetch')
            ->willReturn($row);

        $result = $this->repository->firstOrFail($filters, $fields);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testFirstOrFailThrowsExceptionWhenNotFound(): void
    {
        $filters = ['status' => 999];
        $fields = [];

        $this->mockQuery->method('fetch')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->repository->firstOrFail($filters, $fields);
    }

    public function testValueReturnsFieldValue(): void
    {
        $rows = [['name' => 'Test Name']];
        $filters = ['id' => 1];
        $field = 'name';

        $this->mockQuery->method('execute')
            ->willReturn($rows);

        $result = $this->repository->value($filters, $field);

        $this->assertSame('Test Name', $result);
    }

    public function testValueOrFailReturnsFieldValueWhenFound(): void
    {
        $rows = [['name' => 'Test Name']];
        $filters = ['id' => 1];
        $field = 'name';

        $this->mockQuery->method('execute')
            ->willReturn($rows);

        $result = $this->repository->valueOrFail($filters, $field);

        $this->assertSame('Test Name', $result);
    }

    public function testValueOrFailThrowsExceptionWhenNotFound(): void
    {
        $filters = ['id' => 999];
        $field = 'name';

        $this->mockQuery->method('execute')
            ->willReturn([]);

        $this->expectException(EntityNotFoundException::class);
        $this->repository->valueOrFail($filters, $field);
    }

    public function testValueOrDefaultReturnsFieldValueWhenFound(): void
    {
        $rows = [['name' => 'Test Name']];
        $filters = ['id' => 1];
        $field = 'name';
        $default = 'Default Name';

        $this->mockQuery->method('execute')
            ->willReturn($rows);

        $result = $this->repository->valueOrDefault($filters, $field, $default);

        $this->assertSame('Test Name', $result);
    }

    public function testValueOrDefaultReturnsDefaultWhenNotFound(): void
    {
        $filters = ['id' => 999];
        $field = 'name';
        $default = 'Default Name';

        $this->mockQuery->method('execute')
            ->willReturn([]);

        $result = $this->repository->valueOrDefault($filters, $field, $default);

        $this->assertSame($default, $result);
    }

    public function testValuesReturnsArrayOfFieldValues(): void
    {
        $filters = ['status' => 1];
        $field = 'name';

        $this->mockQuery->method('values')
            ->willReturn(['Name 1', 'Name 2', 'Name 3']);

        $result = $this->repository->values($filters, $field);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame('Name 1', $result[0]);
        $this->assertSame('Name 2', $result[1]);
        $this->assertSame('Name 3', $result[2]);
    }

    public function testPluckReturnsKeyValuePairs(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Name 1'],
            ['id' => 2, 'name' => 'Name 2'],
            ['id' => 3, 'name' => 'Name 3'],
        ];
        $filters = ['status' => 1];
        $keyField = 'id';
        $valueField = 'name';

        $this->mockQuery->method('execute')
            ->willReturn($rows);

        $result = $this->repository->pluck($filters, $valueField, $keyField);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame('Name 1', $result[1]);
        $this->assertSame('Name 2', $result[2]);
        $this->assertSame('Name 3', $result[3]);
    }

    public function testCountReturnsCountOfMatchingEntities(): void
    {
        $filters = ['status' => 1];

        $this->mockQuery->method('count')
            ->willReturn(5);

        $result = $this->repository->count($filters);

        $this->assertSame(5, $result);
    }

    public function testExistsReturnsTrueWhenEntityExists(): void
    {
        $filters = ['id' => 1];

        $this->mockQuery->method('exists')
            ->willReturn(true);

        $result = $this->repository->exists($filters);

        $this->assertTrue($result);
    }

    public function testExistsReturnsFalseWhenEntityDoesNotExist(): void
    {
        $filters = ['id' => 999];

        $this->mockQuery->method('exists')
            ->willReturn(false);

        $result = $this->repository->exists($filters);

        $this->assertFalse($result);
    }

    public function testExistsByIdReturnsTrueWhenEntityExists(): void
    {
        $id = 1;

        $this->mockQuery->method('exists')
            ->willReturn(true);

        $result = $this->repository->existsById($id);

        $this->assertTrue($result);
    }

    public function testExistsByIdReturnsFalseWhenEntityDoesNotExist(): void
    {
        $id = 999;

        $this->mockQuery->method('exists')
            ->willReturn(false);

        $result = $this->repository->existsById($id);

        $this->assertFalse($result);
    }

    public function testPluckReturnsValueArrayWhenKeyFieldIsNull(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Name 1'],
            ['id' => 2, 'name' => 'Name 2'],
            ['id' => 3, 'name' => 'Name 3'],
        ];
        $filters = ['status' => 1];
        $valueField = 'name';

        $this->mockQuery->method('execute')
            ->willReturn($rows);

        $result = $this->repository->pluck($filters, $valueField);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        // When keyField is null, should use primary key
        $this->assertSame('Name 1', $result[1]);
        $this->assertSame('Name 2', $result[2]);
        $this->assertSame('Name 3', $result[3]);
    }

    public function testPutCreatesEntityFromArray(): void
    {
        $data = ['name' => 'New Entity'];
        $createdEntity = new TestEntity(['id' => 1, 'name' => 'New Entity']);

        $this->mockEntityManager->expects($this->once())
            ->method('put')
            ->with($this->callback(function ($arg) {
                return $arg instanceof TestEntity && $arg->name === 'New Entity';
            }))
            ->willReturn($createdEntity);

        $result = $this->repository->put($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('New Entity', $result->name);
    }

    public function testPutUsesEntityDirectlyWhenEntityPassed(): void
    {
        $entity = new TestEntity(['name' => 'Existing Entity']);
        $createdEntity = new TestEntity(['id' => 1, 'name' => 'Existing Entity']);

        $this->mockEntityManager->expects($this->once())
            ->method('put')
            ->with($entity)
            ->willReturn($createdEntity);

        $result = $this->repository->put($entity);

        $this->assertSame($createdEntity, $result);
    }

    public function testFillCreatesEntityWithFillableFields(): void
    {
        $data = ['name' => 'Test Name', 'status' => 1, 'nonFillable' => 'Should be ignored'];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string', 'status' => 'int']);

        $result = $this->repository->fill($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame('Test Name', $result->name);
        $this->assertSame(1, $result->status);
        // Non-fillable fields should not be set
        $this->assertFalse(property_exists($result, 'nonFillable'));
    }

    public function testFillConvertsTypesCorrectly(): void
    {
        $data = [
            'name' => 123,        // Should convert to string
            'status' => '456',    // Should convert to int
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string', 'status' => 'int']);

        $result = $this->repository->fill($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame('123', $result->name);
        $this->assertSame(456, $result->status);
    }

    public function testFillConvertsBoolCorrectly(): void
    {
        $data = [
            'active' => 'true',
            'disabled' => 'false',
            'enabled' => '1',
            'locked' => '0',
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['active' => 'bool', 'disabled' => 'bool', 'enabled' => 'bool', 'locked' => 'bool']);

        $result = $this->repository->fill($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertTrue($result->active);
        $this->assertFalse($result->disabled);
        $this->assertTrue($result->enabled);
        $this->assertFalse($result->locked);
    }

    public function testUpdateAllUpdatesEntitiesMatchingFilters(): void
    {
        $filters = ['status' => 1];
        $data = ['name' => 'Updated Name'];

        $this->mockQuery->expects($this->once())
            ->method('update')
            ->with($data)
            ->willReturn(3);

        $result = $this->repository->updateAll($filters, $data);

        $this->assertSame(3, $result);
    }

    public function testDeleteAllDeletesEntitiesMatchingFilters(): void
    {
        $filters = ['status' => 0];

        $this->mockQuery->expects($this->once())
            ->method('delete')
            ->willReturn(5);

        $result = $this->repository->deleteAll($filters);

        $this->assertSame(5, $result);
    }

    public function testIncrementByIdIncrementsCounters(): void
    {
        $id = 1;
        $counters = ['views' => 1, 'likes' => 5];

        $this->mockQuery->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) {
                return isset($data['views'])
                    && isset($data['likes'])
                    && $data['views'] instanceof \Switon\Db\Fragment\Increment
                    && $data['likes'] instanceof \Switon\Db\Fragment\Increment;
            }))
            ->willReturn(1);

        $result = $this->repository->incrementById($id, $counters);

        $this->assertSame(1, $result);
    }

    public function testWhereSkipsDateRangeFilterWhenArrayIndicesMissing(): void
    {
        // Arrange - associative array, not indexed [0], [1]
        $filters = [
            'created_at@=' => ['min' => '2024-01-01', 'max' => '2024-12-31'],
        ];

        $this->mockQuery->method('select')
            ->willReturnSelf();

        // Expect - should not convert date range because array indices [0] and [1] don't exist
        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($filters) {
                return isset($filters['created_at@=']);
            }))
            ->willReturnSelf();

        // Act - use testable repository's exposed method
        $this->repository->testWhere($filters);
    }
}
