<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Attribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Switon\Binding\Attribute\ResolvedBy;
use Switon\Binding\PropertyNormalizerInterface;
use Switon\Core\InputInterface;
use Switon\Orm\Attribute\Owner;
use Switon\Orm\Attribute\Repository;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\EntityResolver;
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Orm\RepositoryInterface;
use Switon\Orm\Tests\Fixtures\TestEntityWithRepository;
use Switon\Orm\Tests\TestCase;

class EntityResolverTest extends TestCase
{
    protected function createParameter(string $fixtureClass = EntityResolverFixture::class): ReflectionParameter
    {
        return (new ReflectionMethod($fixtureClass, 'saveAction'))->getParameters()[0];
    }

    protected function createResolver(
        InputInterface                           $input,
        EntityMetadataInterface                  $entityMetadata,
        ?\Switon\Principal\IdentityInterface $identity = null,
        ?object                                  $identityFilter = null,
    ): EntityResolver
    {
        return new class($input, $entityMetadata, $identity, $identityFilter) extends EntityResolver {
            public function __construct(
                InputInterface                           $input,
                EntityMetadataInterface                  $entityMetadata,
                ?\Switon\Principal\IdentityInterface $identity,
                protected ?object                        $testIdentityFilter,
            )
            {
                $this->input = $input;
                $this->entityMetadata = $entityMetadata;
                if ($identity !== null) {
                    $this->identity = $identity;
                }
            }

            protected function resolveIdentityFilter(ReflectionParameter $parameter): object|null
            {
                return $this->testIdentityFilter;
            }
        };
    }

    protected function expectRepositoryMetadata(
        EntityMetadataInterface $entityMetadata,
        string                  $entityClass,
        RepositoryInterface     $repository,
        string                  $primaryKey = 'id',
    ): void
    {
        $entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with($entityClass)
            ->willReturn($repository);
        $entityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with($entityClass)
            ->willReturn($primaryKey);
    }

    public function testResolveLoadsExistingEntityFromMergedInputViaRepositoryFill(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['name' => 'Alice', 'id' => 99]);

        $entity = new TestEntityWithRepository();
        $entity->id = 1;
        $entity->name = 'Alice';

        $existing = new TestEntityWithRepository();
        $existing->id = 99;
        $existing->name = 'Before';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 99])
            ->willReturn($existing);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['name' => 'Alice', 'id' => 99])
            ->willReturn($entity);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, TestEntityWithRepository::class, $repository);

        $resolver = $this->createResolver($input, $entityMetadata);
        $parameter = $this->createParameter();
        $resolved = $resolver->resolve($parameter, TestEntityWithRepository::class);

        $this->assertSame($existing, $resolved);
        $this->assertSame(99, $resolved->id);
        $this->assertSame('Alice', $resolved->name);
    }

    public function testResolveThrowsWhenPrimaryKeyMissing(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['name' => 'Alice']);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(TestEntityWithRepository::class)
            ->willReturn('id');

        $resolver = $this->createResolver($input, $entityMetadata);
        $parameter = $this->createParameter();

        $this->expectException(PrimaryKeyMissingException::class);
        $resolver->resolve($parameter, TestEntityWithRepository::class);
    }

    public function testEntityBaseDeclaresResolvedByForSubclasses(): void
    {
        $reflection = new ReflectionClass(Entity::class);
        $attribute = $reflection->getAttributes(ResolvedBy::class)[0] ?? null;

        $this->assertNotNull($attribute);
        $this->assertSame(EntityResolver::class, $attribute->newInstance()->getResolver());
    }

    public function testResolveAppliesPropertyInputNormalizersBeforeRepositoryFill(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['id' => 7, 'name' => 'alice']);

        $entity = new EntityResolverNormalizedEntity();
        $entity->name = 'ALICE';

        $existing = new EntityResolverNormalizedEntity();
        $existing->id = 7;
        $existing->name = 'Before';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 7])
            ->willReturn($existing);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['id' => 7, 'name' => 'ALICE'])
            ->willReturn($entity);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, EntityResolverNormalizedEntity::class, $repository);

        $resolver = $this->createResolver($input, $entityMetadata);
        $parameter = $this->createParameter(EntityResolverNormalizedFixture::class);
        $resolved = $resolver->resolve($parameter, EntityResolverNormalizedEntity::class);

        $this->assertSame($existing, $resolved);
        $this->assertSame(7, $resolved->id);
        $this->assertSame('ALICE', $resolved->name);
    }

    public function testResolveRestoresPrimaryKeyFromRawInputAfterFill(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['id' => 9, 'name' => 'Alice']);

        $entity = new TestEntityWithRepository();
        $entity->name = 'Alice';

        $existing = new TestEntityWithRepository();
        $existing->id = 9;
        $existing->name = 'Before';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 9])
            ->willReturn($existing);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['id' => 9, 'name' => 'Alice'])
            ->willReturn($entity);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, TestEntityWithRepository::class, $repository);

        $resolver = $this->createResolver($input, $entityMetadata);
        $parameter = $this->createParameter();
        $resolved = $resolver->resolve($parameter, TestEntityWithRepository::class);

        $this->assertSame(9, $resolved->id);
        $this->assertSame('Alice', $resolved->name);
    }

    public function testResolveInjectsCurrentIdentityFieldForNewEntityWhenIdentityFilterApplies(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['id' => 7, 'name' => 'Alice']);

        $entity = new EntityResolverOwnedEntity();
        $entity->name = 'Alice';
        $entity->admin_id = 7;

        $existing = new EntityResolverOwnedEntity();
        $existing->id = 7;
        $existing->admin_id = 7;
        $existing->name = 'Before';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 7, 'admin_id' => 7])
            ->willReturn($existing);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['id' => 7, 'name' => 'Alice', 'admin_id' => 7])
            ->willReturn($entity);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, EntityResolverOwnedEntity::class, $repository);

        $resolver = $this->createResolver($input, $entityMetadata, null, new IdentityFilterStub('admin_id', true, 7));
        $parameter = $this->createParameter();
        $resolved = $resolver->resolve($parameter, EntityResolverOwnedEntity::class);

        $this->assertSame($existing, $resolved);
        $this->assertSame(7, $resolved->admin_id);
    }

    public function testResolveLoadsOwnedExistingEntityWhenIdentityFilterApplies(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['id' => 9, 'name' => 'Alice']);

        $existing = new EntityResolverOwnedEntity();
        $existing->id = 9;
        $existing->admin_id = 7;
        $existing->name = 'Before';

        $incoming = new EntityResolverOwnedEntity();
        $incoming->id = 9;
        $incoming->admin_id = 7;
        $incoming->name = 'Alice';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 9, 'admin_id' => 7])
            ->willReturn($existing);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['id' => 9, 'name' => 'Alice', 'admin_id' => 7])
            ->willReturn($incoming);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, EntityResolverOwnedEntity::class, $repository);

        $resolver = $this->createResolver($input, $entityMetadata, null, new IdentityFilterStub('admin_id', true, 7));
        $parameter = $this->createParameter();
        $resolved = $resolver->resolve($parameter, EntityResolverOwnedEntity::class);

        $this->assertSame($existing, $resolved);
        $this->assertSame(9, $resolved->id);
        $this->assertSame(7, $resolved->admin_id);
        $this->assertSame('Alice', $resolved->name);
    }

    public function testResolveLoadsImplicitCreatedByOwnedEntityWhenOwnerExists(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['id' => 9, 'name' => 'Alice']);

        $entity = new EntityResolverImplicitOwnedEntity();
        $entity->name = 'Alice';
        $entity->created_by = 7;

        $existing = new EntityResolverImplicitOwnedEntity();
        $existing->id = 9;
        $existing->created_by = 7;
        $existing->name = 'Before';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 9, 'created_by' => 7])
            ->willReturn($existing);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['id' => 9, 'name' => 'Alice', 'created_by' => 7])
            ->willReturn($entity);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, EntityResolverImplicitOwnedEntity::class, $repository);
        $entityMetadata->expects($this->once())
            ->method('getOwnerField')
            ->with(EntityResolverImplicitOwnedEntity::class)
            ->willReturn('created_by');

        $identity = $this->createMock(\Switon\Principal\IdentityInterface::class);
        $identity->expects($this->once())
            ->method('getId')
            ->willReturn(7);

        $resolver = $this->createResolver($input, $entityMetadata, $identity);
        $parameter = $this->createParameter();
        $resolved = $resolver->resolve($parameter, EntityResolverImplicitOwnedEntity::class);

        $this->assertSame($existing, $resolved);
        $this->assertSame(9, $resolved->id);
        $this->assertSame(7, $resolved->created_by);
        $this->assertSame('Alice', $resolved->name);
    }

    public function testResolveLoadsOwnedExistingEntityFromEntityOwnerMetadata(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['id' => 9, 'name' => 'Alice']);

        $existing = new EntityResolverImplicitOwnedEntity();
        $existing->id = 9;
        $existing->created_by = 7;
        $existing->name = 'Before';

        $incoming = new EntityResolverImplicitOwnedEntity();
        $incoming->id = 9;
        $incoming->created_by = 7;
        $incoming->name = 'Alice';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 9, 'created_by' => 7])
            ->willReturn($existing);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['id' => 9, 'name' => 'Alice', 'created_by' => 7])
            ->willReturn($incoming);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, EntityResolverImplicitOwnedEntity::class, $repository);
        $entityMetadata->expects($this->once())
            ->method('getOwnerField')
            ->with(EntityResolverImplicitOwnedEntity::class)
            ->willReturn('created_by');

        $identity = $this->createMock(\Switon\Principal\IdentityInterface::class);
        $identity->expects($this->once())
            ->method('getId')
            ->willReturn(7);

        $resolver = $this->createResolver($input, $entityMetadata, $identity);
        $parameter = $this->createParameter();
        $resolved = $resolver->resolve($parameter, EntityResolverImplicitOwnedEntity::class);

        $this->assertSame($existing, $resolved);
        $this->assertSame(9, $resolved->id);
        $this->assertSame(7, $resolved->created_by);
        $this->assertSame('Alice', $resolved->name);
    }

    public function testResolveSkipsImplicitEntityOwnerWhenMethodIdentityFilterDoesNotApply(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('all')
            ->willReturn(['id' => 9, 'name' => 'Alice']);

        $entity = new EntityResolverImplicitOwnedEntity();
        $entity->id = 9;
        $entity->name = 'Alice';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('firstOrFail')
            ->with(['id' => 9])
            ->willReturn($entity);
        $repository->expects($this->once())
            ->method('fill')
            ->with(['id' => 9, 'name' => 'Alice'])
            ->willReturn($entity);

        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->expectRepositoryMetadata($entityMetadata, EntityResolverImplicitOwnedEntity::class, $repository);
        $entityMetadata->expects($this->never())
            ->method('getOwnerField');

        $identity = $this->createMock(\Switon\Principal\IdentityInterface::class);
        $identity->expects($this->never())
            ->method('getId');

        $resolver = $this->createResolver($input, $entityMetadata, $identity, new IdentityFilterStub('created_by', false, 7));
        $parameter = $this->createParameter();
        $resolved = $resolver->resolve($parameter, EntityResolverImplicitOwnedEntity::class);

        $this->assertSame(9, $resolved->id);
        $this->assertSame('Alice', $resolved->name);
    }
}

class EntityResolverFixture
{
    public function saveAction(TestEntityWithRepository $entity): void
    {
    }
}

class EntityResolverNormalizedFixture
{
    public function saveAction(EntityResolverNormalizedEntity $entity): void
    {
    }
}

class EntityResolverNormalizedEntity extends Entity
{
    public int $id;

    #[UppercaseInput]
    public string $name;
}

#[Repository(\Switon\Orm\Tests\Fixtures\TestRepository::class)]
class EntityResolverOwnedEntity extends Entity
{
    public int $id;
    public int $admin_id;
    public string $name;
}

class EntityResolverImplicitOwnedEntity extends Entity
{
    public int $id;
    public int $created_by;
    public string $name;
}

#[Owner(null)]
class EntityResolverOwnerDisabledEntity extends Entity
{
    public int $id;
    public int $created_by;
    public string $name;
}

class IdentityFilterStub
{
    public function __construct(
        public string  $field,
        protected bool $applies,
        protected int  $identityId,
    )
    {
    }

    public function appliesTo(ReflectionParameter $parameter): bool
    {
        return $this->applies;
    }

    public function currentIdentityId(?string $targetType = null): int|string
    {
        return $targetType === 'int' ? $this->identityId : (string)$this->identityId;
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class UppercaseInput implements PropertyNormalizerInterface
{
    public function normalizeInput(ReflectionProperty $property, mixed $value): mixed
    {
        return strtoupper((string)$value);
    }
}
