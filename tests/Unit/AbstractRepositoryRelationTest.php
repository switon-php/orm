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

    public function testAllWithRelationOnlyFieldsDoesNotAppendPrimaryKey(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];
        $fields = ['posts' => ['id', 'title']];

        $this->mockEntityMetadata->expects($this->never())
            ->method('getPrimaryKey');

        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($rows);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
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

    public function testAllDoesNotAppendDuplicatePrimaryKeyWhenIdAlreadyPresentWithRelations(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];

        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
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

    public function testFirstWithRelationOnlyFieldsDoesNotAppendPrimaryKey(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['posts' => ['id', 'title']];

        $this->mockEntityMetadata->expects($this->never())
            ->method('getPrimaryKey');

        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
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

    public function testFirstDoesNotAppendDuplicatePrimaryKeyWhenIdAlreadyPresentWithRelations(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
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

    public function testFirstOrFailDoesNotAppendDuplicatePrimaryKeyWhenIdAlreadyPresentWithRelations(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();

        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $entity = $this->repository->firstOrFail([], $fields);

        $this->assertSame(1, $entity->id);
        $this->assertSame('Entity 1', $entity->name);
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

    public function testFindLoadsRelationsWhenSpecified(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(static function (array $filters): bool {
                return isset($filters['id']) && $filters['id'] === 1;
            }))
            ->willReturnSelf();

        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(
                TestEntity::class,
                $this->callback(static function ($entities): bool {
                    return count($entities) === 1 && $entities[0] instanceof TestEntity;
                }),
                ['posts' => ['id', 'title']]
            )
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $result = $this->repository->find(1, $fields);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testFindAddsRootPrimaryKeyWhenRelationsNeedIt(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['name', 'id'])
            ->willReturnSelf();

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $this->repository->find(1, $fields);
    }

    public function testFindDoesNotAppendDuplicatePrimaryKeyWhenIdAlreadyPresentWithRelations(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $this->repository->find(1, $fields);
    }

    public function testFindReturnsNullWhenNoMatchWithRelationFields(): void
    {
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn(null);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $this->assertNull($this->repository->find(999, $fields));
    }

    public function testFindWithRelationOnlyFieldsDoesNotAppendPrimaryKey(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['posts' => ['id', 'title']];

        $this->mockEntityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn('id');
        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $result = $this->repository->find(1, $fields);

        $this->assertInstanceOf(TestEntity::class, $result);
    }

    public function testFirstOrFailThrowsEntityNotFoundWhenNoMatchWithRelationFields(): void
    {
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn(null);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $this->expectException(EntityNotFoundException::class);
        $this->repository->firstOrFail(['id' => 999], $fields);
    }

    public function testFirstOrFailWithRelationOnlyFieldsDoesNotAppendPrimaryKey(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['posts' => ['id', 'title']];

        $this->mockEntityMetadata->expects($this->never())
            ->method('getPrimaryKey');
        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $entity = $this->repository->firstOrFail(['id' => 1], $fields);

        $this->assertSame(1, $entity->id);
    }

    public function testGetLoadsRelationsWhenSpecified(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(static function (array $filters): bool {
                return isset($filters['id']) && $filters['id'] === 1;
            }))
            ->willReturnSelf();

        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $entity = $this->repository->get(1, $fields);

        $this->assertSame(1, $entity->id);
    }

    public function testGetAddsRootPrimaryKeyWhenRelationsNeedIt(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['name', 'id'])
            ->willReturnSelf();

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $this->repository->get(1, $fields);
    }

    public function testGetDoesNotAppendDuplicatePrimaryKeyWhenIdAlreadyPresentWithRelations(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $this->repository->get(1, $fields);
    }

    public function testGetThrowsEntityNotFoundWhenRowMissingWithRelationFields(): void
    {
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn(null);

        $this->mockRelationManager->expects($this->never())
            ->method('earlyLoad');

        $this->expectException(EntityNotFoundException::class);
        $this->repository->get(404, $fields);
    }

    public function testGetWithRelationOnlyFieldsDoesNotAppendPrimaryKey(): void
    {
        $row = ['id' => 1, 'name' => 'Entity 1'];
        $fields = ['posts' => ['id', 'title']];

        $this->mockEntityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(TestEntity::class)
            ->willReturn('id');
        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($row);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturn([new TestEntity(['id' => 1, 'name' => 'Entity 1'])]);

        $entity = $this->repository->get(1, $fields);

        $this->assertSame(1, $entity->id);
    }

    public function testAllByAddsRootPrimaryKeyWhenRelationsNeedIt(): void
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

        $result = $this->repository->allBy([], 'id', $fields);

        $this->assertArrayHasKey(1, $result);
    }

    public function testAllByDoesNotAppendDuplicatePrimaryKeyWhenIdAlreadyPresentWithRelations(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];
        $fields = ['id', 'name', 'posts' => ['id', 'title']];

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();

        $this->mockQuery->method('fetch')->willReturn($rows);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturnArgument(1);

        $this->repository->allBy([], 'id', $fields);
    }

    public function testAllByWithRelationOnlyFieldsDoesNotAppendPrimaryKey(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Entity 1'],
        ];
        $fields = ['posts' => ['id', 'title']];

        $this->mockEntityMetadata->expects($this->never())
            ->method('getPrimaryKey');
        $this->mockEntityMetadata->expects($this->atLeastOnce())
            ->method('getFields')
            ->with(TestEntity::class)
            ->willReturn(['id', 'name']);

        $this->mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($rows);

        $this->mockRelationManager->expects($this->once())
            ->method('earlyLoad')
            ->with(TestEntity::class, $this->anything(), ['posts' => ['id', 'title']])
            ->willReturnArgument(1);

        $result = $this->repository->allBy([], 'id', $fields);

        $this->assertArrayHasKey(1, $result);
    }
}
