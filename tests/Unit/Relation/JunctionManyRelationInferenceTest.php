<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Relation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionClass;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\MisuseException;
use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Relation\JunctionManyRelation;
use Switon\Orm\Tests\Fixtures\TestPermission;
use Switon\Orm\Tests\Fixtures\TestRole;
use Switon\Orm\Tests\Fixtures\TestRolePermissionWithBelongsTo;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class JunctionManyRelationInferenceTest extends TestCase
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector->inject($this);
    }

    public function testEarlyLoadInfersSelfFieldAndSelfValueFromBelongsTo(): void
    {
        $relations = $this->entityMetadata->getRelations(TestRolePermissionWithBelongsTo::class);
        $this->assertArrayHasKey('roles', $relations);

        $relation = $relations['roles'];
        $this->assertInstanceOf(JunctionManyRelation::class, $relation);

        // Replace entityMetadata on relation with a mock so we can assert calls.
        $mockMetadata = $this->createMock(EntityMetadataInterface::class);
        $rRelation = new ReflectionClass($relation);
        $metadataProperty = $rRelation->getProperty('entityMetadata');
        $metadataProperty->setValue($relation, $mockMetadata);

        $mockMetadata->method('getReferencedKey')
            ->willReturnMap([
                [TestRolePermissionWithBelongsTo::class, 'permission_id'],
                [TestPermission::class, 'permission_id'],
                [TestRole::class, 'role_id'],
            ]);

        $mockMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(TestRole::class)
            ->willReturn('role_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [1, 2])
            ->willReturnSelf();
        $pivotQuery->expects($this->once())
            ->method('execute')
            ->willReturn([
                ['permission_id' => 1, 'role_id' => 10],
                ['permission_id' => 1, 'role_id' => 11],
                ['permission_id' => 2, 'role_id' => 11],
            ]);

        $mockMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestRolePermissionWithBelongsTo::class, ['permission_id', 'role_id'])
            ->willReturn($pivotQuery);

        $relatedQuery = $this->createMock(QueryInterface::class);
        $relatedQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [10, 11])
            ->willReturnSelf();
        $relatedQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $relatedQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                10 => ['role_id' => 10, 'name' => 'Role 10'],
                11 => ['role_id' => 11, 'name' => 'Role 11'],
            ]);

        $rows = [
            ['permission_id' => 1, 'role_id' => 10],
            ['permission_id' => 2, 'role_id' => 11],
        ];

        $result = $relation->earlyLoad($rows, $relatedQuery, 'roles');

        $this->assertCount(2, $result);
        $this->assertCount(2, $result[0]['roles']);
        $this->assertCount(1, $result[1]['roles']);
    }

    public function testGetRelatedQueryThrowsWhenCannotInferSelfField(): void
    {
        $relation = new JunctionManyRelation();
        $relation->bind(DummyJunctionManyNoBelongsTo::class, TestRole::class);

        $this->expectException(MisuseException::class);

        $mockMetadata = $this->createMock(EntityMetadataInterface::class);
        $mockMetadata->expects($this->any())
            ->method('getReferencedKey')
            ->willReturn('unused_id');

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $mockMetadata,
        ]);

        $relation->getRelatedQuery();
    }

    public function testEarlyLoadInfersSelfFieldAndSelfValueWhenBelongsToForeignKeyIsNull(): void
    {
        $relation = new JunctionManyRelation();
        $relation->bind(DummyJunctionManyBelongsToForeignKeyNull::class, TestRole::class);

        $mockMetadata = $this->createMock(EntityMetadataInterface::class);

        $mockMetadata->method('getReferencedKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    DummyJunctionManyBelongsToForeignKeyNull::class => 'unused_junction_id',
                    TestPermission::class => 'permission_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $mockMetadata->method('getPrimaryKey')
            ->with(TestRole::class)
            ->willReturn('role_id');

        $pivotQuery = $this->createMock(QueryInterface::class);
        $pivotQuery->expects($this->once())
            ->method('whereIn')
            ->with('permission_id', [1])
            ->willReturnSelf();
        $pivotQuery->expects($this->once())
            ->method('execute')
            ->willReturn([
                ['permission_id' => 1, 'role_id' => 9],
            ]);

        $mockMetadata->expects($this->once())
            ->method('createQuery')
            ->with(DummyJunctionManyBelongsToForeignKeyNull::class, ['permission_id', 'role_id'])
            ->willReturn($pivotQuery);

        $relatedQuery = $this->createMock(QueryInterface::class);
        $relatedQuery->expects($this->once())
            ->method('whereIn')
            ->with('role_id', [9])
            ->willReturnSelf();
        $relatedQuery->expects($this->once())
            ->method('indexBy')
            ->with('role_id')
            ->willReturnSelf();
        $relatedQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                9 => ['role_id' => 9, 'name' => 'R9'],
            ]);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $mockMetadata,
            'initialized' => false,
        ]);

        $rows = [
            ['permission_id' => 1],
        ];

        $result = $relation->earlyLoad($rows, $relatedQuery, 'roles');

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['roles']);
        $this->assertSame(9, $result[0]['roles'][0]->role_id);
    }

    public function testEarlyLoadThrowsWhenJunctionHasOnlyOneBelongsTo(): void
    {
        $relation = new JunctionManyRelation();
        $relation->bind(DummyJunctionManyOnlyPermissionBelongsTo::class, TestRole::class);

        $mockMetadata = $this->createMock(EntityMetadataInterface::class);

        $mockMetadata->method('getReferencedKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    DummyJunctionManyOnlyPermissionBelongsTo::class => 'unused_junction_id',
                    TestPermission::class => 'permission_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $relatedQuery = $this->createMock(QueryInterface::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $mockMetadata,
            'initialized' => false,
        ]);

        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('define two BelongsTo relations');

        $relation->earlyLoad([
            ['permission_id' => 1],
        ], $relatedQuery, 'roles');
    }

    public function testEarlyLoadThrowsWhenOnlyReadonlyAndStaticBelongsToRemain(): void
    {
        $relation = new JunctionManyRelation();
        $relation->bind(DummyJunctionManyReadonlyRoleBelongsTo::class, TestRole::class);

        $mockMetadata = $this->createMock(EntityMetadataInterface::class);
        $mockMetadata->method('getReferencedKey')
            ->willReturnCallback(static function (string $class): string {
                return match ($class) {
                    DummyJunctionManyReadonlyRoleBelongsTo::class => 'unused_junction_id',
                    TestPermission::class => 'permission_id',
                    TestRole::class => 'role_id',
                    default => 'id',
                };
            });

        $relatedQuery = $this->createMock(QueryInterface::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $mockMetadata,
            'initialized' => false,
        ]);

        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('define two BelongsTo relations');

        $relation->earlyLoad([
            ['permission_id' => 1],
        ], $relatedQuery, 'roles');
    }
}

// ============================================================================
// Dummy junction entities for inference edge cases.
// ============================================================================

class DummyJunctionManyNoBelongsTo extends Entity
{
    public int $permission_id;
    public int $role_id;
}

class DummyJunctionManyBelongsToForeignKeyNull extends Entity
{
    public int $permission_id;
    public int $role_id;

    #[BelongsTo]
    public ?TestPermission $permission = null;

    #[BelongsTo]
    public ?TestRole $role = null;
}

class DummyJunctionManyOnlyPermissionBelongsTo extends Entity
{
    public int $permission_id;
    public int $role_id;

    #[BelongsTo]
    public ?TestPermission $permission = null;
}

class DummyJunctionManyReadonlyRoleBelongsTo extends Entity
{
    public int $permission_id;
    public int $role_id;

    #[BelongsTo]
    public ?TestPermission $permission = null;

    #[BelongsTo]
    public readonly ?TestRole $roleReadOnly;

    #[BelongsTo]
    public static ?TestRole $roleStatic = null;
}
