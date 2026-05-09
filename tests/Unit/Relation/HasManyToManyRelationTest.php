<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Exception\MisuseException;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\HasManyToManyRelation;
use Switon\Orm\RepositoryInterface;
use Switon\Orm\Tests\Fixtures\Pivot\UserRole as PivotUserRole;
use Switon\Orm\Tests\Fixtures\TestArticle;
use Switon\Orm\Tests\Fixtures\TestArticleTestTag;
use Switon\Orm\Tests\Fixtures\TestRole;
use Switon\Orm\Tests\Fixtures\TestTag;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\Fixtures\TestUserRole;
use Switon\Orm\Tests\Fixtures\TestUserRoleWithBelongsTo;
use Switon\Orm\Tests\Fixtures\TestUserRoleWithBelongsToIgnored;
use Switon\Orm\Tests\Fixtures\User;
use Switon\Orm\Tests\Fixtures\UserRole;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

/**
 * Unit tests for HasManyToManyRelation.
 */
#[AllowMockObjectsWithoutExpectations]
class HasManyToManyRelationTest extends TestCase
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
    }

    /**
     * Test constructor with junction entity only.
     */
    public function testConstructorWithJunctionEntityOnly(): void
    {
        $relation = new HasManyToManyRelation('App\\Entity\\UserRole');

        $this->assertInstanceOf(HasManyToManyRelation::class, $relation);
    }

    /**
     * Test constructor with all parameters.
     */
    public function testConstructorWithAllParameters(): void
    {
        $relation = new HasManyToManyRelation(
            'App\\Entity\\UserRole',
            'App\\Entity\\Role',
            ['name' => SORT_ASC]
        );

        $this->assertInstanceOf(HasManyToManyRelation::class, $relation);
    }

    /**
     * Test earlyLoad with valid junction data.
     */
    public function testEarlyLoadWithValidData(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        // Parent data (users)
        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        // Junction data
        $junctionData = [
            ['user_id' => 1, 'role_id' => 1],
            ['user_id' => 1, 'role_id' => 2],
            ['user_id' => 2, 'role_id' => 1],
        ];

        // Related data (roles)
        $relatedData = [
            1 => ['role_id' => 1, 'name' => 'Admin'],
            2 => ['role_id' => 2, 'name' => 'Editor'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($class) {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id'
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('roles', $result[0]);
        $this->assertArrayHasKey('roles', $result[1]);
        $this->assertCount(2, $result[0]['roles']); // User 1 has 2 roles
        $this->assertCount(1, $result[1]['roles']); // User 2 has 1 role
    }

    public function testEarlyLoadKeepsDuplicateRelatedEntitiesWhenJunctionHasDuplicates(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $junctionData = [
            // role_id=1 duplicated intentionally
            ['user_id' => 1, 'role_id' => 1],
            ['user_id' => 1, 'role_id' => 1],
            ['user_id' => 1, 'role_id' => 2],
            ['user_id' => 2, 'role_id' => 1],
        ];

        $relatedData = [
            1 => ['role_id' => 1, 'name' => 'Admin'],
            2 => ['role_id' => 2, 'name' => 'Editor'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($class) {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(2, $result);
        $this->assertCount(3, $result[0]['roles']); // duplicates kept
        $this->assertCount(1, $result[1]['roles']);

        $this->assertSame(1, $result[0]['roles'][0]->role_id);
        $this->assertSame(1, $result[0]['roles'][1]->role_id);
        $this->assertSame(2, $result[0]['roles'][2]->role_id);
    }

    /**
     * Test earlyLoad returns empty array when no junction data.
     */
    public function testEarlyLoadReturnsEmptyArrayWhenNoJunctionData(): void
    {
        $relation = new HasManyToManyRelation('App\\Entity\\UserRole', 'App\\Entity\\Role');

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => 'App\\Entity\\User',
            'relatedEntityClass' => 'App\\Entity\\Role',
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    'App\\Entity\\User' => 'user_id',
                    'App\\Entity\\Role' => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn([]); // No junction data

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([]);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['roles']);
        $this->assertEmpty($result[0]['roles']);
    }

    /**
     * Empty parent rows → unique self ids are empty; junction and related queries still run with empty IN lists.
     */
    public function testEarlyLoadWithEmptyParentRows(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [])
            ->willReturnSelf();
        $junctionQuery->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [])
            ->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([]);

        $result = $relation->earlyLoad([], $this->mockQuery, 'roles');

        $this->assertSame([], $result);
    }

    /**
     * Junction rows whose self id is not in the parent batch must not attach to any parent row.
     */
    public function testEarlyLoadIgnoresJunctionRowsForParentsNotInBatch(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 999, 'role_id' => 1],
            ['user_id' => 1, 'role_id' => 2],
        ];

        $relatedData = [
            1 => ['role_id' => 1, 'name' => 'OrphanOnly'],
            2 => ['role_id' => 2, 'name' => 'Kept'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();
        $junctionQuery->expects($this->once())
            ->method('execute')
            ->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [1, 2])
            ->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['roles']);
        $this->assertSame(2, $result[0]['roles'][0]->role_id);
    }

    /**
     * Duplicate parent rows (same self id) should each receive the same related roles.
     *
     * array_unique() should only affect which ids are queried, not the mapping back to each parent row.
     */
    public function testEarlyLoadMapsRelatedEntitiesForDuplicateParentRows(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1 - A'],
            ['user_id' => 1, 'name' => 'User 1 - B'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 10],
        ];

        $relatedData = [
            10 => ['role_id' => 10, 'name' => 'Admin'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();
        $junctionQuery->expects($this->once())
            ->method('execute')
            ->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRole::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [10])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['roles']);
        $this->assertSame(10, $result[0]['roles'][0]->role_id);
        $this->assertCount(1, $result[1]['roles']);
        $this->assertSame(10, $result[1]['roles'][0]->role_id);
    }

    public function testEarlyLoadSkipsJunctionOrphansWhenRelatedEntityMissing(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 1],
            ['user_id' => 1, 'role_id' => 999], // orphan junction (role does not exist in relatedEntities)
        ];

        $relatedData = [
            1 => ['role_id' => 1, 'name' => 'Admin'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($class) {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id'
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('roles', $result[0]);
        $this->assertCount(1, $result[0]['roles']); // only role_id=1 should be attached
        $this->assertSame(1, $result[0]['roles'][0]->role_id);
    }

    public function testEarlyLoadSkipsOrphanJunctionsAndKeepsDuplicateValidTargetsWhenJunctionHasStringIds(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        // role_id values are numeric strings (e.g. '1') to validate array key coercion.
        $junctionData = [
            ['user_id' => 1, 'role_id' => '1'],
            ['user_id' => 1, 'role_id' => '1'], // duplicate valid junction
            ['user_id' => 1, 'role_id' => '999'],
            ['user_id' => 1, 'role_id' => '999'], // duplicate orphan junction
        ];

        $relatedData = [
            1 => ['role_id' => 1, 'name' => 'Admin'],
        ];

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('roles', $result[0]);
        $this->assertCount(2, $result[0]['roles']); // only role_id=1 duplicates kept
        $this->assertSame(1, $result[0]['roles'][0]->role_id);
        $this->assertSame(1, $result[0]['roles'][1]->role_id);
    }

    public function testEarlyLoadSkipsDuplicateOrphanJunctionValuesWhenRelatedEntityMissingAndDoesNotDispatch(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => '999'],
            ['user_id' => 1, 'role_id' => '999'],
            ['user_id' => 1, 'role_id' => '999'],
        ];

        $relatedData = [];

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('roles', $result[0]);
        $this->assertCount(0, $result[0]['roles']);
    }

    public function testEarlyLoadThrowsExceptionWhenJunctionRelatedFieldMissing(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1], // Missing role_id
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad($parentData, $this->mockQuery, 'roles');
    }

    public function testEarlyLoadThrowsExceptionWhenRelatedPrimaryKeyMissing(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 10],
        ];

        $relatedData = [
            10 => ['name' => 'Admin'], // Missing role_id
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($junctionQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad($parentData, $this->mockQuery, 'roles');
    }

    public function testEarlyLoadInfersJunctionFieldsFromBelongsToRelations(): void
    {
        $relation = new HasManyToManyRelation(TestUserRoleWithBelongsTo::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 1],
            ['user_id' => 1, 'role_id' => 2],
            ['user_id' => 2, 'role_id' => 1],
        ];

        $relatedData = [
            1 => ['role_id' => 1, 'name' => 'Admin'],
            2 => ['role_id' => 2, 'name' => 'Editor'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();
        $junctionQuery->expects($this->once())
            ->method('execute')
            ->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRoleWithBelongsTo::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [1, 2])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(2, $result);
        $this->assertCount(2, $result[0]['roles']);
        $this->assertCount(1, $result[1]['roles']);
    }

    /**
     * inferFromRelations() must skip BelongsTo attributes on readonly and static properties.
     *
     * This prevents wrong junction fields inference (e.g. role_readonly_id / role_static_id).
     */
    public function testEarlyLoadSkipsReadOnlyAndStaticBelongsToFieldsWhenInferringJunctionFields(): void
    {
        $relation = new HasManyToManyRelation(TestUserRoleWithBelongsToIgnored::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
            ['user_id' => 2, 'name' => 'User 2'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 10],
            ['user_id' => 1, 'role_id' => 11],
            ['user_id' => 2, 'role_id' => 10],
        ];

        $relatedData = [
            10 => ['role_id' => 10, 'name' => 'Admin'],
            11 => ['role_id' => 11, 'name' => 'Editor'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1, 2])
            ->willReturnSelf();
        $junctionQuery->expects($this->once())
            ->method('execute')
            ->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRoleWithBelongsToIgnored::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [10, 11])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(2, $result);
        $this->assertCount(2, $result[0]['roles']);
        $this->assertCount(1, $result[1]['roles']);
        $this->assertInstanceOf(TestRole::class, $result[0]['roles'][0]);
        $this->assertSame(10, $result[0]['roles'][0]->role_id);
        $this->assertSame(11, $result[0]['roles'][1]->role_id);
    }

    public function testEarlyLoadThrowsWhenJunctionHasNoBelongsToRelations(): void
    {
        $relation = new HasManyToManyRelation(UserRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => User::class,
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('define two BelongsTo relations');

        $relation->earlyLoad([
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
        ], $this->mockQuery, 'roles');
    }

    /**
     * Explicit foreign entity (constructor 2nd param) must override junction-based inference.
     */
    public function testEarlyLoadRespectsExplicitForeignEntityOverride(): void
    {
        $relation = new HasManyToManyRelation(TestUserRoleWithBelongsTo::class, TestArticle::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 10],
            ['user_id' => 1, 'role_id' => 11],
        ];

        $relatedData = [
            10 => ['article_id' => 10, 'title' => 'Article 10'],
            11 => ['article_id' => 11, 'title' => 'Article 11'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestArticle::class => 'article_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestArticle::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->expects($this->once())
            ->method('whereIn')
            ->with('user_id', [1])
            ->willReturnSelf();
        $junctionQuery->expects($this->once())
            ->method('execute')
            ->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRoleWithBelongsTo::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('article_id', [10, 11])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('article_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'articles');

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['articles']);
        $this->assertInstanceOf(TestArticle::class, $result[0]['articles'][0]);
    }

    public function testEarlyLoadThrowsWhenJunctionNeedsPivotNamespaceFallback(): void
    {
        $relation = new HasManyToManyRelation(PivotUserRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => User::class,
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('define two BelongsTo relations');

        $relation->earlyLoad([
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
        ], $this->mockQuery, 'roles');
    }

    public function testEarlyLoadThrowsWhenJunctionNeedsSuffixNameFallback(): void
    {
        $relation = new HasManyToManyRelation(TestArticleTestTag::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestTag::class,
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('define two BelongsTo relations');

        $relation->earlyLoad([
            ['tag_id' => 1, 'name' => 'Tag 1'],
            ['tag_id' => 2, 'name' => 'Tag 2'],
        ], $this->mockQuery, 'articles');
    }

    /**
     * Test lazyLoad creates correct query.
     */
    public function testLazyLoadCreatesCorrectQuery(): void
    {
        $relation = new HasManyToManyRelation('App\\Entity\\UserRole', 'App\\Entity\\Role');

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => 'App\\Entity\\User',
            'relatedEntityClass' => 'App\\Entity\\Role',
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
        ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(function ($class) {
                return match ($class) {
                    'App\\Entity\\User' => 'id',
                    'App\\Entity\\Role' => 'id',
                    default => 'id'
                };
            });

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->method('values')->willReturn([1, 2]);

        $this->mockEntityMetadata->method('getRepository')
            ->with('App\\Entity\\UserRole')
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->method('createQuery')
            ->with('App\\Entity\\Role')
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();

        $entity = new class(['id' => 42]) extends Entity {
            public int $id;
        };

        $result = $relation->lazyLoad($entity);

        $this->assertInstanceOf(QueryInterface::class, $result);
    }

    /**
     * lazyLoad: related ids is empty should still build the query with an empty IN list.
     */
    public function testLazyLoadWithEmptyRelatedIdsStillBuildsWhereIn(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
        ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 7], 'role_id')
            ->willReturn([]);

        $this->mockEntityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(TestUserRole::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestRole::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $entity = new class(['user_id' => 7]) extends Entity {
            public int $user_id;
        };

        $result = $relation->lazyLoad($entity);

        $this->assertInstanceOf(QueryInterface::class, $result);
    }

    public function testLazyLoadBuildsWhereInWithZeroRelatedIdsStillBuildsWhereIn(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
        ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('values')
            ->with(['user_id' => 7], 'role_id')
            ->willReturn([0, 1]);

        $this->mockEntityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(TestUserRole::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestRole::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [0, 1])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $entity = new class(['user_id' => 7]) extends Entity {
            public int $user_id;
        };

        $result = $relation->lazyLoad($entity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testEarlyLoadAttachesRelatedEntityWhenRelatedEntityIdIsZero(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 0],
        ];

        $relatedData = [
            0 => ['role_id' => 0, 'name' => 'ZeroRole'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRole::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [0])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['roles']);
        $this->assertSame(0, $result[0]['roles'][0]->role_id);
    }

    public function testEarlyLoadAttachesRelatedEntityWhenSelfEntityIdIsZero(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 0, 'name' => 'User 0'],
        ];

        $junctionData = [
            ['user_id' => 0, 'role_id' => 1],
        ];

        $relatedData = [
            1 => ['role_id' => 1, 'name' => 'Role 1'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRole::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [1])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['roles']);
        $this->assertSame(1, $result[0]['roles'][0]->role_id);
    }

    public function testEarlyLoadReturnsEmptyRolesForSelfEntityIdIsZeroWhenRelatedEntitiesMissing(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 0, 'name' => 'User 0'],
        ];

        $junctionData = [
            ['user_id' => 0, 'role_id' => 1],
        ];

        $relatedData = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRole::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [1])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertEmpty($result[0]['roles']);
    }

    public function testEarlyLoadSkipsJunctionOrphansWhenRelatedEntityIdIsZeroMissingInRelatedEntities(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 0],
        ];

        // relatedEntities intentionally does not have role_id=0
        $relatedData = [];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRole::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [0])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertEmpty($result[0]['roles']);
    }

    public function testEarlyLoadSkipsJunctionOrphansWhenRelatedEntityIdIsZeroStringMissingInRelatedEntities(): void
    {
        $relation = new HasManyToManyRelation(TestUserRole::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            'junctionRelatedField' => 'role_id',
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => '0'], // string zero
        ];

        // relatedEntities intentionally does not have role_id=0
        $relatedData = [];

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRole::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with(
                'role_id',
                $this->callback(function (array $ids): bool {
                    return $ids === [0] || $ids === ['0'];
                })
            )
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertEmpty($result[0]['roles']);
    }

    public function testLazyLoadThrowsWhenJunctionNeedsSuffixNameFallback(): void
    {
        $relation = new HasManyToManyRelation(TestArticleTestTag::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestTag::class,
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestTag::class)
            ->willReturn('tag_id');

        $entity = new TestTag(['tag_id' => 7, 'name' => 'lazy']);

        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('define two BelongsTo relations');

        $relation->lazyLoad($entity);
    }

    public function testEarlyLoadInitializesJunctionRelatedFieldWhenOnlyJunctionSelfFieldInjected(): void
    {
        $relation = new HasManyToManyRelation(TestUserRoleWithBelongsTo::class, TestRole::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            'relatedEntityClass' => TestRole::class,
            'junctionSelfField' => 'user_id',
            // junctionRelatedField intentionally not injected (null)
            'eventDispatcher' => $this->mockEventDispatcher,
        ]);

        $parentData = [
            ['user_id' => 1, 'name' => 'User 1'],
        ];

        $junctionData = [
            ['user_id' => 1, 'role_id' => 2],
        ];

        $relatedData = [
            2 => ['role_id' => 2, 'name' => 'Editor'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $junctionQuery = $this->createMock(QueryInterface::class);
        $junctionQuery->method('whereIn')->willReturnSelf();
        $junctionQuery->method('execute')->willReturn($junctionData);

        // getJunctionRelatedField() should initialize junctionRelatedField and createQuery() with it.
        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestUserRoleWithBelongsTo::class, ['user_id', 'role_id'])
            ->willReturn($junctionQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [2])
            ->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $this->mockEventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $relation->earlyLoad($parentData, $this->mockQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['roles']);
        $this->assertSame(2, $result[0]['roles'][0]->role_id);
    }

    public function testGetRelatedQueryInitializesWhenRelatedEntityClassIsEmpty(): void
    {
        $orderBy = ['name' => SORT_ASC];
        $relation = new HasManyToManyRelation(TestUserRoleWithBelongsTo::class, TestRole::class, $orderBy);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestUser::class,
            // Intentionally do not inject relatedEntityClass, leaving it empty so getRelatedQuery() triggers initializeFields().
        ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    TestUser::class => 'user_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestRole::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with($orderBy)
            ->willReturnSelf();

        $relation->getRelatedQuery();
    }

    /**
     * Test getRelatedQuery applies ordering.
     */
    public function testGetRelatedQueryAppliesOrdering(): void
    {
        $orderBy = ['name' => SORT_ASC];
        $relation = new HasManyToManyRelation(
            'App\\Entity\\UserRole',
            'App\\Entity\\Role',
            $orderBy
        );

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'relatedEntityClass' => 'App\\Entity\\Role',
        ]);

        $this->mockEntityMetadata->method('createQuery')
            ->with('App\\Entity\\Role')
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with($orderBy)
            ->willReturnSelf();

        $relation->getRelatedQuery();
    }
}
