<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationNotFoundException;
use Switon\Orm\Exception\RelationPayloadInvalidException;
use Switon\Orm\RelationInterface;
use Switon\Orm\RelationManager;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class RelationManagerTest extends TestCase
{
    protected RelationManager $manager;
    protected MockObject|EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->manager = new RelationManager();
        $this->injectRelationDependencies($this->manager, [
            'entityMetadata' => $this->entityMetadata,
        ]);
    }

    public function testGetRelatedQueryThrowsWhenRelationDoesNotExist(): void
    {
        $this->entityMetadata->expects($this->once())
            ->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn([]);

        $this->expectException(RelationNotFoundException::class);
        $this->expectExceptionMessage('Relation "missing" is not defined for entity');

        $this->manager->getQuery(TestEntity::class, 'missing', null);
    }

    public function testGetRelatedQueryThrowsWhenRelationValueUsesWildcardString(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->method('getRelatedQuery')->willReturn($query);

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "posts".');

        $this->manager->getQuery(TestEntity::class, 'posts', '*');
    }

    public function testGetRelatedQueryThrowsWhenRelationValueUsesStringFieldList(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->method('getRelatedQuery')->willReturn($query);

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "posts".');

        $this->manager->getQuery(TestEntity::class, 'posts', 'id, name  created_at');
    }

    public function testGetRelatedQueryRunsCallableAgainstRelationQuery(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->method('getRelatedQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('orderBy')
            ->with(['id' => SORT_ASC])
            ->willReturnSelf();

        $result = $this->manager->getQuery(
            TestEntity::class,
            'posts',
            static function (QueryInterface $query): void {
                $query->orderBy(['id' => SORT_ASC]);
            }
        );

        $this->assertSame($query, $result);
    }

    public function testGetRelatedQueryThrowsForUnsupportedWithDataType(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->method('getRelatedQuery')->willReturn($query);

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "posts".');

        $this->manager->getQuery(TestEntity::class, 'posts', new stdClass());
    }

    public function testEarlyLoadWithNestedRelationArraySplitsFieldsAndChildWiths(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->expects($this->once())
            ->method('getRelatedQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('select')
            ->with(['id', 'title'])
            ->willReturnSelf();

        $query->expects($this->once())
            ->method('with')
            ->with(['comments' => ['id', 'content']])
            ->willReturnSelf();

        $relation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $query, 'posts')
            ->willReturn($entities);

        $result = $this->manager->earlyLoad(
            TestEntity::class,
            $entities,
            ['posts' => ['id', 'title', 'comments' => ['id', 'content']]]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadWithOnlyNestedRelationsSkipsSelectAndAppliesChildWiths(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->expects($this->once())
            ->method('getRelatedQuery')
            ->willReturn($query);

        $query->expects($this->never())
            ->method('select');

        $query->expects($this->once())
            ->method('with')
            ->with(['comments' => ['id', 'content']])
            ->willReturnSelf();

        $relation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $query, 'posts')
            ->willReturn($entities);

        $result = $this->manager->earlyLoad(
            TestEntity::class,
            $entities,
            ['posts' => ['comments' => ['id', 'content']]]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadDoesNotValidateNestedPayloadShape(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->expects($this->once())
            ->method('getRelatedQuery')
            ->willReturn($query);

        $query->expects($this->never())
            ->method('select');

        $query->expects($this->once())
            ->method('with')
            ->with(['comments' => '*'])
            ->willReturnSelf();

        $relation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $query, 'posts')
            ->willReturn($entities);

        $result = $this->manager->earlyLoad(
            TestEntity::class,
            $entities,
            ['posts' => ['comments' => '*']]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadThrowsWhenDottedRelationDoesNotExist(): void
    {
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->expectException(RelationNotFoundException::class);
        $this->expectExceptionMessage('Relation "posts.comments" is not defined for entity');

        $this->manager->earlyLoad(
            TestEntity::class,
            $entities,
            ['posts.comments' => []]
        );
    }

    public function testEarlyLoadThrowsWhenRelationUsesNonArrayValue(): void
    {
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load payload for relation "posts".');

        $this->manager->earlyLoad(TestEntity::class, $entities, ['posts' => '*']);
    }

    public function testEarlyLoadSupportsQueryInterfaceRelationValue(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $query, 'posts')
            ->willReturn($entities);

        $result = $this->manager->earlyLoad(
            TestEntity::class,
            $entities,
            ['posts' => $query]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadSupportsCallableRelationValue(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->expects($this->once())
            ->method('getRelatedQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('orderBy')
            ->with(['id' => SORT_ASC])
            ->willReturnSelf();

        $relation->expects($this->once())
            ->method('earlyLoad')
            ->with($entities, $query, 'posts')
            ->willReturn($entities);

        $result = $this->manager->earlyLoad(
            TestEntity::class,
            $entities,
            [
                'posts' => static function (QueryInterface $query): void {
                    $query->orderBy(['id' => SORT_ASC]);
                },
            ]
        );

        $this->assertSame($entities, $result);
    }

    public function testEarlyLoadThrowsWhenWithRelationDoesNotExist(): void
    {
        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn([]);

        $this->expectException(RelationNotFoundException::class);
        $this->expectExceptionMessage('Relation "posts" is not defined for entity');

        $this->manager->earlyLoad(TestEntity::class, [], ['posts' => ['id']]);
    }

    public function testLazyLoadDelegatesToRelation(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);
        $entity = new TestEntity(['id' => 1, 'name' => 'A']);

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->expects($this->once())
            ->method('lazyLoad')
            ->with($entity)
            ->willReturn($query);

        $this->assertSame($query, $this->manager->lazyLoad($entity, 'posts'));
    }

    public function testLazyLoadThrowsWhenRelationDoesNotExist(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'A']);

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn([]);

        $this->expectException(RelationNotFoundException::class);
        $this->expectExceptionMessage('Relation "missing" is not defined for entity');

        $this->manager->lazyLoad($entity, 'missing');
    }

    public function testHasReturnsTrueWhenRelationExists(): void
    {
        $relation = $this->createMock(RelationInterface::class);

        $this->entityMetadata->expects($this->once())
            ->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $this->assertTrue($this->manager->has(TestEntity::class, 'posts'));
    }

    public function testGetRelatedQuerySelectsFieldsWhenWithDataIsArray(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $relation = $this->createMock(RelationInterface::class);

        $this->entityMetadata->method('getRelations')
            ->with(TestEntity::class)
            ->willReturn(['posts' => $relation]);

        $relation->method('getRelatedQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('select')
            ->with(['id', 'name'])
            ->willReturnSelf();

        $result = $this->manager->getQuery(TestEntity::class, 'posts', ['id', 'name']);

        $this->assertSame($query, $result);
    }

    public function testEarlyLoadThrowsWhenRelationUsesNumericIndex(): void
    {
        $entities = [new TestEntity(['id' => 1, 'name' => 'A'])];

        $this->expectException(RelationPayloadInvalidException::class);
        $this->expectExceptionMessage('Invalid eager-load relation key "0".');

        $this->manager->earlyLoad(TestEntity::class, $entities, ['posts']);
    }
}
