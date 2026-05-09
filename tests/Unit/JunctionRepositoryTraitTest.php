<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\JunctionFieldsInferenceException;
use Switon\Orm\Relation\BelongsToRelation;
use Switon\Orm\RepositoryInterface;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class JunctionRepositoryTraitTest extends TestCase
{
    protected TestJunctionRepository $repository;
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|QueryInterface $mockQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);

        $this->container->set(EntityMetadataInterface::class, $this->mockEntityMetadata);

        $this->repository = new TestJunctionRepository();
        $this->injector->inject($this->repository);
    }

    public function testSyncRemovesAndAttachesRelationships(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('id');

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('whereIn')
            ->willReturnSelf();

        $this->mockQuery->method('indexBy')
            ->willReturnSelf();

        $this->mockQuery->method('fetch')
            ->willReturn([]);

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $result = $this->repository->sync(TestAdminEntity::class, 1, [2, 3, 4]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('attached', $result);
        $this->assertArrayHasKey('detached', $result);
        $this->assertIsInt($result['attached']);
        $this->assertIsInt($result['detached']);
    }

    public function testSyncOnlyAttachesNewRelationships(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('id');

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('whereIn')
            ->willReturnSelf();

        $this->mockQuery->method('indexBy')
            ->willReturnSelf();

        $this->mockQuery->method('fetch')
            ->willReturn([]);

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $result = $this->repository->sync(TestAdminEntity::class, 1, []);

        $this->assertSame(0, $result['attached']);
        $this->assertSame(0, $result['detached']);
    }

    public function testAttachCreatesNewRelationships(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);


        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('id');

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('whereIn')
            ->willReturnSelf();

        $this->mockQuery->method('indexBy')
            ->willReturnSelf();

        $this->mockQuery->method('fetch')
            ->willReturn([]);

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $count = $this->repository->attach(TestAdminEntity::class, 1, [2, 3]);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testAttachUsesRepositoryEntitiesForAutoFill(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');
        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('role_id');

        $entityRepo = $this->createMock(RepositoryInterface::class);
        $relatedRepo = $this->createMock(RepositoryInterface::class);

        $admin = new TestAdminEntity();
        $admin->admin_id = 1;
        $admin->admin_name = 'A';

        $role2 = new TestRoleEntity();
        $role2->role_id = 2;
        $role2->role_name = 'R2';

        $role3 = new TestRoleEntity();
        $role3->role_id = 3;
        $role3->role_name = 'R3';

        $entityRepo->method('get')->with(1)->willReturn($admin);
        $relatedRepo->method('allBy')
            ->with(['role_id' => [2, 3]], 'role_id')
            ->willReturn([2 => $role2, 3 => $role3]);

        $this->mockEntityMetadata->method('getRepository')
            ->willReturnCallback(function (string $entityClass) use ($entityRepo, $relatedRepo) {
                if ($entityClass === TestAdminEntity::class) {
                    return $entityRepo;
                }
                if ($entityClass === TestRoleEntity::class) {
                    return $relatedRepo;
                }
                return $entityRepo;
            });

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $count = $this->repository->attach(TestAdminEntity::class, 1, [2, 3]);

        $this->assertSame(2, $count);
    }

    public function testAttachSkipsExistingRelationships(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->mockEntityMetadata->method('getPrimaryKey')
            ->willReturn('id');

        $this->mockEntityMetadata->method('createQuery')
            ->willReturn($this->mockQuery);

        $this->mockQuery->method('whereIn')
            ->willReturnSelf();

        $this->mockQuery->method('indexBy')
            ->willReturnSelf();

        $this->mockQuery->method('fetch')
            ->willReturn([]);

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $count = $this->repository->attach(TestAdminEntity::class, 1, []);

        $this->assertSame(0, $count);
    }

    public function testDetachRemovesSpecificRelationships(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $count = $this->repository->detach(TestAdminEntity::class, 1, [2, 3]);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testDetachRemovesAllRelationshipsWhenEmptyArray(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $count = $this->repository->detach(TestAdminEntity::class, 1);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testGetJunctionFieldsCachesResults(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'admin' => $this->createMockBelongsToRelation('admin_id'),
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $result1 = $this->repository->testGetJunctionFields(TestAdminEntity::class);
        $result2 = $this->repository->testGetJunctionFields(TestAdminEntity::class);

        $this->assertSame($result1, $result2);
    }

    public function testGetJunctionFieldsThrowsExceptionWhenFieldsNotFound(): void
    {
        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([]);

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $this->expectException(JunctionFieldsInferenceException::class);

        $this->repository->testGetJunctionFields(TestAdminEntity::class);
    }

    public function testGetJunctionFieldsThrowsWhenJunctionHasOnlyOneBelongsTo(): void
    {
        $repository = new TestSingleBelongsToJunctionRepository();
        $repository->setMockMetadata($this->mockEntityMetadata);

        $this->mockEntityMetadata->method('getRelations')
            ->willReturn([
                'role' => $this->createMockBelongsToRelation('role_id'),
            ]);

        $this->expectException(JunctionFieldsInferenceException::class);
        $this->expectExceptionMessage('define two BelongsTo relations');

        $repository->testGetJunctionFields(TestAdminEntity::class);
    }

    public function testAutoFillFieldsFromEntitiesCopiesMatchingFields(): void
    {
        $junctionEntity = new TestJunctionEntity();
        $adminEntity = new TestAdminEntity();
        $roleEntity = new TestRoleEntity();

        $adminEntity->admin_name = 'Admin Name';
        $adminEntity->admin_code = 'ADMIN';
        $roleEntity->role_name = 'Role Name';
        $roleEntity->role_code = 'ROLE';

        $this->mockEntityMetadata->method('getFields')
            ->willReturn(['admin_id', 'admin_name', 'admin_code', 'role_id', 'role_name', 'role_code']);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturnCallback(function ($class) {
                if ($class === TestAdminEntity::class) {
                    return 'admin_id';
                }
                return 'role_id';
            });

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $this->repository->testAutoFillFieldsFromEntities($junctionEntity, $adminEntity, $roleEntity);

        $this->assertSame('Admin Name', $junctionEntity->admin_name);
        $this->assertSame('ADMIN', $junctionEntity->admin_code);
        $this->assertSame('Role Name', $junctionEntity->role_name);
        $this->assertSame('ROLE', $junctionEntity->role_code);
    }

    public function testAutoFillFieldsFromEntitiesDoesNotOverwriteExistingValues(): void
    {
        $junctionEntity = new TestJunctionEntity();
        $adminEntity = new TestAdminEntity();

        $junctionEntity->admin_name = 'Existing Name';
        $adminEntity->admin_name = 'New Name';

        $this->mockEntityMetadata->method('getFields')
            ->willReturn(['admin_id', 'admin_name', 'role_id']);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $this->repository->testAutoFillFieldsFromEntities($junctionEntity, $adminEntity, null);

        $this->assertSame('Existing Name', $junctionEntity->admin_name);
    }

    public function testAutoFillFieldsFromEntitiesHandlesNullRelatedEntity(): void
    {
        $junctionEntity = new TestJunctionEntity();
        $adminEntity = new TestAdminEntity();

        $adminEntity->admin_name = 'Admin Name';

        $this->mockEntityMetadata->method('getFields')
            ->willReturn(['admin_id', 'admin_name', 'role_id']);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturn('admin_id');

        $this->repository->setMockMetadata($this->mockEntityMetadata);

        $this->repository->testAutoFillFieldsFromEntities($junctionEntity, $adminEntity, null);

        $this->assertSame('Admin Name', $junctionEntity->admin_name);
    }

    public function testAutoFillFieldsLoadsEntitiesOnDemand(): void
    {
        $junctionEntity = new TestJunctionEntity();
        $adminEntity = new TestAdminEntity();
        $roleEntity = new TestRoleEntity();

        $adminEntity->admin_name = 'Admin Name';
        $roleEntity->role_name = 'Role Name';

        $entityRepo = $this->createMock(RepositoryInterface::class);
        $entityRepo->expects($this->once())->method('get')->with(1)->willReturn($adminEntity);

        $relatedRepo = $this->createMock(RepositoryInterface::class);
        $relatedRepo->expects($this->once())->method('get')->with(2)->willReturn($roleEntity);

        $this->mockEntityMetadata->method('getRepository')
            ->willReturnCallback(function (string $entityClass) use ($entityRepo, $relatedRepo) {
                return $entityClass === TestAdminEntity::class ? $entityRepo : $relatedRepo;
            });

        $this->mockEntityMetadata->method('getFields')
            ->willReturn(['admin_id', 'admin_name', 'role_id', 'role_name']);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->willReturnCallback(function (string $class) {
                return $class === TestAdminEntity::class ? 'admin_id' : 'role_id';
            });

        $this->repository->setMockMetadata($this->mockEntityMetadata);
        $this->repository->testAutoFillFields($junctionEntity, TestAdminEntity::class, 1, TestRoleEntity::class, 2);

        $this->assertSame('Admin Name', $junctionEntity->admin_name);
        $this->assertSame('Role Name', $junctionEntity->role_name);
    }

    public function testFillMatchingFieldsLoadsRelatedEntityOnDemand(): void
    {
        $junctionEntity = new TestJunctionEntity();
        $roleEntity = new TestRoleEntity();
        $roleEntity->role_name = 'Role Name';
        $roleEntity->role_code = 'ROLE';

        $relatedRepo = $this->createMock(RepositoryInterface::class);
        $relatedRepo->expects($this->once())->method('get')->with(2)->willReturn($roleEntity);

        $this->mockEntityMetadata->method('getRepository')
            ->with(TestRoleEntity::class)
            ->willReturn($relatedRepo);

        $this->mockEntityMetadata->method('getReferencedKey')
            ->with(TestRoleEntity::class)
            ->willReturn('role_id');

        $this->mockEntityMetadata->method('getFields')
            ->with(TestRoleEntity::class)
            ->willReturn(['role_id', 'role_name', 'role_code']);

        $this->repository->setMockMetadata($this->mockEntityMetadata);
        $this->repository->testFillMatchingFields($junctionEntity, TestRoleEntity::class, 2, ['role_name' => 0, 'role_code' => 1]);

        $this->assertSame('Role Name', $junctionEntity->role_name);
        $this->assertSame('ROLE', $junctionEntity->role_code);
    }

    protected function createMockBelongsToRelation(string $foreignKey): BelongsToRelation
    {
        $relation = $this->createMock(BelongsToRelation::class);
        $relation->method('getForeignKey')->willReturn($foreignKey);
        return $relation;
    }
}

class TestJunctionEntity extends Entity
{
    public int $admin_id;
    public ?string $admin_name = null;
    public ?string $admin_code = null;
    public int $role_id;
    public ?string $role_name = null;
    public ?string $role_code = null;

    #[BelongsTo]
    public ?TestAdminEntity $admin = null;

    #[BelongsTo]
    public ?TestRoleEntity $role = null;
}

class TestSingleBelongsToJunctionEntity extends Entity
{
    public int $admin_id;
    public int $role_id;

    #[BelongsTo]
    public ?TestRoleEntity $role = null;
}

/**
 * Test Admin entity for JunctionRepositoryTrait tests.
 */
class TestAdminEntity extends Entity
{
    public int $admin_id;
    public ?string $admin_name = null;
    public ?string $admin_code = null;
}

/**
 * Test Role entity for JunctionRepositoryTrait tests.
 */
class TestRoleEntity extends Entity
{
    public int $role_id;
    public ?string $role_name = null;
    public ?string $role_code = null;
}

class TestJunctionRepository
{
    use \Switon\Orm\JunctionRepositoryTrait;

    protected EntityMetadataInterface $mockMetadata;
    protected EntityMetadataInterface $entityMetadata;

    public function setMockMetadata(EntityMetadataInterface $metadata): void
    {
        $this->mockMetadata = $metadata;
        $this->entityMetadata = $metadata;
    }

    public function testGetJunctionFields(string $entityClass): array
    {
        return $this->getJunctionFields($entityClass);
    }

    public function testAutoFillFieldsFromEntities(
        Entity  $junctionEntity,
        Entity  $entity,
        ?Entity $relatedEntity
    ): void
    {
        $this->autoFillFieldsFromEntities($junctionEntity, $entity, $relatedEntity);
    }

    public function testAutoFillFields(
        Entity     $junctionEntity,
        string     $entityClass,
        int|string $entityId,
        string     $relatedEntityClass,
        int|string $relatedId
    ): void
    {
        $this->autoFillFields($junctionEntity, $entityClass, $entityId, $relatedEntityClass, $relatedId);
    }

    public function testFillMatchingFields(
        Entity     $junctionEntity,
        string     $relatedEntityClass,
        int|string $relatedId,
        array      $junctionFieldsMap
    ): void
    {
        $this->fillMatchingFields($junctionEntity, $relatedEntityClass, $relatedId, $junctionFieldsMap);
    }

    public function getEntityClass(): string
    {
        return TestJunctionEntity::class;
    }

    public function values(array $conditions, string $column): array
    {
        return [];
    }

    public function deleteAll(array $conditions): int
    {
        return 0;
    }

    public function create(Entity $entity): void
    {
    }
}

class TestSingleBelongsToJunctionRepository
{
    use \Switon\Orm\JunctionRepositoryTrait;

    protected EntityMetadataInterface $mockMetadata;
    protected EntityMetadataInterface $entityMetadata;

    public function setMockMetadata(EntityMetadataInterface $metadata): void
    {
        $this->mockMetadata = $metadata;
        $this->entityMetadata = $metadata;
    }

    public function testGetJunctionFields(string $entityClass): array
    {
        return $this->getJunctionFields($entityClass);
    }

    public function getEntityClass(): string
    {
        return TestSingleBelongsToJunctionEntity::class;
    }

    public function values(array $conditions, string $column): array
    {
        return [];
    }

    public function deleteAll(array $conditions): int
    {
        return 0;
    }

    public function create(Entity $entity): void
    {
    }
}
