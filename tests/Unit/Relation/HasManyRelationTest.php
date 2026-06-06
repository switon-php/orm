<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Event\RelationDataInconsistency;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\HasManyRelation;
use Switon\Orm\Tests\Fixtures\TestPost;
use Switon\Orm\Tests\Fixtures\TestPostStringUserId;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\Fixtures\TestUserId;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class HasManyRelationTest extends TestCase
{
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|EventDispatcherInterface $mockEventDispatcher;
    protected MockObject|QueryInterface $mockQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);

        // Replace dependencies in container for autowiring
        $this->container->replace(EntityMetadataInterface::class, $this->mockEntityMetadata);
        $this->container->replace(EventDispatcherInterface::class, $this->mockEventDispatcher);
    }

    /**
     * Create a HasManyRelation with dependencies autowired and entity classes bound.
     */
    protected function createHasManyRelation(
        string  $relatedEntity,
        ?string $foreignKey = null,
        array   $orderBy = [],
        string  $selfEntity = TestUser::class
    ): HasManyRelation {
        $relation = $this->createRelation(HasManyRelation::class, [
            'relatedEntity' => $relatedEntity,
            'foreignKey' => $foreignKey,
            'orderBy' => $orderBy,
        ]);
        $relation->bind($selfEntity, $relatedEntity);
        return $relation;
    }

    public function testConstructorWithExplicitForeignKey(): void
    {
        $relation = new HasManyRelation('RelatedEntity', 'user_id', ['created_at' => SORT_DESC]);

        $this->assertInstanceOf(HasManyRelation::class, $relation);
    }


    public function testGetRelatedQueryAppliesOrdering(): void
    {
        // Arrange
        $orderBy = ['created_at' => SORT_DESC, 'title' => SORT_ASC];
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id', $orderBy);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with($orderBy)
            ->willReturnSelf();

        // Act
        $result = $relation->getRelatedQuery();

        // Assert
        $this->assertSame($this->mockQuery, $result);
    }

    public function testEarlyLoadWithValidData(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        // Setup test data
        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 1, 'title' => 'Post 2'],
            ['post_id' => 3, 'user_id' => 2, 'title' => 'Post 3'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        // Event dispatcher should not be called (no orphaned records)
        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('posts', $result[0]);
        $this->assertArrayHasKey('posts', $result[1]);
        $this->assertCount(2, $result[0]['posts']);
        $this->assertCount(1, $result[1]['posts']);

        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
        $this->assertSame(1, $result[0]['posts'][0]->post_id);
        $this->assertSame(1, $result[0]['posts'][0]->user_id);
        $this->assertSame('Post 1', $result[0]['posts'][0]->title);
    }

    public function testEarlyLoadWithOrphanedRecordsDispatchesEvent(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        // Setup test data with orphaned records
        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 999, 'title' => 'Orphaned Post'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        // Event dispatcher should be called with orphaned record
        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'posts'
                    && $event->parentEntityClass === TestUser::class
                    && $event->relatedEntityClass === TestPost::class
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === [999]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 2;
            }));

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['posts']); // Only non-orphaned post
        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
        $this->assertSame(1, $result[0]['posts'][0]->post_id);
    }

    public function testEarlyLoadThrowsWhenLaterParentPrimaryKeyFieldMissing(): void
    {
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['name' => 'User 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->never())->method('whereIn');
        $this->mockQuery->expects($this->never())->method('fetch');
        $this->mockEventDispatcher->expects($this->never())->method('dispatch');

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field user_id in relation posts');

        $relation->earlyLoad($parentData, $this->mockQuery, 'posts');
    }

    public function testEarlyLoadMatchesStringForeignKeyValuesToIntParentsAndDispatchesStringOrphanCount(): void
    {
        // Arrange: entity foreign key is string-typed, while parent primary key values are ints.
        $relation = $this->createHasManyRelation(TestPostStringUserId::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $relatedData = [
            ['post_id' => 10, 'user_id' => '1', 'title' => 'Post 1'],
            ['post_id' => 11, 'user_id' => '2', 'title' => 'Post 2'],
            ['post_id' => 12, 'user_id' => '999', 'title' => 'Orphan A'],
            ['post_id' => 13, 'user_id' => '999', 'title' => 'Orphan B'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        // Event should report all orphaned rows (duplicates preserved).
        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'posts'
                    && $event->parentEntityClass === TestUser::class
                    && $event->relatedEntityClass === TestPostStringUserId::class
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === ['999', '999']
                    && $event->orphanedCount === 2
                    && $event->totalRelatedRecords === 4;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertInstanceOf(TestPostStringUserId::class, $result[0]['posts'][0]);
        $this->assertSame('1', $result[0]['posts'][0]->user_id);

        $this->assertCount(1, $result[1]['posts']);
        $this->assertInstanceOf(TestPostStringUserId::class, $result[1]['posts'][0]);
        $this->assertSame('2', $result[1]['posts'][0]->user_id);
    }

    public function testEarlyLoadThrowsExceptionWhenForeignKeyMissing(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        // Related data missing foreign key field
        $relatedData = [
            ['post_id' => 1, 'title' => 'Post 1'], // Missing user_id
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad($parentData, $this->mockQuery, 'posts');
    }

    public function testEarlyLoadTreatsNullForeignKeyValueAsPresentFieldNotMissingField(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $relatedData = [
            ['post_id' => 10, 'user_id' => null, 'title' => 'Orphan null fk'],
            ['post_id' => 11, 'user_id' => 1, 'title' => 'Matched'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        // Null foreign key row should be treated as orphaned data inconsistency, not missing field exception.
        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'posts'
                    && $event->orphanedForeignKeyValues === [null]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 2;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
        $this->assertSame(11, $result[0]['posts'][0]->post_id);
    }

    public function testEarlyLoadSetsEmptyArrayForParentsWithNoRelatedRecords(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        // Only user 1 has posts
        $relatedData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(0, $result[1]['posts']); // Empty array for user 2
    }

    public function testEarlyLoadWithEmptyFetchReturnsEmptyArraysForAllParents(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([]);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(2, $result);
        $this->assertSame([], $result[0]['posts']);
        $this->assertSame([], $result[1]['posts']);
    }

    public function testEarlyLoadWithEmptyParentBatchReturnsEmptyArray(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([]);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertSame([], $result);
    }

    public function testLazyLoadCreatesQueryWithCorrectConditions(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id', ['created_at' => SORT_DESC]);

        // Create mock entity
        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['created_at' => SORT_DESC])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['user_id' => 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadMatchesZeroSelfPrimaryKeyValuesCorrectly(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id', ['created_at' => SORT_DESC]);

        // Create mock entity with self primary key = 0
        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 0;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['created_at' => SORT_DESC])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['user_id' => 0])
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

    public function testEarlyLoadWithLargeDataset(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        // Large dataset: 100 users
        $parentData = [];
        for ($i = 1; $i <= 100; $i++) {
            $parentData[] = ['user_id' => $i, 'name' => "User $i"];
        }

        // 500 posts distributed across users
        $relatedData = [];
        for ($i = 1; $i <= 500; $i++) {
            $userId = (($i - 1) % 100) + 1; // Distribute evenly
            $relatedData[] = ['post_id' => $i, 'user_id' => $userId, 'title' => "Post $i"];
        }

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        // No orphaned records
        $this->mockEventDispatcher->expects($this->never())->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        $this->assertCount(100, $result);
        // Each user should have 5 posts
        foreach ($result as $user) {
            $this->assertCount(5, $user['posts']);
        }

        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
    }

    public function testEarlyLoadWithMultipleOrphanedRecords(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        // Multiple orphaned records
        $relatedData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 999, 'title' => 'Orphaned 1'],
            ['post_id' => 3, 'user_id' => 888, 'title' => 'Orphaned 2'],
            ['post_id' => 4, 'user_id' => 777, 'title' => 'Orphaned 3'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        // Event should be dispatched with all orphaned IDs
        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->orphanedForeignKeyValues === [999, 888, 777]
                    && $event->orphanedCount === 3
                    && $event->totalRelatedRecords === 4;
            }));

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['posts']); // Only non-orphaned post
    }

    public function testEarlyLoadDispatchesEventWithDuplicateOrphanedForeignKeyValues(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 999, 'title' => 'Orphaned 1'],
            ['post_id' => 2, 'user_id' => 999, 'title' => 'Orphaned 2'],
            ['post_id' => 3, 'user_id' => 888, 'title' => 'Orphaned 3'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('user_id');

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'posts'
                    && $event->orphanedForeignKeyValues === [999, 999, 888]
                    && $event->orphanedCount === 3
                    && $event->totalRelatedRecords === 3;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert: parent batch preserved, posts array is empty (all related rows are orphans)
        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]['posts']);
    }

    public function testEarlyLoadMapsZeroPrimaryKeyParentsCorrectly(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 0, 'name' => 'User 0'],
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 0, 'title' => 'Post 0'],
            ['post_id' => 2, 'user_id' => 1, 'title' => 'Post 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [0, 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(1, $result[1]['posts']);

        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
        $this->assertSame(0, $result[0]['posts'][0]->user_id);
        $this->assertSame(1, $result[1]['posts'][0]->user_id);
    }

    public function testEarlyLoadDispatchesEventWhenOrphanForeignKeyValueIsZero(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 0, 'title' => 'Orphaned User 0'],
            ['post_id' => 2, 'user_id' => 2, 'title' => 'Post 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'posts'
                    && $event->orphanedForeignKeyValues === [0]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 2;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(2, $result);
        $this->assertSame([], $result[0]['posts']); // user_id=1 has no valid related rows
        $this->assertCount(1, $result[1]['posts']);
        $this->assertInstanceOf(TestPost::class, $result[1]['posts'][0]);
        $this->assertSame(2, $result[1]['posts'][0]->user_id);
    }

    public function testEarlyLoadMatchesStringPrimaryKeyValues(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => '1', 'name' => 'User 1'],
            ['user_id' => '2', 'name' => 'User 2'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 2, 'title' => 'Post 2'],
            ['post_id' => 3, 'user_id' => 3, 'title' => 'Orphaned Post'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', ['1', '2'])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'posts'
                    && $event->orphanedForeignKeyValues === [3]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 3;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(1, $result[1]['posts']);
        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
        $this->assertSame(1, $result[0]['posts'][0]->user_id);
        $this->assertSame(2, $result[1]['posts'][0]->user_id);
    }

    public function testEarlyLoadWithDuplicatePrimaryKeyParentsAttachesToEachParent(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPost::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1A'],
            ['user_id' => 1, 'name' => 'User 1B'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('user_id');

        // HasManyRelation::earlyLoad does not de-duplicate parent ids.
        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert: duplicate parent rows should each receive the related records
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(1, $result[1]['posts']);
        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
        $this->assertInstanceOf(TestPost::class, $result[1]['posts'][0]);
        $this->assertSame(1, $result[0]['posts'][0]->post_id);
        $this->assertSame(1, $result[1]['posts'][0]->post_id);
    }

    public function testEarlyLoadMatchesNumericStringForeignKeyValues(): void
    {
        // Arrange
        $relation = $this->createHasManyRelation(TestPostStringUserId::class, 'user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => '1', 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => '2', 'title' => 'Post 2'],
            ['post_id' => 3, 'user_id' => '3', 'title' => 'Orphaned Post'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'posts'
                    && $event->orphanedForeignKeyValues === ['3']
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 3;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        // Assert
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(1, $result[1]['posts']);
        $this->assertInstanceOf(TestPostStringUserId::class, $result[0]['posts'][0]);
        $this->assertSame('1', $result[0]['posts'][0]->user_id);
        $this->assertSame('2', $result[1]['posts'][0]->user_id);
    }

    public function testEarlyLoadAutoInfersForeignKeyFromReferencedKey(): void
    {
        // Arrange - foreignKey is null, should be auto-inferred
        $relation = $this->createHasManyRelation(TestPost::class, null, [], TestUserId::class);

        $parentData = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
        ];

        $relatedData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 2, 'title' => 'Post 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUserId::class)
            ->willReturn('id');

        $this->mockEntityMetadata->expects($this->once())
            ->method('getReferencedKey')
            ->with(TestUserId::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        // No orphaned records
        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'posts');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(1, $result[1]['posts']);

        $this->assertInstanceOf(TestPost::class, $result[0]['posts'][0]);
        $this->assertSame(1, $result[0]['posts'][0]->post_id);
    }

    public function testLazyLoadAutoInfersForeignKeyFromReferencedKey(): void
    {
        // Arrange - foreignKey is null, should be auto-inferred
        $relation = $this->createHasManyRelation(TestPost::class, null, [], TestUserId::class);

        // Create mock entity
        $mockEntity = $this->createMock(TestUserId::class);
        $mockEntity->id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUserId::class)
            ->willReturn('id');

        $this->mockEntityMetadata->expects($this->once())
            ->method('getReferencedKey')
            ->with(TestUserId::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['user_id' => 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }
}
