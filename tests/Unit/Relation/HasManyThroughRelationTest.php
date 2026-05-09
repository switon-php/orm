<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Event\RelationDataInconsistency;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\HasManyThroughRelation;
use Switon\Orm\RepositoryInterface;
use Switon\Orm\Tests\Fixtures\TestComment;
use Switon\Orm\Tests\Fixtures\TestPost;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class HasManyThroughRelationTest extends TestCase
{
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|EventDispatcherInterface $mockEventDispatcher;
    protected MockObject|QueryInterface $mockQuery;
    protected MockObject|QueryInterface $mockThroughQuery;
    protected MockObject|RepositoryInterface $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);
        $this->mockThroughQuery = $this->createMock(QueryInterface::class);
        $this->mockRepository = $this->createMock(RepositoryInterface::class);

        // Replace dependencies in container for autowiring
        $this->container->replace(EntityMetadataInterface::class, $this->mockEntityMetadata);
        $this->container->replace(EventDispatcherInterface::class, $this->mockEventDispatcher);
    }

    /**
     * Create a HasManyThroughRelation with dependencies autowired and entity classes bound.
     */
    protected function createHasManyThroughRelation(
        string  $targetEntity,
        string  $throughEntity,
        ?string $firstKey = null,
        ?string $secondKey = null,
        array   $orderBy = [],
        string  $selfEntity = TestUser::class
    ): HasManyThroughRelation
    {
        $relation = $this->createRelation(HasManyThroughRelation::class, [
            'targetEntity' => $targetEntity,
            'throughEntity' => $throughEntity,
            'firstKey' => $firstKey,
            'secondKey' => $secondKey,
            'orderBy' => $orderBy,
        ]);
        $relation->bind($selfEntity, $targetEntity);
        return $relation;
    }

    public function testConstructorWithExplicitKeys(): void
    {
        $relation = new HasManyThroughRelation(
            'Comment',
            'Post',
            'user_id',
            'post_id',
            ['created_at' => SORT_DESC]
        );

        $this->assertInstanceOf(HasManyThroughRelation::class, $relation);
    }


    public function testGetRelatedQueryAppliesOrdering(): void
    {
        // Arrange
        $orderBy = ['created_at' => SORT_DESC];
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id', $orderBy);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestComment::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with($orderBy)
            ->willReturnSelf();

        $result = $relation->getRelatedQuery();

        $this->assertSame($this->mockQuery, $result);
    }

    public function testEarlyLoadWithValidData(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        // Setup test data
        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $throughData = [
            ['post_id' => 1, 'user_id' => 1],
            ['post_id' => 2, 'user_id' => 1],
            ['post_id' => 3, 'user_id' => 2],
        ];

        $relatedData = [
            ['comment_id' => 1, 'post_id' => 1, 'content' => 'Comment 1'],
            ['comment_id' => 2, 'post_id' => 1, 'content' => 'Comment 2'],
            ['comment_id' => 3, 'post_id' => 2, 'content' => 'Comment 3'],
            ['comment_id' => 4, 'post_id' => 3, 'content' => 'Comment 4'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($entityClass) {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [1, 2, 3])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        // Event dispatcher should not be called (no orphaned records)
        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('comments', $result[0]);
        $this->assertArrayHasKey('comments', $result[1]);
        $this->assertCount(3, $result[0]['comments']); // User 1 has 3 comments through 2 posts
        $this->assertCount(1, $result[1]['comments']); // User 2 has 1 comment through 1 post
    }

    public function testEarlyLoadWithEmptyThroughEntities(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockEntityMetadata->method('createQuery')->willReturn($this->mockThroughQuery);
        $this->mockThroughQuery->method('whereIn')->willReturnSelf();
        $this->mockThroughQuery->method('execute')->willReturn([]); // No through entities

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('comments', $result[0]);
        $this->assertCount(0, $result[0]['comments']); // Empty array
    }

    /**
     * Empty parent batch: through query runs with empty IN list; result stays empty.
     */
    public function testEarlyLoadWithEmptyParentRows(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [])
            ->willReturnSelf();
        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $this->mockEventDispatcher->expects($this->never())->method('dispatch');

        $result = $relation->earlyLoad([], $this->mockQuery, 'comments');

        $this->assertSame([], $result);
    }

    /**
     * Duplicate self primary key in the parent batch should attach the same through targets to each row.
     */
    public function testEarlyLoadDuplicateParentSelfIdsAttachToEachParentRow(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1 - A'],
            ['user_id' => 1, 'name' => 'User 1 - B'],
        ];

        $throughData = [
            ['post_id' => 1, 'user_id' => 1],
        ];

        $relatedData = [
            ['comment_id' => 1, 'post_id' => 1, 'content' => 'Only'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->method('whereIn')->willReturnSelf();
        $this->mockThroughQuery->method('execute')->willReturn($throughData);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['comments']);
        $this->assertCount(1, $result[1]['comments']);
        $this->assertSame(1, $result[0]['comments'][0]->comment_id);
        $this->assertSame(1, $result[1]['comments'][0]->comment_id);
    }

    public function testEarlyLoadAutoInfersFirstKeyAndSecondKeyFromReferencedKey(): void
    {
        // Arrange - keys are null, should be auto-inferred
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $throughData = [
            ['post_id' => 10, 'custom_first' => 1],
            ['post_id' => 11, 'custom_first' => 2],
        ];

        $relatedData = [
            ['comment_id' => 1, 'post_id' => 10, 'content' => 'Comment 1'],
            ['comment_id' => 2, 'post_id' => 11, 'content' => 'Comment 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->expects($this->exactly(2))
            ->method('getReferencedKey')
            ->willReturnMap([
                [TestUser::class, 'custom_first'],
                [TestPost::class, 'post_id'],
            ]);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, ['post_id', 'custom_first'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('custom_first', [1, 2])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['comments']);
        $this->assertCount(1, $result[1]['comments']);
    }

    public function testEarlyLoadWithOrphanedRecordsDispatchesEvent(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        // Through data includes orphaned record
        $throughData = [
            ['post_id' => 1, 'user_id' => 1],
            ['post_id' => 2, 'user_id' => 999], // Orphaned
        ];

        $relatedData = [
            ['comment_id' => 1, 'post_id' => 1, 'content' => 'Comment 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($entityClass) {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')->willReturn($this->mockThroughQuery);
        $this->mockThroughQuery->method('whereIn')->willReturnSelf();
        $this->mockThroughQuery->method('execute')->willReturn($throughData);
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        // Event dispatcher should be called with orphaned record
        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'comments'
                    && $event->parentEntityClass === TestUser::class
                    && $event->relatedEntityClass === TestComment::class
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === [999]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 2;
            }));

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(1, $result);
    }

    public function testEarlyLoadMapsNumericStringThroughSelfIdToIntParentAndDispatchesOnlyNumericOrphans(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $throughData = [
            ['post_id' => 10, 'user_id' => '1'], // valid: numeric-string self id matches int parent
            ['post_id' => 11, 'user_id' => '999'], // orphan
        ];

        $relatedData = [
            ['comment_id' => 100, 'post_id' => 10, 'content' => 'C100'],
            ['comment_id' => 101, 'post_id' => 11, 'content' => 'C101'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($entityClass) {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'comments'
                    && $event->parentEntityClass === TestUser::class
                    && $event->relatedEntityClass === TestComment::class
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === [999]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 2;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert
        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['comments']);
        $this->assertSame(100, $result[0]['comments'][0]->comment_id);
    }

    public function testEarlyLoadDispatchesOrphanEventWithNonNumericStringOrphanSelfIdDistinctCount(): void
    {
        // Arrange: one valid through row (self id matches parent) + duplicate orphan through rows with non-numeric self id.
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $throughData = [
            ['post_id' => 10, 'user_id' => '1'], // valid via numeric-string self id
            ['post_id' => 11, 'user_id' => 'u999'], // orphan
            ['post_id' => 12, 'user_id' => 'u999'], // orphan duplicate self id
        ];

        $relatedData = [
            ['comment_id' => 100, 'post_id' => 10, 'content' => 'C10'],
            ['comment_id' => 101, 'post_id' => 11, 'content' => 'C11'],
            ['comment_id' => 102, 'post_id' => 12, 'content' => 'C12'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($entityClass) {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        // relatedQuery should query all distinct through ids.
        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10, 11, 12])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'comments'
                    && $event->parentEntityClass === TestUser::class
                    && $event->relatedEntityClass === TestComment::class
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === ['u999']
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 3;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert: only comments from through rows belonging to user_id=1 should attach.
        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['comments']);
        $this->assertSame(100, $result[0]['comments'][0]->comment_id);
    }

    public function testEarlyLoadWithDuplicateOrphanedThroughEntitiesDispatchesDistinctOrphanCount(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        // Duplicate orphaned self IDs (user_id=999) for multiple through entities (post_id=2,3).
        $throughData = [
            ['post_id' => 1, 'user_id' => 1],
            ['post_id' => 2, 'user_id' => 999],
            ['post_id' => 3, 'user_id' => 999],
        ];

        $relatedData = [
            ['comment_id' => 1, 'post_id' => 1, 'content' => 'Comment 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($entityClass) {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        // throughQuery: user_id IN (1)
        $this->mockEntityMetadata->method('createQuery')->willReturn($this->mockThroughQuery);
        $this->mockThroughQuery->method('whereIn')->willReturnSelf();
        $this->mockThroughQuery->method('execute')->willReturn($throughData);

        // relatedQuery: post_id IN (1,2,3)
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        // Orphan detection groups by orphan self ID, so orphanedCount should be 1 (999), not 2.
        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'comments'
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === [999]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 3;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert
        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['comments']);
    }

    public function testEarlyLoadThrowsExceptionWhenSecondKeyMissing(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [['user_id' => 1]];
        $throughData = [['post_id' => 1, 'user_id' => 1]];
        $relatedData = [['comment_id' => 1, 'content' => 'Comment 1']]; // Missing post_id

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockEntityMetadata->method('createQuery')->willReturn($this->mockThroughQuery);
        $this->mockThroughQuery->method('whereIn')->willReturnSelf();
        $this->mockThroughQuery->method('execute')->willReturn($throughData);
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad($parentData, $this->mockQuery, 'comments');
    }

    public function testEarlyLoadThrowsExceptionWhenThroughIdMissing(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [['user_id' => 1]];
        $throughData = [['user_id' => 1]]; // Missing post_id

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')->willReturn($this->mockThroughQuery);
        $this->mockThroughQuery->method('whereIn')->willReturnSelf();
        $this->mockThroughQuery->method('execute')->willReturn($throughData);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad($parentData, $this->mockQuery, 'comments');
    }

    public function testEarlyLoadMapsThroughEntitiesToParentWhenSelfIdIsZero(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 0, 'name' => 'User 0'],
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $throughData = [
            ['post_id' => 10, 'user_id' => 0],
            ['post_id' => 11, 'user_id' => 1],
        ];

        $relatedData = [
            ['comment_id' => 100, 'post_id' => 10, 'content' => 'C100'],
            ['comment_id' => 101, 'post_id' => 11, 'content' => 'C101'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [0, 1])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['comments']);
        $this->assertCount(1, $result[1]['comments']);
        $this->assertSame(100, $result[0]['comments'][0]->comment_id);
        $this->assertSame(101, $result[1]['comments'][0]->comment_id);
    }

    public function testEarlyLoadDispatchesOrphanEventWhenThroughSelfIdIsZeroOrphan(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        // Through entity belongs to user_id=0 which is not in parent batch => orphan
        $throughData = [
            ['post_id' => 10, 'user_id' => 0],
        ];

        $relatedData = [
            // keep empty to avoid field checks
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'comments'
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === [0]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 1;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert
        $this->assertCount(1, $result);
        $this->assertCount(0, $result[0]['comments']);
    }

    public function testLazyLoadBuildsWhereInWithZeroSelfId(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 0;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestPost::class)
            ->willReturn($this->mockRepository);

        $this->mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 0], 'post_id')
            ->willReturn([10, 11]);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestComment::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10, 11])
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

    public function testLazyLoadCreatesQueryWithCorrectConditions(): void
    {
        // Arrange
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id', ['created_at' => SORT_DESC]);

        // Create mock entity
        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($entityClass) {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestPost::class)
            ->willReturn($this->mockRepository);

        $this->mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 1], 'post_id')
            ->willReturn([1, 2, 3]);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestComment::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['created_at' => SORT_DESC])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [1, 2, 3])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadWithEmptyThroughIdsStillBuildsWhereIn(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 42;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestPost::class)
            ->willReturn($this->mockRepository);

        $this->mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 42], 'post_id')
            ->willReturn([]);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestComment::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testEarlyLoadAttachesEmptyTargetArraysWhenRelatedQueryReturnsEmpty(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $throughData = [
            ['post_id' => 10, 'user_id' => 1],
            ['post_id' => 11, 'user_id' => 2],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([]);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(2, $result);
        $this->assertSame([], $result[0]['comments']);
        $this->assertSame([], $result[1]['comments']);
    }

    public function testEarlyLoadAttachesTargetWhenThroughIdIsZero(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $throughData = [
            ['post_id' => 0, 'user_id' => 1],
        ];

        $relatedData = [
            ['comment_id' => 100, 'post_id' => 0, 'content' => 'C100'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [0])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['comments']);
        $this->assertInstanceOf(TestComment::class, $result[0]['comments'][0]);
        $this->assertSame(100, $result[0]['comments'][0]->comment_id);
        $this->assertSame(0, $result[0]['comments'][0]->post_id);
    }

    public function testEarlyLoadDispatchesOrphanEventWithZeroAndNonZeroThroughSelfIds(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $throughData = [
            ['post_id' => 10, 'user_id' => 0],
            ['post_id' => 11, 'user_id' => 999],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([]);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'comments'
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === [0, 999]
                    && $event->orphanedCount === 2
                    && $event->totalRelatedRecords === 2;
            }));

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]['comments']);
    }

    public function testLazyLoadBuildsWhereInWithThroughIdsContainingZeroAndDuplicates(): void
    {
        $relation = $this->createHasManyThroughRelation(
            TestComment::class,
            TestPost::class,
            'user_id',
            'post_id',
            ['created_at' => SORT_DESC]
        );

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestPost::class)
            ->willReturn($this->mockRepository);

        $this->mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 1], 'post_id')
            ->willReturn([0, 0, 5]);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestComment::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['created_at' => SORT_DESC])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [0, 0, 5])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadBuildsWhereInWithEmptyThroughIdsStillAppliesOrderByAndFetchTypeTrue(): void
    {
        $relation = $this->createHasManyThroughRelation(
            TestComment::class,
            TestPost::class,
            'user_id',
            'post_id',
            ['created_at' => SORT_DESC]
        );

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 42;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestPost::class)
            ->willReturn($this->mockRepository);

        $this->mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 42], 'post_id')
            ->willReturn([]);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestComment::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['created_at' => SORT_DESC])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testEarlyLoadWithDuplicateThroughIdsPreservesTargetDuplicates(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $throughData = [
            ['post_id' => 10, 'user_id' => 1],
            ['post_id' => 10, 'user_id' => 1], // duplicate through_id for same parent
        ];

        $relatedData = [
            ['comment_id' => 100, 'post_id' => 10, 'content' => 'C100'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        // array_unique on through_id should keep only [10] for the whereIn
        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [10])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['comments']); // target entity merged twice
        $this->assertSame(100, $result[0]['comments'][0]->comment_id);
        $this->assertSame(100, $result[0]['comments'][1]->comment_id);
    }

    public function testEarlyLoadWithEmptyThroughEntitiesForMultipleParentsAttachesEmptyArraysForAllParents(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturnCallback(static function (string $entityClass): string {
            return match ($entityClass) {
                TestUser::class => 'user_id',
                TestPost::class => 'post_id',
                TestComment::class => 'comment_id',
                default => 'id',
            };
        });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn([]); // No through entities

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert
        $this->assertCount(2, $result);
        $this->assertSame([], $result[0]['comments']);
        $this->assertSame([], $result[1]['comments']);
        $this->mockEventDispatcher->expects($this->never())->method('dispatch');
    }

    public function testEarlyLoadIncludesZeroThroughIdInRelatedQueryWhereInAndAttachesCorrectComments(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $throughData = [
            ['post_id' => 0, 'user_id' => 1],
            ['post_id' => 5, 'user_id' => 2],
        ];

        $relatedData = [
            ['comment_id' => 1000, 'post_id' => 0, 'content' => 'C0'],
            ['comment_id' => 1001, 'post_id' => 5, 'content' => 'C5'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturnCallback(static function (string $entityClass): string {
            return match ($entityClass) {
                TestUser::class => 'user_id',
                TestPost::class => 'post_id',
                TestComment::class => 'comment_id',
                default => 'id',
            };
        });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [0, 5])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())->method('dispatch');

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['comments']);
        $this->assertSame(1000, $result[0]['comments'][0]->comment_id);
        $this->assertCount(1, $result[1]['comments']);
        $this->assertSame(1001, $result[1]['comments'][0]->comment_id);
    }

    public function testEarlyLoadWithEmptyParentRowsButThroughEntitiesExistDispatchesOrphanEventAndReturnsEmpty(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id');

        $parentData = []; // empty parent batch

        $throughData = [
            ['post_id' => 1, 'user_id' => 999], // orphan: selfId 999 not in parent batch
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturnCallback(static function (string $entityClass): string {
            return match ($entityClass) {
                TestUser::class => 'user_id',
                TestPost::class => 'post_id',
                TestComment::class => 'comment_id',
                default => 'id',
            };
        });

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class, ['post_id', 'user_id'])
            ->willReturn($this->mockThroughQuery);

        $this->mockThroughQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [])
            ->willReturnSelf();

        $this->mockThroughQuery->expects($this->once())
            ->method('execute')
            ->willReturn($throughData);

        // related entities empty; avoids field missing exception and mapping work
        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([]);

        $this->mockEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof RelationDataInconsistency
                    && $event->relationName === 'comments'
                    && $event->foreignKeyField === 'user_id'
                    && $event->orphanedForeignKeyValues === [999]
                    && $event->orphanedCount === 1
                    && $event->totalRelatedRecords === 1;
            }));

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'comments');

        // Assert
        $this->assertSame([], $result);
    }

    public function testLazyLoadAppliesOrderByEvenWhenOrderByIsEmpty(): void
    {
        $relation = $this->createHasManyThroughRelation(TestComment::class, TestPost::class, 'user_id', 'post_id', []);

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $entityClass): string {
                return match ($entityClass) {
                    TestUser::class => 'user_id',
                    TestPost::class => 'post_id',
                    TestComment::class => 'comment_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestPost::class)
            ->willReturn($this->mockRepository);

        $this->mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 1], 'post_id')
            ->willReturn([2, 3]);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestComment::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with([])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [2, 3])
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
}
