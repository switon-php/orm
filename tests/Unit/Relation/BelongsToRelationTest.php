<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\BelongsToRelation;
use Switon\Orm\Tests\Fixtures\TestPost;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;
use AllowDynamicProperties;
use ErrorException;

#[AllowMockObjectsWithoutExpectations]
class BelongsToRelationTest extends TestCase
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
     * Create a BelongsToRelation with dependencies autowired and entity classes bound.
     */
    protected function createBelongsToRelation(
        ?string $foreignKey = null,
        string  $selfEntity = TestPost::class,
        string  $relatedEntity = TestUser::class
    ): BelongsToRelation {
        $relation = $this->createRelation(BelongsToRelation::class, [
            'foreignKey' => $foreignKey,
        ]);
        $relation->bind($selfEntity, $relatedEntity);
        return $relation;
    }

    public function testConstructorWithExplicitForeignKey(): void
    {
        $relation = new BelongsToRelation('user_id');

        $this->assertInstanceOf(BelongsToRelation::class, $relation);
    }


    public function testGetForeignKeyReturnsCorrectValue(): void
    {
        $relation = new BelongsToRelation('user_id');

        $this->assertEquals('user_id', $relation->getForeignKey());
    }

    public function testEarlyLoadWithValidData(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        // Setup test data
        $childData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 2, 'title' => 'Post 2'],
            ['post_id' => 3, 'user_id' => 1, 'title' => 'Post 3'],
        ];

        $parentData = [
            1 => ['user_id' => 1, 'name' => 'User 1'],
            2 => ['user_id' => 2, 'name' => 'User 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($parentData);

        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('user', $result[0]);
        $this->assertArrayHasKey('user', $result[1]);
        $this->assertArrayHasKey('user', $result[2]);
        $this->assertInstanceOf(TestUser::class, $result[0]['user']);
        $this->assertInstanceOf(TestUser::class, $result[1]['user']);
        $this->assertInstanceOf(TestUser::class, $result[2]['user']);
    }

    public function testGetForeignKeyAutoInfersFromReferencedKey(): void
    {
        // Arrange - foreignKey is null, should be auto-inferred
        $relation = $this->createBelongsToRelation();

        $this->mockEntityMetadata->expects($this->once())
            ->method('getReferencedKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->assertSame('user_id', $relation->getForeignKey());

        // Second call should use cached value
        $this->assertSame('user_id', $relation->getForeignKey());
    }

    public function testEarlyLoadAutoInfersForeignKeyFromReferencedKey(): void
    {
        // Arrange - foreignKey is null, should be auto-inferred
        $relation = $this->createBelongsToRelation();

        // Setup test data
        $childData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 2, 'title' => 'Post 2'],
            ['post_id' => 3, 'user_id' => 1, 'title' => 'Post 3'],
        ];

        $parentData = [
            1 => ['user_id' => 1, 'name' => 'User 1'],
            2 => ['user_id' => 2, 'name' => 'User 2'],
        ];

        $this->mockEntityMetadata->expects($this->once())
            ->method('getReferencedKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($parentData);

        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        $this->assertCount(3, $result);
        $this->assertInstanceOf(TestUser::class, $result[0]['user']);
        $this->assertInstanceOf(TestUser::class, $result[1]['user']);
        $this->assertInstanceOf(TestUser::class, $result[2]['user']);
    }

    public function testEarlyLoadSetsNullWhenForeignKeyValueIsNull(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        $childData = [
            ['post_id' => 1, 'user_id' => null, 'title' => 'No author'],
            ['post_id' => 2, 'user_id' => 1, 'title' => 'Post 1'],
        ];

        $parentData = [
            1 => ['user_id' => 1, 'name' => 'User 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with(
                'user_id',
                $this->callback(static fn (array $ids): bool => in_array(null, $ids, true) && in_array(1, $ids, true))
            )
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($parentData);

        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        $this->assertNull($result[0]['user']);
        $this->assertInstanceOf(TestUser::class, $result[1]['user']);
    }

    public function testEarlyLoadUsesPrimaryKeyOnRelatedEntityEvenWhenForeignKeyIsInferredFromReferencedKey(): void
    {
        // Arrange - foreignKey is null, should be auto-inferred
        $relation = $this->createBelongsToRelation();

        $childData = [
            ['post_id' => 1, 'custom_ref_id' => 10],
            ['post_id' => 2, 'custom_ref_id' => 11],
        ];

        $parentData = [
            10 => ['user_id' => 10, 'name' => 'User 10'],
            11 => ['user_id' => 11, 'name' => 'User 11'],
        ];

        $this->mockEntityMetadata->expects($this->once())
            ->method('getReferencedKey')
            ->with(TestUser::class)
            ->willReturn('custom_ref_id');

        $this->mockEntityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($parentData);

        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestUser::class, $result[0]['user']);
        $this->assertInstanceOf(TestUser::class, $result[1]['user']);
    }

    public function testEarlyLoadSetsNullForMissingParent(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        // Child references user_id 999 which doesn't exist
        $childData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 999, 'title' => 'Orphaned Post'],
        ];

        $parentData = [
            1 => ['user_id' => 1, 'name' => 'User 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($parentData);

        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestUser::class, $result[0]['user']);
        $this->assertNull($result[1]['user']); // Orphaned post has null user
    }

    public function testEarlyLoadThrowsWhenRelatedPrimaryKeyFieldMissing(): void
    {
        $relation = $this->createBelongsToRelation('user_id');

        $childData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['name' => 'User 1'],
            ]);

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field user_id in relation user');

        $relation->earlyLoad($childData, $this->mockQuery, 'user');
    }

    public function testEarlyLoadThrowsWhenLaterChildForeignKeyFieldMissing(): void
    {
        $relation = $this->createBelongsToRelation('user_id');

        $childData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'title' => 'Post 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->never())->method('whereIn');
        $this->mockQuery->expects($this->never())->method('indexBy');
        $this->mockQuery->expects($this->never())->method('fetch');

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field user_id in relation user');

        $relation->earlyLoad($childData, $this->mockQuery, 'user');
    }

    public function testEarlyLoadHandlesDuplicateForeignKeys(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        // Multiple posts by same user
        $childData = [
            ['post_id' => 1, 'user_id' => 1, 'title' => 'Post 1'],
            ['post_id' => 2, 'user_id' => 1, 'title' => 'Post 2'],
            ['post_id' => 3, 'user_id' => 1, 'title' => 'Post 3'],
        ];

        $parentData = [
            1 => ['user_id' => 1, 'name' => 'User 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')
            ->with('user_id', [1]) // Should only query once for user_id 1
            ->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($parentData);

        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        $this->assertCount(3, $result);
        // All posts should reference the same user
        $this->assertInstanceOf(TestUser::class, $result[0]['user']);
        $this->assertInstanceOf(TestUser::class, $result[1]['user']);
        $this->assertInstanceOf(TestUser::class, $result[2]['user']);
    }

    public function testLazyLoadCreatesQueryWithCorrectConditions(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        // Create mock entity
        $mockEntity = $this->createMock(TestPost::class);
        $mockEntity->user_id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestUser::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['user_id' => 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(false) // Single entity, not array
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testEarlyLoadWithEmptyChildData(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        $childData = [];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')
            ->with('user_id', []) // Empty array
            ->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([]);

        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        $this->assertCount(0, $result);
    }

    public function testEarlyLoadMatchesZeroForeignKeyValuesCorrectly(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        $childData = [
            ['post_id' => 1, 'user_id' => 0, 'title' => 'Post 1'],
        ];

        $parentData = [
            0 => ['user_id' => 0, 'name' => 'User 0'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [0])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($parentData);

        // Act
        $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');

        // Assert: foreign key value 0 must not be treated as null.
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TestUser::class, $result[0]['user']);
        $this->assertSame(0, $result[0]['user']->user_id);
    }

    public function testLazyLoadBuildsWhereWithNullForeignKeyValue(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        $mockEntity = new BelongsToRelationLazyLoadNullFkEntity(['user_id' => null]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestUser::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['user_id' => null])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(false) // Single entity, not array
            ->willReturnSelf();

        // Act
        $result = $relation->lazyLoad($mockEntity);

        // Assert
        $this->assertSame($this->mockQuery, $result);
    }

    public function testEarlyLoadWithNullForeignKeyDoesNotEmitDeprecation(): void
    {
        // Arrange
        $relation = $this->createBelongsToRelation('user_id');

        $childData = [
            ['post_id' => 1, 'user_id' => null, 'title' => 'No author'],
            ['post_id' => 2, 'user_id' => 1, 'title' => 'Post 1'],
        ];

        $parentData = [
            1 => ['user_id' => 1, 'name' => 'User 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with(
                'user_id',
                $this->callback(static fn (array $ids): bool => in_array(null, $ids, true) && in_array(1, $ids, true))
            )
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($parentData);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ($severity === E_DEPRECATED) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
            return false;
        });

        try {
            // Act
            $result = $relation->earlyLoad($childData, $this->mockQuery, 'user');
        } finally {
            restore_error_handler();
        }

        // Assert
        $this->assertNull($result[0]['user']);
        $this->assertInstanceOf(TestUser::class, $result[1]['user']);
    }
}

/**
 * Test-only entity stub for BelongsToRelation lazyLoad null foreign key.
 *
 * @internal
 */
#[AllowDynamicProperties]
class BelongsToRelationLazyLoadNullFkEntity extends Entity
{
}
