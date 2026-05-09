<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Relation\JunctionManyRelation;
use Switon\Orm\RepositoryInterface;
use Switon\Orm\Tests\Fixtures\TestPermission;
use Switon\Orm\Tests\Fixtures\TestRolePermission;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

/**
 * Unit tests for JunctionManyRelation.
 *
 * JunctionManyRelation is used on junction entities to load related entities
 * grouped by a foreign key field.
 */
#[AllowMockObjectsWithoutExpectations]
class JunctionManyRelationTest extends TestCase
{
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|QueryInterface $mockQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);
    }

    /**
     * Test earlyLoad groups entities by foreign key.
     */
    public function testEarlyLoadGroupsEntitiesByForeignKey(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        // Junction data (RolePermission entries grouped by role_id)
        $junctionData = [
            ['role_id' => 1, 'permission_id' => 10],
            ['role_id' => 1, 'permission_id' => 20],
            ['role_id' => 2, 'permission_id' => 10],
        ];

        // Pivot data (same as junction for this test)
        $pivotData = [
            ['role_id' => 1, 'permission_id' => 10],
            ['role_id' => 1, 'permission_id' => 20],
            ['role_id' => 2, 'permission_id' => 10],
        ];

        // Related data (permissions)
        $relatedData = [
            10 => ['permission_id' => 10, 'name' => 'read'],
            20 => ['permission_id' => 20, 'name' => 'write'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->method('whereIn')->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($pivotData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($pivotQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $result = $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('permissions', $result[0]);
        $this->assertArrayHasKey('permissions', $result[1]);
        $this->assertArrayHasKey('permissions', $result[2]);

        // Role 1 has 2 permissions (2 junction records with role_id=1)
        $this->assertCount(2, $result[0]['permissions']);
        $this->assertCount(2, $result[1]['permissions']);

        // Role 2 has 1 permission
        $this->assertCount(1, $result[2]['permissions']);
    }

    public function testEarlyLoadMatchesZeroSelfFieldValuesCorrectly(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        // Junction data with selfField=0 included
        $junctionData = [
            ['role_id' => 0, 'permission_id' => 10],
            ['role_id' => 1, 'permission_id' => 11],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->expects($this->once())
            ->method('whereIn')
            ->with(
                'role_id',
                $this->callback(static function (array $ids): bool {
                    return count($ids) === 2
                        && in_array(0, $ids, true)
                        && in_array(1, $ids, true);
                })
            )
            ->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($junctionData);

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($pivotQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('permission_id')
            ->willReturnSelf();

        $relatedData = [
            10 => ['permission_id' => 10, 'name' => 'read'],
            11 => ['permission_id' => 11, 'name' => 'write'],
        ];
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['permissions']);
        $this->assertCount(1, $result[1]['permissions']);

        $this->assertInstanceOf(TestPermission::class, $result[0]['permissions'][0]);
        $this->assertInstanceOf(TestPermission::class, $result[1]['permissions'][0]);

        $this->assertSame(10, $result[0]['permissions'][0]->permission_id);
        $this->assertSame(11, $result[1]['permissions'][0]->permission_id);
    }

    public function testEarlyLoadDeduplicatesGroupingIdsInPivotWhereIn(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $junctionData = [
            ['role_id' => 1, 'permission_id' => 10],
            ['role_id' => 1, 'permission_id' => 11],
            ['role_id' => 2, 'permission_id' => 12],
        ];

        $pivotData = [
            ['role_id' => 1, 'permission_id' => 10],
            ['role_id' => 2, 'permission_id' => 11],
        ];

        $relatedData = [
            10 => ['permission_id' => 10, 'name' => 'read'],
            11 => ['permission_id' => 11, 'name' => 'write'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->expects($this->once())
            ->method('whereIn')
            ->with(
                'role_id',
                $this->callback(static function (array $ids): bool {
                    return count($ids) === 2
                        && in_array(1, $ids, true)
                        && in_array(2, $ids, true);
                })
            )
            ->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($pivotData);

        $this->mockEntityMetadata->method('createQuery')->willReturn($pivotQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with(
                'permission_id',
                $this->callback(static function (array $ids): bool {
                    return count($ids) === 2
                        && in_array(10, $ids, true)
                        && in_array(11, $ids, true);
                })
            )
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('permission_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');

        $this->assertCount(3, $result);
        $this->assertCount(1, $result[0]['permissions']);
        $this->assertCount(1, $result[1]['permissions']);
        $this->assertCount(1, $result[2]['permissions']);

        $this->assertSame(10, $result[0]['permissions'][0]->permission_id);
        $this->assertSame(10, $result[1]['permissions'][0]->permission_id);
        $this->assertSame(11, $result[2]['permissions'][0]->permission_id);
    }

    public function testEarlyLoadSkipsPivotRowsWithMissingRelatedEntity(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $junctionData = [
            ['role_id' => 1, 'permission_id' => 10],
            ['role_id' => 2, 'permission_id' => 11],
        ];

        $pivotData = [
            ['role_id' => 1, 'permission_id' => 10],
            ['role_id' => 2, 'permission_id' => 11],
        ];

        // relatedEntities intentionally misses permission_id=10
        $relatedData = [
            11 => ['permission_id' => 11, 'name' => 'write'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->method('whereIn')->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($pivotData);
        $this->mockEntityMetadata->method('createQuery')->willReturn($pivotQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [10, 11])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('permission_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');

        $this->assertCount(2, $result);
        $this->assertSame([], $result[0]['permissions']);
        $this->assertCount(1, $result[1]['permissions']);
        $this->assertSame(11, $result[1]['permissions'][0]->permission_id);
    }

    public function testEarlyLoadPreservesDuplicatePivotRecordsForSameGrouping(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $junctionData = [
            ['role_id' => 1, 'permission_id' => 10],
        ];

        $pivotData = [
            ['role_id' => 1, 'permission_id' => 10],
            ['role_id' => 1, 'permission_id' => 10], // duplicate pivot row
        ];

        $relatedData = [
            10 => ['permission_id' => 10, 'name' => 'read'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->method('whereIn')->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($pivotData);
        $this->mockEntityMetadata->method('createQuery')->willReturn($pivotQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [10])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('permission_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['permissions']);
        $this->assertSame(10, $result[0]['permissions'][0]->permission_id);
        $this->assertSame(10, $result[0]['permissions'][1]->permission_id);
    }

    public function testEarlyLoadMapsPermissionIdZeroCorrectly(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $junctionData = [
            ['role_id' => 0, 'permission_id' => 0],
            ['role_id' => 1, 'permission_id' => 11],
        ];

        $pivotData = [
            ['role_id' => 0, 'permission_id' => 0],
            ['role_id' => 1, 'permission_id' => 11],
        ];

        $relatedData = [
            0 => ['permission_id' => 0, 'name' => 'zero'],
            11 => ['permission_id' => 11, 'name' => 'write'],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->method('whereIn')->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($pivotData);
        $this->mockEntityMetadata->method('createQuery')->willReturn($pivotQuery);

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [0, 11])
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('indexBy')
            ->with('permission_id')
            ->willReturnSelf();
        $this->mockQuery->expects($this->once())
            ->method('fetch')
            ->willReturn($relatedData);

        $result = $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[0]['permissions']);
        $this->assertCount(1, $result[1]['permissions']);
        $this->assertSame(0, $result[0]['permissions'][0]->permission_id);
        $this->assertSame(11, $result[1]['permissions'][0]->permission_id);
    }

    public function testEarlyLoadThrowsExceptionWhenPivotValueFieldMissing(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $junctionData = [
            ['role_id' => 1, 'permission_id' => 10],
        ];

        $pivotData = [
            ['role_id' => 1], // Missing permission_id
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->method('whereIn')->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($pivotData);
        $this->mockEntityMetadata->method('createQuery')->willReturn($pivotQuery);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');
    }

    public function testEarlyLoadThrowsExceptionWhenRelatedPrimaryKeyMissing(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $junctionData = [
            ['role_id' => 1, 'permission_id' => 10],
        ];

        $pivotData = [
            ['role_id' => 1, 'permission_id' => 10],
        ];

        $relatedData = [
            10 => ['name' => 'read'], // Missing permission_id
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->method('whereIn')->willReturnSelf();
        $pivotQuery->method('execute')->willReturn($pivotData);
        $this->mockEntityMetadata->method('createQuery')->willReturn($pivotQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn($relatedData);

        $this->expectException(RelationFieldMissingException::class);

        $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');
    }

    public function testLazyLoadBuildsWhereInWithEmptyRelatedIdsAndSetsFetchTypeTrue(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $entity = new class(['role_id' => 0, 'user_id' => 42]) extends Entity {
            public int $role_id;
            public int $user_id;
        };

        $this->mockEntityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('values')
            ->with(['role_id' => 0], 'permission_id')
            ->willReturn([]);

        $this->mockEntityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(TestRolePermission::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestPermission::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with([])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($entity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadUsesPivotRepositoryValuesWithZeroGroupingId(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $entity = new class(['role_id' => 0, 'user_id' => 42]) extends Entity {
            public int $role_id;
            public int $user_id;
        };

        $this->mockEntityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('values')
            ->with(['role_id' => 0], 'permission_id')
            ->willReturn([10, 11]);

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestRolePermission::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPermission::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->method('setFetchType')->willReturnSelf();

        $relation->lazyLoad($entity);
    }

    public function testLazyLoadBuildsWhereInWithNonEmptyRelatedIdsAndAppliesOrderBy(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => ['name' => SORT_ASC],
            'initialized' => true,
        ]);

        $entity = new class(['role_id' => 5, 'user_id' => 42]) extends Entity {
            public int $role_id;
            public int $user_id;
        };

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->method('values')
            ->willReturn([10, 11]);
        $this->mockEntityMetadata->method('getRepository')
            ->with(TestRolePermission::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPermission::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with(['name' => SORT_ASC])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($entity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadBuildsWhereInWithNonZeroGroupingId(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $entity = new class(['role_id' => 3, 'user_id' => 42]) extends Entity {
            public int $role_id;
            public int $user_id;
        };

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('values')
            ->with(['role_id' => 3], 'permission_id')
            ->willReturn([12]);

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestRolePermission::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPermission::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [12])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $relation->lazyLoad($entity);
    }

    public function testLazyLoadReturnsQueryConfiguredForFetchTypeTrue(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $entity = new class(['role_id' => 1, 'user_id' => 42]) extends Entity {
            public int $role_id;
            public int $user_id;
        };

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->method('values')->willReturn([10]);
        $this->mockEntityMetadata->method('getRepository')
            ->with(TestRolePermission::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPermission::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();
        $this->mockQuery->method('whereIn')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $result = $relation->lazyLoad($entity);

        $this->assertSame($this->mockQuery, $result);
    }

    public function testLazyLoadBuildsWhereInWithDuplicateRelatedIdsWithoutDeduplication(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => TestRolePermission::class,
            'relatedEntityClass' => TestPermission::class,
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $entity = new class(['role_id' => 2, 'user_id' => 42]) extends Entity {
            public int $role_id;
            public int $user_id;
        };

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with(TestPermission::class)
            ->willReturn('permission_id');

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->method('values')
            ->willReturn([10, 10, 11]); // duplicates preserved at repository layer

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestRolePermission::class)
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->method('createQuery')
            ->with(TestPermission::class)
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [10, 10, 11])
            ->willReturnSelf();

        $this->mockQuery->expects($this->once())
            ->method('setFetchType')
            ->with(true)
            ->willReturnSelf();

        $relation->lazyLoad($entity);
    }

    /**
     * Test earlyLoad returns empty array when no pivot data.
     */
    public function testEarlyLoadReturnsEmptyArrayWhenNoPivotData(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => 'App\\Entity\\UserRole',
            'relatedEntityClass' => 'App\\Entity\\Permission',
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $junctionData = [
            ['role_id' => 1, 'user_id' => 1],
        ];

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->method('whereIn')->willReturnSelf();
        $pivotQuery->method('execute')->willReturn([]); // No pivot data

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($pivotQuery);

        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('indexBy')->willReturnSelf();
        $this->mockQuery->method('fetch')->willReturn([]);

        $result = $relation->earlyLoad($junctionData, $this->mockQuery, 'permissions');

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['permissions']);
        $this->assertEmpty($result[0]['permissions']);
    }

    /**
     * Test lazyLoad creates correct query.
     */
    public function testLazyLoadCreatesCorrectQuery(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'selfEntityClass' => 'App\\Entity\\UserRole',
            'relatedEntityClass' => 'App\\Entity\\Permission',
            'selfField' => 'role_id',
            'selfValue' => 'permission_id',
            'orderBy' => [],
            'initialized' => true,
        ]);

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->with('App\\Entity\\Permission')
            ->willReturn('id');

        $mockRepository = $this->createMock(RepositoryInterface::class);
        $mockRepository->method('values')->willReturn([10, 20]);

        $this->mockEntityMetadata->method('getRepository')
            ->with('App\\Entity\\UserRole')
            ->willReturn($mockRepository);

        $this->mockEntityMetadata->method('createQuery')
            ->with('App\\Entity\\Permission')
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('orderBy')->willReturnSelf();
        $this->mockQuery->method('whereIn')->willReturnSelf();
        $this->mockQuery->method('setFetchType')->willReturnSelf();

        $entity = new class(['role_id' => 1, 'user_id' => 42]) extends Entity {
            public int $role_id;
            public int $user_id;
        };

        $result = $relation->lazyLoad($entity);

        $this->assertInstanceOf(QueryInterface::class, $result);
    }

    /**
     * Test getRelatedQuery applies ordering.
     */
    public function testGetRelatedQueryAppliesOrdering(): void
    {
        $relation = $this->createPartialMock(JunctionManyRelation::class, []);

        $orderBy = ['name' => SORT_ASC];
        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $this->mockEntityMetadata,
            'relatedEntityClass' => 'App\\Entity\\Permission',
            'orderBy' => $orderBy,
            'initialized' => true,
        ]);

        $this->mockEntityMetadata->method('createQuery')
            ->with('App\\Entity\\Permission')
            ->willReturn($this->mockQuery);

        $this->mockQuery->expects($this->once())
            ->method('orderBy')
            ->with($orderBy)
            ->willReturnSelf();

        $relation->getRelatedQuery();
    }
}
