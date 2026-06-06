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
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestRepositoryCrud;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class AbstractRepositoryCrudTest extends TestCase
{
    protected TestRepositoryCrud $repository;
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

        $this->repository = new TestRepositoryCrud(
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator
        );
    }

    public function testCreateCreatesNewEntityUsingEntityManager(): void
    {
        $data = ['name' => 'New Entity'];
        $createdEntity = new TestEntity(['id' => 1, 'name' => 'New Entity']);

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string']);

        $this->mockEntityManager->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($arg) {
                return $arg instanceof TestEntity && $arg->name === 'New Entity';
            }))
            ->willReturn($createdEntity);

        $result = $this->repository->create($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('New Entity', $result->name);
    }

    public function testUpdateUpdatesEntityUsingEntityManager(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Updated Name']);
        $updatedEntity = new TestEntity(['id' => 1, 'name' => 'Updated Name']);

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
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();
        $this->mockQuery->method('fetch')
            ->willReturn(['id' => 1, 'name' => 'Original Name']);

        $this->mockEntityManager->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($arg) {
                return $arg instanceof TestEntity && $arg->id === 1;
            }), $this->callback(function ($arg) {
                return $arg instanceof TestEntity && $arg->id === 1;
            }))
            ->willReturn($updatedEntity);

        $result = $this->repository->update($entity);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame('Updated Name', $result->name);
    }

    public function testDeleteDeletesEntityUsingEntityManager(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Entity']);

        $this->mockEntityManager->expects($this->once())
            ->method('delete')
            ->with($entity)
            ->willReturn($entity);

        $result = $this->repository->delete($entity);

        $this->assertInstanceOf(TestEntity::class, $result);
    }

    public function testSaveCreatesEntityWhenNoPrimaryKey(): void
    {
        $entity = new TestEntity(['name' => 'New Entity']);
        $createdEntity = new TestEntity(['id' => 1, 'name' => 'New Entity']);

        $this->mockEntityManager->expects($this->once())
            ->method('create')
            ->with($entity)
            ->willReturn($createdEntity);

        $result = $this->repository->save($entity);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testSaveUpdatesEntityWhenHasPrimaryKey(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Updated Name']);

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
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();
        $this->mockQuery->method('with')
            ->willReturnSelf();
        $this->mockQuery->method('fetch')
            ->willReturn(['id' => 1, 'name' => 'Original Name']);

        $this->mockEntityManager->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($arg) {
                return $arg instanceof TestEntity && $arg->id === 1;
            }), $this->callback(function ($arg) {
                return $arg instanceof TestEntity && $arg->id === 1;
            }))
            ->willReturn($entity);

        $result = $this->repository->save($entity);

        $this->assertInstanceOf(TestEntity::class, $result);
    }

    public function testGetReturnsEntityById(): void
    {
        $id = 1;
        $row = ['id' => 1, 'name' => 'Entity 1'];

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
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();
        $this->mockQuery->method('with')
            ->willReturnSelf();
        $this->mockQuery->method('fetch')
            ->willReturn($row);

        $result = $this->repository->get($id);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('Entity 1', $result->name);
    }

    public function testGetThrowsExceptionWhenNotFound(): void
    {
        $id = 999;

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
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();
        $this->mockQuery->method('fetch')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->repository->get($id);
    }

    public function testUpdateByIdUpdatesEntityById(): void
    {
        $id = 1;
        $data = ['name' => 'Updated Name'];
        $entity = new TestEntity(['id' => 1, 'name' => 'Updated Name']);

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
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();
        $this->mockQuery->method('fetch')
            ->willReturn(['id' => 1, 'name' => 'Original Name']);

        $this->mockEntityManager->expects($this->once())
            ->method('update')
            ->willReturn($entity);

        $result = $this->repository->updateById($id, $data);

        $this->assertInstanceOf(TestEntity::class, $result);
    }

    public function testDeleteByIdDeletesEntityById(): void
    {
        $id = 1;
        $entity = new TestEntity(['id' => 1, 'name' => 'Entity']);

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
        $this->mockQuery->method('limit')
            ->willReturnSelf();
        $this->mockQuery->method('setFetchType')
            ->willReturnSelf();
        $this->mockQuery->method('fetch')
            ->willReturn(['id' => 1, 'name' => 'Entity']);

        $this->mockEntityManager->expects($this->once())
            ->method('delete')
            ->with($this->callback(function ($arg) {
                return $arg instanceof TestEntity && $arg->id === 1;
            }))
            ->willReturn($entity);

        $result = $this->repository->deleteById($id);

        $this->assertInstanceOf(TestEntity::class, $result);
    }

    public function testUpdateArrayRestoresPrimaryKeyFromOriginalBeforeEntityManagerUpdate(): void
    {
        $data = ['id' => 1, 'name' => 'Updated Name'];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string']);

        $this->mockQueryBuilder->method('create')
            ->willReturn($this->mockQuery);
        $this->mockQuery->method('setTable')->willReturnSelf();
        $this->mockQuery->method('setColumnMap')->willReturnSelf();
        $this->mockQuery->method('select')->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn(['id' => 1, 'name' => 'Original Name']);

        $this->mockEntityManager->expects($this->once())
            ->method('update')
            ->with(
                $this->callback(static function ($entity): bool {
                    return $entity instanceof TestEntity
                        && $entity->id === 1
                        && $entity->name === 'Updated Name';
                }),
                $this->callback(static function ($original): bool {
                    return $original instanceof TestEntity
                        && $original->id === 1
                        && $original->name === 'Original Name';
                })
            )
            ->willReturn(new TestEntity(['id' => 1, 'name' => 'Updated Name']));

        $result = $this->repository->update($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('Updated Name', $result->name);
    }

    public function testSaveArrayWithPrimaryKeyRoutesToUpdatePath(): void
    {
        $data = ['id' => 5, 'name' => 'Updated Name'];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['id' => 'int', 'name' => 'string']);

        $this->mockQueryBuilder->method('create')
            ->willReturn($this->mockQuery);
        $this->mockQuery->method('setTable')->willReturnSelf();
        $this->mockQuery->method('setColumnMap')->willReturnSelf();
        $this->mockQuery->method('select')->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('limit')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn(['id' => 5, 'name' => 'Original Name']);

        $this->mockEntityManager->expects($this->never())->method('create');
        $this->mockEntityManager->expects($this->once())
            ->method('update')
            ->with(
                $this->callback(static function ($entity): bool {
                    return $entity instanceof TestEntity
                        && $entity->id === 5
                        && $entity->name === 'Updated Name';
                }),
                $this->callback(static function ($original): bool {
                    return $original instanceof TestEntity
                        && $original->id === 5
                        && $original->name === 'Original Name';
                })
            )
            ->willReturn(new TestEntity(['id' => 5, 'name' => 'Updated Name']));

        $result = $this->repository->save($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(5, $result->id);
        $this->assertSame('Updated Name', $result->name);
    }

    public function testUpdateThrowsExceptionWhenArrayMissingPrimaryKey(): void
    {
        $data = ['name' => 'Updated Name']; // Missing 'id'

        $this->expectException(PrimaryKeyMissingException::class);

        $this->repository->update($data);
    }

    public function testUpdateThrowsExceptionWhenEntityMissingPrimaryKey(): void
    {
        $entity = new TestEntity(['name' => 'Updated Name']); // Missing 'id'

        $this->expectException(PrimaryKeyMissingException::class);

        $this->repository->update($entity);
    }
}
