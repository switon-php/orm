<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationNotFoundException;
use Switon\Orm\Exception\RelationPayloadInvalidException;
use Switon\Orm\RelationInterface;
use Switon\Orm\RelationManager;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class RelationManagerIntegrationTest extends TestCase
{
    #[Autowired] protected RelationManagerInterface $relationManager;
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        // Remove if already resolved to avoid ServiceAlreadyResolvedException
        if ($this->container->has(EntityMetadataInterface::class)) {
            $this->container->remove(EntityMetadataInterface::class);
        }

        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->container->set(EntityMetadataInterface::class, $this->mockEntityMetadata);

        // Remove RelationManager if already resolved to force re-injection with new mock
        if ($this->container->has(RelationManagerInterface::class)) {
            $this->container->remove(RelationManagerInterface::class);
        }

        $this->injector->inject($this);
    }

    public function testRelationManagerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(RelationManagerInterface::class, $this->relationManager);
        $this->assertInstanceOf(RelationManager::class, $this->relationManager);
    }

    public function testHasReturnsFalseWhenRelationDoesNotExist(): void
    {
        $entityClass = TestEntity::class;
        $relationName = 'someRelation';

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([]);

        $result = $this->relationManager->has($entityClass, $relationName);

        $this->assertFalse($result);
    }

    public function testHasReturnsTrueWhenRelationExists(): void
    {
        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $result = $this->relationManager->has($entityClass, $relationName);

        $this->assertTrue($result);
    }

    public function testGetReturnsNullWhenRelationDoesNotExist(): void
    {
        $entityClass = TestEntity::class;
        $relationName = 'someRelation';

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([]);

        $result = $this->relationManager->get($entityClass, $relationName);

        $this->assertNull($result);
    }

    public function testGetReturnsRelationWhenExists(): void
    {
        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $result = $this->relationManager->get($entityClass, $relationName);

        $this->assertSame($mockRelation, $result);
    }

    public function testEarlyLoadWithEmptyRelationsArray(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relations = [];
        $entityClass = TestEntity::class;

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([]);

        $result = $this->relationManager->earlyLoad(TestEntity::class, $entities, $relations);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TestEntity::class, $result[0]);
    }

    public function testEarlyLoadThrowsExceptionWhenRelationDoesNotExist(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relations = ['nonexistent'];
        $entityClass = TestEntity::class;

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([]);

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load relation key "0".');
        $this->relationManager->earlyLoad(TestEntity::class, $entities, $relations);
    }

    public function testEarlyLoadWithRelationArrayValue(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);
        $mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name']);

        $mockRelation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $mockQuery, $relationName)
            ->willReturn($entities);

        $result = $this->relationManager->earlyLoad(TestEntity::class, $entities, [$relationName => ['id', 'name']]);

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadWithNestedRelationArray(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $mockQuery->expects($this->once())
            ->method('select')
            ->with(['id', 'name']);

        $mockQuery->expects($this->once())
            ->method('with')
            ->with(['childRelation' => ['id']]);

        $mockRelation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $mockQuery, $relationName)
            ->willReturn($entities);

        $result = $this->relationManager->earlyLoad(
            TestEntity::class,
            $entities,
            [$relationName => ['id', 'name', 'childRelation' => ['id']]]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadWithNestedRelationOnlyAppliesChildWithWithoutSelect(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $mockQuery->expects($this->never())
            ->method('select');

        $mockQuery->expects($this->once())
            ->method('with')
            ->with(['childRelation' => ['id']]);

        $mockRelation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $mockQuery, $relationName)
            ->willReturn($entities);

        $result = $this->relationManager->earlyLoad(
            TestEntity::class,
            $entities,
            [$relationName => ['childRelation' => ['id']]]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadDoesNotValidateNestedPayloadShape(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $mockQuery->expects($this->never())
            ->method('select');

        $mockQuery->expects($this->once())
            ->method('with')
            ->with(['childRelation' => '*']);

        $mockRelation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $mockQuery, $relationName)
            ->willReturn($entities);

        $result = $this->relationManager->earlyLoad(
            TestEntity::class,
            $entities,
            [$relationName => ['childRelation' => '*']]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadSupportsQueryInterfaceRelationValue(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $mockQuery, $relationName)
            ->willReturn($entities);

        $result = $this->relationManager->earlyLoad(TestEntity::class, $entities, [$relationName => $mockQuery]);

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadSupportsCallableRelationValue(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['id' => 'ASC']);

        $mockRelation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $mockQuery, $relationName)
            ->willReturn($entities);

        $result = $this->relationManager->earlyLoad(
            TestEntity::class,
            $entities,
            [
                $relationName => static function (QueryInterface $query): void {
                    $query->orderBy(['id' => 'ASC']);
                },
            ]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadThrowsWhenRelationValueTypeIsUnsupported(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entities = [$entity];
        $relationName = 'someRelation';

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "someRelation".');

        $this->relationManager->earlyLoad(TestEntity::class, $entities, [$relationName => new \stdClass()]);
    }

    public function testLazyLoadThrowsExceptionWhenRelationDoesNotExist(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([]);

        $this->expectException(RelationNotFoundException::class);
        $this->expectExceptionMessage('Relation "someRelation" is not defined for entity');
        $this->relationManager->lazyLoad($entity, $relationName);
    }

    public function testGetRelatedQueryThrowsExceptionWhenRelationDoesNotExist(): void
    {
        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $data = null;

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([]);

        $this->expectException(RelationNotFoundException::class);
        $this->expectExceptionMessage('Relation "someRelation" is not defined for entity');
        $this->relationManager->getQuery($entityClass, $relationName, $data);
    }

    public function testGetRelatedQueryWithNullData(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $result = $this->relationManager->getQuery($entityClass, $relationName, null);

        $this->assertSame($mockQuery, $result);
    }

    public function testGetRelatedQueryThrowsWhenDataUsesWildcardString(): void
    {
        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "someRelation".');

        $this->relationManager->getQuery($entityClass, $relationName, '*');
    }

    public function testGetRelatedQueryThrowsWhenDataUsesStringFieldList(): void
    {
        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "someRelation".');

        $this->relationManager->getQuery($entityClass, $relationName, 'id, name');
    }

    public function testGetRelatedQueryWithArrayData(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);
        $fields = ['id', 'name', 'email'];

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $mockQuery->expects($this->once())
            ->method('select')
            ->with($fields);

        $result = $this->relationManager->getQuery($entityClass, $relationName, $fields);

        $this->assertSame($mockQuery, $result);
    }

    public function testGetRelatedQueryWithCallableData(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);
        $callable = function ($query) use ($mockQuery) {
            $this->assertSame($mockQuery, $query);
            $query->select(['id']);
        };

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $mockQuery->expects($this->once())
            ->method('select')
            ->with(['id']);

        $result = $this->relationManager->getQuery($entityClass, $relationName, $callable);

        $this->assertSame($mockQuery, $result);
    }

    public function testGetRelatedQueryThrowsExceptionWithInvalidData(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entityClass = TestEntity::class;
        $relationName = 'someRelation';
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->method('getRelatedQuery')
            ->willReturn($mockQuery);

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "someRelation".');
        $this->relationManager->getQuery($entityClass, $relationName, 123);
    }

    public function testLazyLoadReturnsQueryWhenRelationExists(): void
    {
        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }

        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $relationName = 'someRelation';
        $entityClass = TestEntity::class;
        $mockRelation = $this->createMock(RelationInterface::class);
        $mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getRelations')
            ->with($entityClass)
            ->willReturn([$relationName => $mockRelation]);

        $mockRelation->expects($this->once())
            ->method('lazyLoad')
            ->with($entity)
            ->willReturn($mockQuery);

        $result = $this->relationManager->lazyLoad($entity, $relationName);

        $this->assertSame($mockQuery, $result);
    }
}
