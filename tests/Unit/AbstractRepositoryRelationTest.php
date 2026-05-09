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
class AbstractRepositoryRelationTest extends TestCase
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
        $this->mockEntityMetadata->method('getFields')->willReturn(['id', 'name']);

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

    public function testAllLoadsRelationsWhenSpecified(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->method('fetch')->willReturn($rows);

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

        $result = $this->repository->all([], $fields);

        $this->assertCount(1, $result);
    }

    public function testAllAddsRootPrimaryKeyWhenRelationsNeedIt(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $fields = ['name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['name', 'id'])
            ->willReturnSelf();

        $this->mockQuery->method('fetch')->willReturn($rows);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturnArgument(1);

        $this->repository->all([], $fields);
    }

    public function testAllTreatsStringKeyWithsAsRelationWiths(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        // All string-key with entries are treated as relation payloads.
        $fields = [
            'id',
            'name',
            'posts' => ['id', 'title'],
            'someQueryWith' => ['field1'],
        ];

        $this->mockQuery->method('fetch')->willReturn($rows);
        $this->mockQuery->expects($this->never())
            ->method('with');

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(
                TestEntity::class,
                $this->anything(),
                [
                    'posts' => ['id', 'title'],
                    'someQueryWith' => ['field1'],
                ]
            )
            ->willReturnArgument(1);

        $this->repository->all([], $fields);
    }

    public function testFirstLoadsRelationsWhenSpecified(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(
                TestEntity::class,
                $this->callback(function ($entities) {
                    return count($entities) === 1 && $entities[0] instanceof TestEntity;
                }),
                ['posts' => ['id', 'title']]
            )
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $result = $this->repository->first([], $fields);

        $this->assertInstanceOf(TestEntity::class, $result);
    }

    public function testFirstAddsRootPrimaryKeyWhenRelationsNeedIt(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['name', 'id'])
            ->willReturnSelf();

        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $this->repository->first([], $fields);
    }

    public function testAllWithNestedRelations(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $fields = [
            'id',
            'name',
            'posts' => [
                'id',
                'title',
                'comments' => ['id', 'content'], // Nested relation
            ],
        ];

        $this->mockQuery->method('fetch')->willReturn($rows);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(
                TestEntity::class,
                $this->anything(),
                $this->callback(function ($withs) {
                    return isset($withs['posts'])
                        && is_array($withs['posts'])
                        && in_array('id', $withs['posts'])
                        && in_array('title', $withs['posts'])
                        && isset($withs['posts']['comments']);
                })
            )
            ->willReturnArgument(1);

        $this->repository->all([], $fields);
    }

    public function testAllWithMultipleRelations(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $fields = [
            'id',
            'name',
            'posts' => ['id', 'title'],
            'comments' => ['id', 'content'],
            'tags' => ['id', 'name'],
        ];

        $this->mockQuery->method('fetch')->willReturn($rows);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(
                TestEntity::class,
                $this->anything(),
                [
                    'posts' => ['id', 'title'],
                    'comments' => ['id', 'content'],
                    'tags' => ['id', 'name'],
                ]
            )
            ->willReturnArgument(1);

        $this->repository->all([], $fields);
    }

    public function testAllWithoutRelationsDoesNotCallRelationManager(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $fields = ['id', 'name']; // No relations

        $this->mockQuery->method('fetch')->willReturn($rows);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $result = $this->repository->all([], $fields);

        $this->assertCount(1, $result);
    }

    public function testAllWithBareRelationNameTreatsItAsPlainField(): void
    {
        $fields = ['id', 'posts'];

        $this->mockQuery->method('fetch')->willReturn([
            ['id' => 1, 'name' => 'Entity 1'],
        ]);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'posts'])
            ->willReturnSelf();

        $result = $this->repository->all([], $fields);

        $this->assertCount(1, $result);
    }

    public function testAllWithStringKeyStringValueDoesNotCallRelationManager(): void
    {
        $fields = ['id', 'displayName' => 'name'];

        $this->mockQuery->method('fetch')->willReturn([
            ['id' => 1, 'name' => 'Entity 1'],
        ]);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'displayName' => 'name'])
            ->willReturnSelf();

        $result = $this->repository->all([], $fields);

        $this->assertCount(1, $result);
    }
}
