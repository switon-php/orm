<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\EntityHydrator;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestRepository;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class AbstractRepositoryBatchTest extends TestCase
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

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('id');
        $this->mockEntityMetadata->method('getFillable')->willReturn(['name' => 'string', 'status' => 'int']);
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

        $this->repository = new TestRepository(
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator
        );
    }

    public function testCreateManyCreatesMultipleEntities(): void
    {
        $data = [
            ['name' => 'Entity 1'],
            ['name' => 'Entity 2'],
            ['name' => 'Entity 3'],
        ];

        $createdEntities = [
            new TestEntity(['id' => 1, 'name' => 'Entity 1']),
            new TestEntity(['id' => 2, 'name' => 'Entity 2']),
            new TestEntity(['id' => 3, 'name' => 'Entity 3']),
        ];

        $this->mockEntityManager->expects($this->once())
            ->method('createMany')
            ->with($this->callback(function ($entities) {
                return count($entities) === 3
                    && $entities[0] instanceof TestEntity
                    && $entities[1] instanceof TestEntity
                    && $entities[2] instanceof TestEntity;
            }))
            ->willReturn($createdEntities);

        $result = $this->repository->createMany($data);

        $this->assertCount(3, $result);
        $this->assertInstanceOf(TestEntity::class, $result[0]);
        $this->assertInstanceOf(TestEntity::class, $result[1]);
        $this->assertInstanceOf(TestEntity::class, $result[2]);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame(2, $result[1]->id);
        $this->assertSame(3, $result[2]->id);
    }

    public function testCreateManyReturnsEmptyArrayWhenInputEmpty(): void
    {
        $result = $this->repository->createMany([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCreateManyRemovesPrimaryKeyFromArrayData(): void
    {
        $data = [
            ['id' => 999, 'name' => 'Entity 1'], // ID should be removed for security
            ['id' => 888, 'name' => 'Entity 2'],
        ];

        $createdEntities = [
            new TestEntity(['id' => 1, 'name' => 'Entity 1']),
            new TestEntity(['id' => 2, 'name' => 'Entity 2']),
        ];

        $this->mockEntityManager->expects($this->once())
            ->method('createMany')
            ->with($this->callback(function ($entities) {
                // Verify that primary keys were removed
                return !isset($entities[0]->id) && !isset($entities[1]->id);
            }))
            ->willReturn($createdEntities);

        $result = $this->repository->createMany($data);

        $this->assertCount(2, $result);
        // Returned entities should have auto-generated IDs
        $this->assertSame(1, $result[0]->id);
        $this->assertSame(2, $result[1]->id);
    }

    public function testCreateManyHandlesMixedArrayAndEntityInput(): void
    {
        $entity1 = new TestEntity(['name' => 'Entity 1']);
        $data = [
            $entity1,
            ['name' => 'Entity 2'],
            new TestEntity(['name' => 'Entity 3']),
        ];

        $createdEntities = [
            new TestEntity(['id' => 1, 'name' => 'Entity 1']),
            new TestEntity(['id' => 2, 'name' => 'Entity 2']),
            new TestEntity(['id' => 3, 'name' => 'Entity 3']),
        ];

        $this->mockEntityManager->expects($this->once())
            ->method('createMany')
            ->willReturn($createdEntities);

        $result = $this->repository->createMany($data);

        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame(2, $result[1]->id);
        $this->assertSame(3, $result[2]->id);
    }

    public function testCreateManyWithLargeDataset(): void
    {
        // Test with 100 entities
        $data = [];
        for ($i = 1; $i <= 100; $i++) {
            $data[] = ['name' => "Entity $i"];
        }

        $createdEntities = [];
        for ($i = 1; $i <= 100; $i++) {
            $createdEntities[] = new TestEntity(['id' => $i, 'name' => "Entity $i"]);
        }

        $this->mockEntityManager->expects($this->once())
            ->method('createMany')
            ->willReturn($createdEntities);

        $result = $this->repository->createMany($data);

        $this->assertCount(100, $result);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame(100, $result[99]->id);
    }

    public function testDeleteByIdReturnsNullWhenEntityNotFound(): void
    {
        $this->mockQueryBuilder->method('create')->willReturn($this->mockQuery);
        $this->mockQuery->method('setTable')->willReturnSelf();
        $this->mockQuery->method('setColumnMap')->willReturnSelf();
        $this->mockQuery->method('select')->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn(null);

        $result = $this->repository->deleteById(999);

        $this->assertNull($result);
    }

    public function testDeleteByIdReturnsDeletedEntity(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Entity']);

        $this->mockQueryBuilder->method('create')->willReturn($this->mockQuery);
        $this->mockQuery->method('setTable')->willReturnSelf();
        $this->mockQuery->method('setColumnMap')->willReturnSelf();
        $this->mockQuery->method('select')->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn(['id' => 1, 'name' => 'Entity']);

        $this->mockEntityManager->expects($this->once())
            ->method('delete')
            ->willReturn($entity);

        $result = $this->repository->deleteById(1);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
    }
}
