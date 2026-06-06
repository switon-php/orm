<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\MorphManyRelation;
use Switon\Orm\Tests\Fixtures\TestMorphChild;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class MorphManyRelationTest extends TestCase
{
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|QueryInterface $mockQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);

        // Replace dependencies in container for autowiring
        $this->container->replace(EntityMetadataInterface::class, $this->mockEntityMetadata);
    }

    /**
     * Create a MorphManyRelation with dependencies autowired and entity classes bound.
     */
    protected function createMorphManyRelation(
        string $relatedEntity,
        string $tableField,
        string $idField,
        string $selfEntity = TestUser::class
    ): MorphManyRelation {
        $relation = $this->createRelation(MorphManyRelation::class, [
            'relatedEntity' => $relatedEntity,
            'tableField' => $tableField,
            'idField' => $idField,
        ]);
        $relation->bind($selfEntity, $relatedEntity);
        return $relation;
    }

    public function testEarlyLoadAppliesPolymorphicConditionsAndSkipsOrphans(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $parents = [
            ['user_id' => 1, 'name' => 'U1'],
            ['user_id' => 2, 'name' => 'U2'],
        ];

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users'])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('commentable_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['id' => 10, 'commentable_table' => 'test_users', 'commentable_id' => 1, 'title' => 'C1'],
                ['id' => 11, 'commentable_table' => 'test_users', 'commentable_id' => 999, 'title' => 'Orphan'],
            ]);

        $result = $relation->earlyLoad($parents, $this->mockQuery, 'posts');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(0, $result[1]['posts']);
    }

    public function testEarlyLoadAttachesAndSkipsBasedOnStringAndIntParentIdMix(): void
    {
        // Arrange: parent ids include numeric-string (should match int related ids) + non-numeric string (should not).
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $parents = [
            ['user_id' => '1', 'name' => 'U1-string'],
            ['user_id' => 'u2', 'name' => 'U2-nonnumeric'],
            ['user_id' => 2, 'name' => 'U2-int'],
        ];

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users'])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('commentable_id', ['1', 'u2', 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['id' => 10, 'commentable_table' => 'test_users', 'commentable_id' => 1, 'title' => 'C1'],
                ['id' => 11, 'commentable_table' => 'test_users', 'commentable_id' => 2, 'title' => 'C2'],
                ['id' => 12, 'commentable_table' => 'test_users', 'commentable_id' => 3, 'title' => 'Orphan'],
            ]);

        // Act
        $result = $relation->earlyLoad($parents, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(3, $result);

        $this->assertCount(1, $result[0]['posts']);
        $this->assertInstanceOf(TestMorphChild::class, $result[0]['posts'][0]);
        $this->assertSame(10, $result[0]['posts'][0]->id);

        $this->assertCount(0, $result[1]['posts']);

        $this->assertCount(1, $result[2]['posts']);
        $this->assertInstanceOf(TestMorphChild::class, $result[2]['posts'][0]);
        $this->assertSame(11, $result[2]['posts'][0]->id);
    }

    public function testEarlyLoadAttachesEmptyArraysWhenRelatedQueryFetchesEmpty(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $parents = [
            ['user_id' => 1, 'name' => 'U1'],
            ['user_id' => 2, 'name' => 'U2'],
        ];

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users'])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('commentable_id', [1, 2])
            ->willReturnSelf();

        // Empty related entities: should not throw and should attach empty arrays per parent.
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([]);

        // Act
        $result = $relation->earlyLoad($parents, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(2, $result);
        $this->assertCount(0, $result[0]['posts']);
        $this->assertCount(0, $result[1]['posts']);
    }

    public function testEarlyLoadSkipsOrphansWhenParentIdIsZero(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $parents = [
            ['user_id' => 0, 'name' => 'U0'],
            ['user_id' => 1, 'name' => 'U1'],
        ];

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users'])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('commentable_id', [0, 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['id' => 10, 'commentable_table' => 'test_users', 'commentable_id' => 0, 'title' => 'C0'],
                ['id' => 11, 'commentable_table' => 'test_users', 'commentable_id' => 999, 'title' => 'Orphan'],
            ]);

        // Act
        $result = $relation->earlyLoad($parents, $this->mockQuery, 'posts');

        // Assert: parentId=0 should still attach correctly
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(0, $result[1]['posts']);
    }

    public function testEarlyLoadWithDuplicateParentIdsAttachesToEachParentRow(): void
    {
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $parents = [
            ['user_id' => 1, 'name' => 'U1-A'],
            ['user_id' => 1, 'name' => 'U1-B'],
        ];

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users'])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('commentable_id', [1, 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['id' => 10, 'commentable_table' => 'test_users', 'commentable_id' => 1, 'title' => 'C1'],
            ]);

        $result = $relation->earlyLoad($parents, $this->mockQuery, 'posts');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(1, $result[1]['posts']);
        $this->assertInstanceOf(TestMorphChild::class, $result[0]['posts'][0]);
        $this->assertInstanceOf(TestMorphChild::class, $result[1]['posts'][0]);
        $this->assertSame(10, $result[0]['posts'][0]->id);
        $this->assertSame(10, $result[1]['posts'][0]->id);
    }

    public function testEarlyLoadThrowsWhenIdFieldMissing(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockEntityMetadata->method('getTable')->willReturn('test_users');

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([
            ['id' => 10, 'commentable_table' => 'test_users', 'title' => 'C1'],
        ]);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad([
            ['user_id' => 1],
        ], $this->mockQuery, 'posts');
    }

    public function testEarlyLoadThrowsWhenTableFieldMissing(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockEntityMetadata->method('getTable')->willReturn('test_users');

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([
            ['id' => 10, 'commentable_id' => 1, 'title' => 'C1'],
        ]);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad([
            ['user_id' => 1],
        ], $this->mockQuery, 'posts');
    }

    public function testEarlyLoadThrowsWhenKeyedRelatedRowsMissTableField(): void
    {
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockEntityMetadata->method('getTable')->willReturn('test_users');

        $this->mockQuery->method('where')->willReturnSelf();
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([
            'row-10' => ['id' => 10, 'commentable_id' => 1, 'title' => 'C1'],
        ]);

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field commentable_table in relation posts');

        $relation->earlyLoad([
            ['user_id' => 1],
        ], $this->mockQuery, 'posts');
    }

    public function testLazyLoadBuildsWhereWithZeroParentIdCorrectly(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 0;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestMorphChild::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users', 'commentable_id' => 0])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        // Act
        $result = $relation->lazyLoad($mockEntity);

        // Assert
        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadBuildsWhereWithNonZeroParentIdCorrectly(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 7;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestMorphChild::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users', 'commentable_id' => 7])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        // Act
        $result = $relation->lazyLoad($mockEntity);

        // Assert
        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadAppliesConfiguredOrderBy(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $this->injectRelationDependencies($relation, [
            'orderBy' => ['created_at' => SORT_DESC],
        ]);

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 3;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestMorphChild::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['created_at' => SORT_DESC])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['commentable_table' => 'test_users', 'commentable_id' => 3])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        // Act
        $relation->lazyLoad($mockEntity);
    }

    public function testLazyLoadAlwaysSetsFetchTypeTrue(): void
    {
        // Arrange
        $relation = $this->createMorphManyRelation(TestMorphChild::class, 'commentable_table', 'commentable_id');

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getTable')
            ->with(TestUser::class, true)
            ->willReturn('test_users');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestMorphChild::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->method('where')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        // Act
        $result = $relation->lazyLoad($mockEntity);

        // Assert
        $this->assertSame($this->mockQuery, $result);
    }
}
