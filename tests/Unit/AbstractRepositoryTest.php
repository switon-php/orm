<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\Exception\RuntimeException;
use Switon\Orm\EntityHydrator;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\Fixtures\Entity\InferredSample;
use Switon\Orm\Tests\Fixtures\Repository\InferredSampleRepository;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestRepository;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class AbstractRepositoryTest extends TestCase
{
    protected TestRepository $repository;
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

        $this->repository = new TestRepository(
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator
        );
    }

    /**
     * Test that repository correctly infers entity class from naming convention.
     *
     * Verified through public API: all() returns entities of the correct type.
     */
    public function testRepositoryReturnsCorrectEntityType(): void
    {
        $rows = [['id' => 1, 'name' => 'Entity 1']];

        $this->mockQuery->method('fetch')
            ->willReturn($rows);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $result = $this->repository->all();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TestEntity::class, $result[0]);
    }

    public function testAllReturnsEmptyArrayWhenNoRows(): void
    {
        $this->mockQuery->method('fetch')
            ->willReturn([]);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $result = $this->repository->all();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAllConvertsRowsToEntities(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
            ['id' => 2, 'name' => 'Entity 2'],
        ];

        $this->mockQuery->method('fetch')
            ->willReturn($rows);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $result = $this->repository->all();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestEntity::class, $result[0]);
        $this->assertInstanceOf(TestEntity::class, $result[1]);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame('Entity 1', $result[0]->name);
        $this->assertSame(2, $result[1]->id);
        $this->assertSame('Entity 2', $result[1]->name);
    }

    public function testAllAppliesFilters(): void
    {
        $filters = ['status' => 1];
        $this->mockQuery->method('fetch')
            ->willReturn([]);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($filters)
            ->willReturnSelf();

        $this->repository->all($filters);
    }

    public function testAllAppliesOrders(): void
    {
        $orders = ['name' => 'ASC'];
        $this->mockQuery->method('fetch')
            ->willReturn([]);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with($orders)
            ->willReturnSelf();

        $this->repository->all([], [], $orders);
    }

    public function testAllByReturnsEntitiesKeyedByField(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
            ['id' => 2, 'name' => 'Entity 2'],
        ];

        $this->mockQuery->method('fetch')
            ->willReturn($rows);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $result = $this->repository->allBy([], 'id');

        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertInstanceOf(TestEntity::class, $result[1]);
        $this->assertInstanceOf(TestEntity::class, $result[2]);
        $this->assertSame(1, $result[1]->id);
        $this->assertSame(2, $result[2]->id);
    }

    public function testFindReturnsEntityById(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];

        $this->mockQuery->method('fetch')
            ->willReturn($row);
        $this->mockQuery->method('with')
            ->willReturnSelf();
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($filters) {
                return isset($filters['id']) && $filters['id'] === 1;
            }))
            ->willReturnSelf();

        $result = $this->repository->find(1);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('Entity 1', $result->name);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->mockQuery->method('fetch')
            ->willReturn([]);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $result = $this->repository->find(999);

        $this->assertNull($result);
    }

    public function testAllBySkipsEntitiesWithMissingKeyField(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
            ['name' => 'Entity 2'], // Missing 'id'
        ];

        $this->mockQuery->method('fetch')
            ->willReturn($rows);
        $this->mockQuery->method('with')
            ->willReturnSelf();

        $result = $this->repository->allBy([], 'id');

        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TestEntity::class, $result[1]);
        $this->assertSame(1, $result[1]->id);
    }

    /**
     * Test that repository throws exception when naming pattern doesn't match.
     *
     * InvalidTestRepository doesn't follow the naming convention and will throw
     * RuntimeException during construction.
     */
    public function testInferEntityClassThrowsExceptionWhenPatternDoesNotMatch(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot infer entity');

        // This should throw during construction because InvalidTestRepository
        // doesn't set entityClass and doesn't match the naming pattern
        new \Switon\Orm\Tests\Fixtures\InvalidTestRepository(
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->make(\Switon\Orm\EntityHydrator::class)
        );
    }

    public function testGetEntityClassReturnsConfiguredEntityClass(): void
    {
        $repository = new class (
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator
        ) extends TestRepository {
            public function exposeEntityClass(): string
            {
                return $this->getEntityClass();
            }
        };

        $this->assertSame(TestEntity::class, $repository->exposeEntityClass());
    }

    public function testInferEntityClassReturnsEntityClassByNamingConvention(): void
    {
        $repository = new InferredSampleRepository();

        $this->assertSame(InferredSample::class, $repository->exposeEntityClass());
    }

    public function testSelectRawUsesMetadataFieldsWhenFieldListIsEmpty(): void
    {
        $this->mockEntityMetadata->expects($this->once())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name', 'status']);

        $this->mockQueryBuilder->expects($this->once())
            ->method('create')
            ->with(TestEntity::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name', 'status'])
            ->willReturnSelf();

        $repository = new class (
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator,
        ) extends TestRepository {
            public function probeSelectRaw(array $fields = []): QueryInterface
            {
                return $this->selectRaw($fields);
            }
        };

        $repository->probeSelectRaw([]);
    }

    public function testSelectRawPassesExplicitFieldsWithoutCallingGetFields(): void
    {
        $this->mockEntityMetadata->expects($this->never())
            ->method('getFields');

        $this->mockQueryBuilder->expects($this->once())
            ->method('create')
            ->with(TestEntity::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();

        $repository = new class (
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator,
        ) extends TestRepository {
            public function probeSelectRaw(array $fields = []): QueryInterface
            {
                return $this->selectRaw($fields);
            }
        };

        $repository->probeSelectRaw(['id', 'name']);
    }
}
