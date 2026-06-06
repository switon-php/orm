<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\MorphToRelation;
use Switon\Orm\Tests\Fixtures\TestPost;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class MorphToRelationTest extends TestCase
{
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|QueryInterface $mockPlaceholderQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockPlaceholderQuery = $this->createMock(QueryInterface::class);

        // Replace dependencies in container for autowiring
        $this->container->replace(EntityMetadataInterface::class, $this->mockEntityMetadata);
    }

    /**
     * Create a MorphToRelation with dependencies autowired, entity classes bound, and morphs configured.
     *
     * @param string[] $morphs The morph entity classes
     */
    protected function createMorphToRelation(
        string $tableField,
        string $idField,
        array  $morphs = []
    ): MorphToRelation {
        $relation = $this->createRelation(MorphToRelation::class, [
            'tableField' => $tableField,
            'idField' => $idField,
        ]);
        $relation->bind(Entity::class, '');
        if ($morphs !== []) {
            // morphs is an #[Autowired] property, need to inject it
            $this->injectRelationDependencies($relation, ['morphs' => $morphs]);
        }
        return $relation;
    }

    public function testEarlyLoadLoadsMultipleTypesAndMapsBack(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestPost::class => 'post_id',
                    TestUser::class => 'user_id',
                    default => 'id',
                };
            });

        $postQuery = $this->createMock(QueryInterface::class);
        $postQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [1, 2])
            ->willReturnSelf();
        $postQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['post_id' => 1, 'user_id' => 10, 'title' => 'P1'],
                ['post_id' => 2, 'user_id' => 10, 'title' => 'P2'],
            ]);

        $userQuery = $this->createMock(QueryInterface::class);
        $userQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [10])
            ->willReturnSelf();
        $userQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['user_id' => 10, 'name' => 'U10'],
            ]);

        $this->mockEntityMetadata->expects($this->exactly(2))
            ->method('createQuery')
            ->willReturnMap([
                [TestPost::class, [], $postQuery],
                [TestUser::class, [], $userQuery],
            ]);

        $entities = [
            ['owner_table' => 'posts', 'owner_id' => 1],
            ['owner_table' => 'posts', 'owner_id' => 2],
            ['owner_table' => 'users', 'owner_id' => 10],
            ['owner_table' => null, 'owner_id' => null],
            ['owner_table' => 'users', 'owner_id' => null],
        ];

        $result = $relation->earlyLoad($entities, $this->mockPlaceholderQuery, 'owner');

        $this->assertInstanceOf(TestPost::class, $result[0]['owner']);
        $this->assertInstanceOf(TestPost::class, $result[1]['owner']);
        $this->assertInstanceOf(TestUser::class, $result[2]['owner']);
        $this->assertNull($result[3]['owner']);
        $this->assertNull($result[4]['owner']);
    }

    public function testEarlyLoadAcceptsClassNameTypeValues(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_type', 'owner_id');

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, false, 'posts'],
            ]);
        $this->mockEntityMetadata->method('getConnection')
            ->with(TestPost::class)
            ->willReturn('default');
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestPost::class)
            ->willReturn([]);
        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPost::class)
            ->willReturn('post_id');

        $postQuery = $this->createMock(QueryInterface::class);
        $postQuery->method('setEntityClass')->willReturnSelf();
        $postQuery->method('setTable')->willReturnSelf();
        $postQuery->method('setColumnMap')->willReturnSelf();
        $postQuery->method('whereIn')->willReturnSelf();
        $postQuery->method('fetch')->willReturn([
            ['post_id' => 1, 'user_id' => 10, 'title' => 'P1'],
        ]);

        $entities = [
            ['owner_type' => TestPost::class, 'owner_id' => 1],
        ];

        $result = $relation->earlyLoad($entities, $postQuery, 'owner');

        $this->assertInstanceOf(TestPost::class, $result[0]['owner']);
    }

    public function testEarlyLoadNormalizesSchemaAndShardingTableTypeValues(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestPost::class => 'post_id',
                    TestUser::class => 'user_id',
                    default => 'id',
                };
            });

        $postQuery = $this->createMock(QueryInterface::class);
        $postQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [1, 2])
            ->willReturnSelf();
        $postQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['post_id' => 1, 'user_id' => 10, 'title' => 'P1'],
                ['post_id' => 2, 'user_id' => 10, 'title' => 'P2'],
            ]);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, [])
            ->willReturn($postQuery);

        $entities = [
            ['owner_table' => 'schema.posts:sharding', 'owner_id' => 1],
            ['owner_table' => 'schema.posts:sharding', 'owner_id' => 2],
        ];

        // Act
        $result = $relation->earlyLoad($entities, $this->mockPlaceholderQuery, 'owner');

        // Assert
        $this->assertInstanceOf(TestPost::class, $result[0]['owner']);
        $this->assertInstanceOf(TestPost::class, $result[1]['owner']);
    }

    public function testEarlyLoadDeduplicatesDuplicateOwnerIdsWithinSameType(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestPost::class => 'post_id',
                    TestUser::class => 'user_id',
                    default => 'id',
                };
            });

        $postQuery = $this->createMock(QueryInterface::class);
        $postQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [1, 2])
            ->willReturnSelf();
        $postQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['post_id' => 1, 'user_id' => 10, 'title' => 'P1'],
                ['post_id' => 2, 'user_id' => 10, 'title' => 'P2'],
            ]);

        $userQuery = $this->createMock(QueryInterface::class);
        $userQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [10])
            ->willReturnSelf();
        $userQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['user_id' => 10, 'name' => 'U10'],
            ]);

        $this->mockEntityMetadata->expects($this->exactly(2))
            ->method('createQuery')
            ->willReturnMap([
                [TestPost::class, [], $postQuery],
                [TestUser::class, [], $userQuery],
            ]);

        $entities = [
            ['owner_table' => 'posts', 'owner_id' => 1],
            ['owner_table' => 'posts', 'owner_id' => 1], // duplicate id
            ['owner_table' => 'posts', 'owner_id' => 2],
            ['owner_table' => 'users', 'owner_id' => 10],
            ['owner_table' => 'users', 'owner_id' => 10], // duplicate id
        ];

        // Act
        $result = $relation->earlyLoad($entities, $this->mockPlaceholderQuery, 'owner');

        // Assert
        $this->assertInstanceOf(TestPost::class, $result[0]['owner']);
        $this->assertInstanceOf(TestPost::class, $result[1]['owner']);
        $this->assertInstanceOf(TestPost::class, $result[2]['owner']);
        $this->assertInstanceOf(TestUser::class, $result[3]['owner']);
        $this->assertInstanceOf(TestUser::class, $result[4]['owner']);
    }

    public function testEarlyLoadIncludesZeroOwnerIdInWhereIn(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPost::class)
            ->willReturn('post_id');

        $postQuery = $this->createMock(QueryInterface::class);
        $postQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [0])
            ->willReturnSelf();
        $postQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['post_id' => 0, 'user_id' => 10, 'title' => 'P0'],
            ]);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, [])
            ->willReturn($postQuery);

        $entities = [
            ['owner_table' => 'posts', 'owner_id' => 0],
        ];

        // Act
        $result = $relation->earlyLoad($entities, $this->mockPlaceholderQuery, 'owner');

        // Assert
        $this->assertInstanceOf(TestPost::class, $result[0]['owner']);
    }

    public function testEarlyLoadThrowsWhenOwnerTableNotRegisteredAndIdPresent(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->expectException(\Switon\Core\Exception\RuntimeException::class);

        // Act
        $relation->earlyLoad([
            ['owner_table' => 'unknowns', 'owner_id' => 1],
        ], $this->mockPlaceholderQuery, 'owner');
    }

    public function testEarlyLoadThrowsWhenRelatedPrimaryKeyFieldMissing(): void
    {
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
            ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPost::class)
            ->willReturn('post_id');

        $postQuery = $this->createMock(QueryInterface::class);
        $postQuery->expects($this->once())
            ->method('whereIn')
            ->with('post_id', [1])
            ->willReturnSelf();
        $postQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                ['title' => 'P1'], // Missing post_id
            ]);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, [])
            ->willReturn($postQuery);

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field post_id in relation owner');

        $relation->earlyLoad([
            ['owner_table' => 'posts', 'owner_id' => 1],
        ], $this->mockPlaceholderQuery, 'owner');
    }

    public function testLazyLoadBuildsWhereForResolvedEntityClassWithZeroId(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPost::class)
            ->willReturn('post_id');

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPost::class, [])
            ->willReturn($this->mockPlaceholderQuery);

        $this->mockPlaceholderQuery->expects($this->once())
            ->method('where')
            ->with(['post_id' => 0])
            ->willReturnSelf();

        $this->mockPlaceholderQuery->expects($this->once())
            ->method('setFetchType')
            ->with(false)
            ->willReturnSelf();

        $entity = new class (['owner_table' => 'posts', 'owner_id' => 0]) extends Entity {
            public string $owner_table;
            public int $owner_id;
        };

        // Act
        $result = $relation->lazyLoad($entity);

        // Assert
        $this->assertSame($this->mockPlaceholderQuery, $result);
    }

    public function testLazyLoadThrowsWhenOwnerTableNotRegisteredInMorphs(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->expectException(\Switon\Core\Exception\RuntimeException::class);

        $entity = new class (['owner_table' => 'unknowns', 'owner_id' => 1]) extends Entity {
            public string $owner_table;
            public int $owner_id;
        };

        // Act
        $relation->lazyLoad($entity);
    }

    public function testEarlyLoadSetsNullWhenTableOrIdIsNullAndDoesNotCreateQueries(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->expects($this->never())
            ->method('createQuery');

        $entities = [
            ['owner_table' => null, 'owner_id' => null],
            ['owner_table' => 'posts', 'owner_id' => null],
            ['owner_table' => null, 'owner_id' => 1],
        ];

        // Act
        $result = $relation->earlyLoad($entities, $this->mockPlaceholderQuery, 'owner');

        // Assert
        $this->assertNull($result[0]['owner']);
        $this->assertNull($result[1]['owner']);
        $this->assertNull($result[2]['owner']);
    }

    public function testLazyLoadBuildsWhereWithNullIdValue(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestUser::class, true, 'users'],
            ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPost::class)
            ->willReturn('post_id');

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPost::class)
            ->willReturn($this->mockPlaceholderQuery);

        $this->mockPlaceholderQuery->expects($this->once())
            ->method('where')
            ->with(['post_id' => null])
            ->willReturnSelf();

        $this->mockPlaceholderQuery->expects($this->once())
            ->method('setFetchType')
            ->with(false)
            ->willReturnSelf();

        $entity = new class (['owner_table' => 'posts', 'owner_id' => null]) extends Entity {
            public string $owner_table;
            public ?int $owner_id;
        };

        // Act
        $result = $relation->lazyLoad($entity);

        // Assert
        $this->assertSame($this->mockPlaceholderQuery, $result);
    }

    public function testEarlyLoadUsesPassedQueryInsteadOfCreatingFreshQuery(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class]);

        $this->mockEntityMetadata->method('getTable')
            ->willReturnMap([
                [TestPost::class, true, 'posts'],
                [TestPost::class, false, 'posts'],
            ]);
        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPost::class)
            ->willReturn('post_id');
        $this->mockEntityMetadata->method('getConnection')
            ->with(TestPost::class)
            ->willReturn('default');
        $this->mockEntityMetadata->method('getColumnMap')
            ->with(TestPost::class)
            ->willReturn([]);

        $this->mockEntityMetadata->expects($this->never())
            ->method('createQuery');

        $relatedQuery = $this->createMock(QueryInterface::class);
        $relatedQuery->method('setEntityClass')->willReturnSelf();
        $relatedQuery->method('setTable')->willReturnSelf();
        $relatedQuery->method('setColumnMap')->willReturnSelf();
        $relatedQuery->method('whereIn')->willReturnSelf();
        $relatedQuery->method('fetch')->willReturn([
            ['post_id' => 1, 'user_id' => 10, 'title' => 'P1'],
        ]);

        // Act
        $result = $relation->earlyLoad(
            [['owner_table' => 'posts', 'owner_id' => 1]],
            $relatedQuery,
            'owner'
        );

        // Assert
        $this->assertInstanceOf(TestPost::class, $result[0]['owner']);
    }

    public function testEarlyLoadThrowsWhenOwnerTableFieldIsMissing(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class]);

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field owner_table in relation owner');

        // Act
        $relation->earlyLoad(
            [['owner_id' => 1]],
            $this->mockPlaceholderQuery,
            'owner'
        );
    }

    public function testLazyLoadThrowsFrameworkExceptionWhenOwnerTableIsNull(): void
    {
        // Arrange
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $entity = new class (['owner_table' => null, 'owner_id' => 1]) extends Entity {
            public ?string $owner_table;
            public int $owner_id;
        };

        $this->expectException(\Switon\Core\Exception\RuntimeException::class);

        // Act
        $relation->lazyLoad($entity);
    }

    public function testLazyLoadThrowsWhenOwnerTableFieldIsMissing(): void
    {
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $entity = new class (['owner_id' => 1]) extends Entity {
            public int $owner_id;
        };

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field owner_table in relation owner_table');

        $relation->lazyLoad($entity);
    }

    public function testLazyLoadThrowsWhenOwnerIdFieldIsMissing(): void
    {
        $relation = $this->createMorphToRelation('owner_table', 'owner_id', [TestPost::class, TestUser::class]);

        $entity = new class (['owner_table' => 'posts']) extends Entity {
            public string $owner_table;
        };

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field owner_id in relation owner_id');

        $relation->lazyLoad($entity);
    }
}
