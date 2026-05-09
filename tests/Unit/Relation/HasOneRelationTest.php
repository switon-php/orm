<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\HasOneRelation;
use Switon\Orm\Tests\Fixtures\TestProfile;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\Fixtures\TestUserId;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class HasOneRelationTest extends TestCase
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
     * Create a HasOneRelation with dependencies autowired and entity classes bound.
     */
    protected function createHasOneRelation(
        ?string $foreignKey = null,
        string  $selfEntity = TestUser::class,
        string  $relatedEntity = TestProfile::class
    ): HasOneRelation
    {
        $relation = $this->createRelation(HasOneRelation::class, [
            'foreignKey' => $foreignKey,
        ]);
        $relation->bind($selfEntity, $relatedEntity);
        return $relation;
    }

    public function testConstructorWithExplicitForeignKey(): void
    {
        $relation = new HasOneRelation('user_id');

        $this->assertInstanceOf(HasOneRelation::class, $relation);
    }


    public function testEarlyLoadWithValidData(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        // Setup test data
        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
            ['user_id' => 3, 'name' => 'User 3'],
        ];

        $relatedData = [
            1 => ['profile_id' => 1, 'user_id' => 1, 'bio' => 'Bio 1'],
            2 => ['profile_id' => 2, 'user_id' => 2, 'bio' => 'Bio 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2, 3])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'profile');

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('profile', $result[0]);
        $this->assertArrayHasKey('profile', $result[1]);
        $this->assertArrayHasKey('profile', $result[2]);
        $this->assertInstanceOf(TestProfile::class, $result[0]['profile']);
        $this->assertInstanceOf(TestProfile::class, $result[1]['profile']);
        $this->assertNull($result[2]['profile']); // User 3 has no profile
    }

    public function testEarlyLoadMatchesZeroSelfPrimaryKeyValuesCorrectly(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        // Duplicate self primary key = 0 in parent batch
        $parentData = [
            ['user_id' => 0, 'name' => 'User 0A'],
            ['user_id' => 0, 'name' => 'User 0B'],
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $relatedData = [
            0 => ['profile_id' => 10, 'user_id' => 0, 'bio' => 'Bio 0'],
            1 => ['profile_id' => 11, 'user_id' => 1, 'bio' => 'Bio 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', $this->callback(static function (array $ids): bool {
                // array_unique keeps original array keys, so assert by values only.
                return count($ids) === 2
                    && in_array(0, $ids, true)
                    && in_array(1, $ids, true);
            })) // array_unique must keep 0
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        // Act
        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'profile');

        // Assert
        $this->assertCount(3, $result);
        $this->assertInstanceOf(TestProfile::class, $result[0]['profile']);
        $this->assertInstanceOf(TestProfile::class, $result[1]['profile']);
        $this->assertInstanceOf(TestProfile::class, $result[2]['profile']);
        $this->assertSame(0, $result[0]['profile']->user_id);
        $this->assertSame(0, $result[1]['profile']->user_id);
        $this->assertSame(1, $result[2]['profile']->user_id);
    }

    public function testEarlyLoadSetsNullForParentsWithNoRelatedEntity(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        // No profiles exist
        $relatedData = [];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'profile');

        $this->assertCount(2, $result);
        $this->assertNull($result[0]['profile']);
        $this->assertNull($result[1]['profile']);
    }

    public function testEarlyLoadThrowsWhenRelatedForeignKeyFieldMissing(): void
    {
        $relation = $this->createHasOneRelation('user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
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
                ['profile_id' => 1, 'bio' => 'Bio 1'],
            ]);

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field user_id in relation profile');

        $relation->earlyLoad($parentData, $this->mockQuery, 'profile');
    }

    public function testEarlyLoadThrowsWhenLaterParentPrimaryKeyFieldMissing(): void
    {
        $relation = $this->createHasOneRelation('user_id');

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['name' => 'User 2'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockQuery->expects($this->never())->method('whereIn');
        $this->mockQuery->expects($this->never())->method('indexBy');
        $this->mockQuery->expects($this->never())->method('fetch');

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field user_id in relation profile');

        $relation->earlyLoad($parentData, $this->mockQuery, 'profile');
    }

    public function testEarlyLoadHandlesDuplicateParentIds(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        // Duplicate user_id in parent data (shouldn't happen normally, but test it)
        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 1, 'name' => 'User 1 Duplicate'],
        ];

        $relatedData = [
            1 => ['profile_id' => 1, 'user_id' => 1, 'bio' => 'Bio 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')
            ->with('user_id', [1]) // Should deduplicate to single ID
            ->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'profile');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestProfile::class, $result[0]['profile']);
        $this->assertInstanceOf(TestProfile::class, $result[1]['profile']);
    }

    public function testLazyLoadCreatesQueryWithCorrectConditions(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        // Create mock entity
        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 1;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestProfile::class)
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

    public function testLazyLoadMatchesZeroSelfPrimaryKeyValuesCorrectly(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        $mockEntity = $this->createMock(TestUser::class);
        $mockEntity->user_id = 0;

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestUser::class)
            ->willReturn('user_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestProfile::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['user_id' => 0])
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

    public function testEarlyLoadWithEmptyParentData(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        $parentData = [];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')
            ->with('user_id', []) // Empty array
            ->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([]);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'profile');

        $this->assertCount(0, $result);
    }

    public function testEarlyLoadMapsCorrectlyByForeignKey(): void
    {
        // Arrange
        $relation = $this->createHasOneRelation('user_id');

        $parentData = [
            ['user_id' => 10, 'name' => 'User 10'],
            ['user_id' => 20, 'name' => 'User 20'],
        ];

        $relatedData = [
            10 => ['profile_id' => 1, 'user_id' => 10, 'bio' => 'Bio 10'],
            20 => ['profile_id' => 2, 'user_id' => 20, 'bio' => 'Bio 20'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('user_id');
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'profile');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestProfile::class, $result[0]['profile']);
        $this->assertInstanceOf(TestProfile::class, $result[1]['profile']);
    }

    public function testEarlyLoadAutoInfersForeignKeyFromReferencedKey(): void
    {
        // Arrange - foreignKey is null, should be auto-inferred
        $relation = $this->createHasOneRelation(null, TestUserId::class, TestProfile::class);

        $parentData = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
        ];

        $relatedData = [
            1 => ['profile_id' => 1, 'user_id' => 1, 'bio' => 'Bio 1'],
            2 => ['profile_id' => 2, 'user_id' => 2, 'bio' => 'Bio 2'],
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
            ->method('indexBy')
            ->with('user_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'profile');

        $this->assertInstanceOf(TestProfile::class, $result[0]['profile']);
        $this->assertInstanceOf(TestProfile::class, $result[1]['profile']);
    }

    public function testLazyLoadAutoInfersForeignKeyFromReferencedKey(): void
    {
        // Arrange - foreignKey is null, should be auto-inferred
        $relation = $this->createHasOneRelation(null, TestUserId::class, TestProfile::class);

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
            ->with(TestProfile::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with(['user_id' => 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(false)
            ->willReturnSelf();

        $result = $relation->lazyLoad($mockEntity);

        $this->assertSame($this->mockQuery, $result);
    }
}
