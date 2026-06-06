<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\EntityHydrator;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Page;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestRepository;
use Switon\Orm\Tests\TestCase;
use Switon\Query\Paginator;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class AbstractRepositoryPaginationTest extends TestCase
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

        $this->mockEntityMetadata->method('getConnection')->willReturn('default');
        $this->mockEntityMetadata->method('getTable')->willReturn('test_entities');
        $this->mockEntityMetadata->method('getColumnMap')->willReturn([]);
        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('id');
        $this->mockEntityMetadata->method('getFields')->willReturn(['id', 'name', 'status']);
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

        $this->mockQueryBuilder->method('create')->willReturn($this->mockQuery);

        $this->mockQuery->method('setTable')->willReturnSelf();
        $this->mockQuery->method('setColumnMap')->willReturnSelf();
        $this->mockQuery->method('select')->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('orderBy')->willReturnSelf();
        $this->mockQuery->method('with')->willReturnSelf();

        $this->repository = new TestRepository(
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator
        );
    }

    public function testPaginateReturnsFirstPage(): void
    {
        $page = Page::of(1, 10);
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
            ['id' => 2, 'name' => 'Entity 2'],
        ];

        $paginator = new Paginator($rows, 1, 10, 25);

        $this->mockQuery->expects($this->once())
            ->method('paginate')
            ->with(1, 10)
            ->willReturn($paginator);

        $result = $this->repository->paginate($page);

        $this->assertInstanceOf(Paginator::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertInstanceOf(TestEntity::class, $result->items[0]);
        $this->assertInstanceOf(TestEntity::class, $result->items[1]);
        $this->assertSame(1, $result->page);
        $this->assertSame(10, $result->size);
        $this->assertSame(25, $result->count);
    }

    public function testPaginateReturnsSecondPage(): void
    {
        $page = Page::of(2, 10);
        $rows = [
            ['id' => 11, 'name' => 'Entity 11'],
            ['id' => 12, 'name' => 'Entity 12'],
        ];

        $paginator = new Paginator($rows, 2, 10, 25);

        $this->mockQuery->expects($this->once())
            ->method('paginate')
            ->with(2, 10)
            ->willReturn($paginator);

        $result = $this->repository->paginate($page);

        $this->assertInstanceOf(Paginator::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertSame(11, $result->items[0]->id);
        $this->assertSame(12, $result->items[1]->id);
        $this->assertSame(2, $result->page);
    }

    public function testPaginateWithEmptyResults(): void
    {
        $page = Page::of(10, 10);
        $paginator = new Paginator([], 10, 10, 0);

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $result = $this->repository->paginate($page);

        $this->assertInstanceOf(Paginator::class, $result);
        $this->assertEmpty($result->items);
        $this->assertSame(0, $result->count);
    }

    public function testPaginateWithEmptyResultsAndRelationsDoesNotCallRelationManager(): void
    {
        $page = Page::of(1, 10);
        $fields = ['id', 'name', 'posts' => ['id', 'title']];
        $paginator = new Paginator([], 1, 10, 0);

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $result = $this->repository->paginate($page, [], $fields);

        $this->assertEmpty($result->items);
    }

    public function testPaginateAppliesFilters(): void
    {
        $page = Page::of(1, 10);
        $filters = ['status' => 1];
        $paginator = new Paginator([], 1, 10, 0);

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($filters)
            ->willReturnSelf();

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->repository->paginate($page, $filters);
    }

    public function testPaginateAppliesOrders(): void
    {
        $page = Page::of(1, 10);
        $orders = ['name' => SORT_ASC, 'id' => SORT_DESC];
        $paginator = new Paginator([], 1, 10, 0);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with($orders)
            ->willReturnSelf();

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->repository->paginate($page, [], [], $orders);
    }

    public function testPaginateWithRelations(): void
    {
        $page = Page::of(1, 10);
        $fields = ['id', 'name', 'posts' => ['id', 'title']];
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $paginator = new Paginator($rows, 1, 10, 1);

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->mockRelationManager->method('has')
            ->willReturnCallback(static function (string $entityClass, string $relationName): bool {
                return $entityClass === TestEntity::class && $relationName === 'posts';
            });

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(
                TestEntity::class,
                $this->callback(function ($entities) {
                    return count($entities) === 1 && $entities[0] instanceof TestEntity;
                }),
                ['posts' => ['id', 'title']]
            )
            ->willReturnArgument(1);

        $result = $this->repository->paginate($page, [], $fields);

        $this->assertInstanceOf(Paginator::class, $result);
        $this->assertCount(1, $result->items);
    }

    public function testPaginateWithoutRelationsDoesNotCallRelationManager(): void
    {
        $page = Page::of(1, 10);
        $fields = ['id', 'name'];
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];
        $paginator = new Paginator($rows, 1, 10, 1);

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $result = $this->repository->paginate($page, [], $fields);

        $this->assertCount(1, $result->items);
        $this->assertInstanceOf(TestEntity::class, $result->items[0]);
    }

    public function testPaginateWithRelationOnlyFieldsDoesNotAppendPrimaryKey(): void
    {
        $page = Page::of(1, 10);
        $fields = ['posts' => ['id', 'title']];
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];
        $paginator = new Paginator($rows, 1, 10, 1);

        $this->mockEntityMetadata->expects($this->never())
            ->method('getPrimaryKey');

        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name', 'status']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name', 'status'])
            ->willReturnSelf();
        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->mockRelationManager->method('has')
            ->willReturnCallback(static function (string $entityClass, string $relationName): bool {
                return $entityClass === TestEntity::class && $relationName === 'posts';
            });

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturnArgument(1);

        $result = $this->repository->paginate($page, [], $fields);

        $this->assertCount(1, $result->items);
    }

    public function testPaginateWithRelationOnlyFieldsAndEmptyRowsDoesNotCallRelationManager(): void
    {
        $page = Page::of(1, 10);
        $fields = ['posts' => ['id', 'title']];
        $paginator = new Paginator([], 1, 10, 0);

        $this->mockEntityMetadata->expects($this->never())
            ->method('getPrimaryKey');
        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name', 'status']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name', 'status'])
            ->willReturnSelf();
        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $result = $this->repository->paginate($page, [], $fields);

        $this->assertEmpty($result->items);
    }

    public function testPaginateDoesNotAppendDuplicatePrimaryKeyWhenIdAlreadyPresentWithRelations(): void
    {
        $page = Page::of(1, 10);
        $fields = ['id', 'name', 'posts' => ['id', 'title']];
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $paginator = new Paginator($rows, 1, 10, 1);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $this->mockRelationManager->method('has')
            ->willReturnCallback(static function (string $entityClass, string $relationName): bool {
                return $entityClass === TestEntity::class && $relationName === 'posts';
            });

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(
                TestEntity::class,
                $this->callback(function ($entities) {
                    return count($entities) === 1 && $entities[0] instanceof TestEntity;
                }),
                ['posts' => ['id', 'title']]
            )
            ->willReturnArgument(1);

        $this->repository->paginate($page, [], $fields);
    }

    public function testPaginateWithCustomPageSize(): void
    {
        $page = Page::of(1, 50);
        $rows = array_map(fn ($i) => ['id' => $i, 'name' => "Entity $i"], range(1, 50));

        $paginator = new Paginator($rows, 1, 50, 100);

        $this->mockQuery->expects($this->once())
            ->method('paginate')
            ->with(1, 50)
            ->willReturn($paginator);

        $result = $this->repository->paginate($page);

        $this->assertCount(50, $result->items);
        $this->assertSame(50, $result->size);
    }

    public function testPaginateConvertsRowsToEntities(): void
    {
        $page = Page::of(1, 10);
        $rows = [
            ['id' => 1, 'name' => 'Entity 1', 'status' => 1],
            ['id' => 2, 'name' => 'Entity 2', 'status' => 0],
        ];

        $paginator = new Paginator($rows, 1, 10, 2);

        $this->mockQuery->method('paginate')->willReturn($paginator);

        $result = $this->repository->paginate($page);

        $this->assertInstanceOf(TestEntity::class, $result->items[0]);
        $this->assertInstanceOf(TestEntity::class, $result->items[1]);
        $this->assertSame('Entity 1', $result->items[0]->name);
        $this->assertSame(1, $result->items[0]->status);
        $this->assertSame('Entity 2', $result->items[1]->name);
        $this->assertSame(0, $result->items[1]->status);
    }
}
