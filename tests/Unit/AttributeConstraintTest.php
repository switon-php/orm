<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Switon\Core\Exception as CoreException;
use Switon\Orm\Attribute\Exists;
use Switon\Orm\Attribute\Immutable;
use Switon\Orm\Attribute\Unique;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\RepositoryInterface;
use Switon\Validating\Exception\InvalidConstraintSourceException;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;
use ReflectionClass;
use stdClass;

#[AllowMockObjectsWithoutExpectations]
class AttributeConstraintTest extends TestCase
{
    protected ValidatorInterface&MockObject $validator;
    protected EntityMetadataInterface&MockObject $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->entityMetadata = $this->createMock(EntityMetadataInterface::class);
    }

    public function testExistsValidateThrowsWhenSourceIsNotEntity(): void
    {
        $constraint = new Exists(User::class);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);
        $validation = new Validation($this->validator, new stdClass());
        $validation->field = 'user_id';
        $validation->value = 1;

        $this->expectException(InvalidConstraintSourceException::class);
        $constraint->validate($validation);
    }

    public function testExistsValidateReturnsTrueForNullValue(): void
    {
        $constraint = new Exists(User::class);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);
        $validation = new Validation($this->validator, new ExistsFixtureOrder());
        $validation->field = 'user_id';
        $validation->value = null;

        $this->assertTrue($constraint->validate($validation));
    }

    public function testExistsValidateReturnsTrueWhenRepositoryFindsEntity(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())->method('get')->with(7)->willReturn(new ExistsFixtureUser());

        $this->entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $constraint = new Exists(User::class);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);
        $validation = new Validation($this->validator, new ExistsFixtureOrder());
        $validation->field = 'user_id';
        $validation->value = 7;

        $this->assertTrue($constraint->validate($validation));
    }

    public function testExistsValidateReturnsFalseWhenRepositoryThrowsCoreException(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())->method('get')->with(9)->willThrowException($this->createMock(CoreException::class));

        $this->entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $constraint = new Exists(User::class);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);
        $validation = new Validation($this->validator, new ExistsFixtureOrder());
        $validation->field = 'user_id';
        $validation->value = 9;

        $this->assertFalse($constraint->validate($validation));
    }

    public function testExistsValidateUsesExplicitEntityClassWithoutFieldNameInference(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())->method('get')->with(1)->willReturn(new ExistsFixtureUser());

        $this->entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $constraint = new Exists(User::class);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);
        $validation = new Validation($this->validator, new ExistsFixtureOrder());
        $validation->field = 'user';
        $validation->value = 1;

        $this->assertTrue($constraint->validate($validation));
    }

    public function testExistsValidateReturnsFalseWhenRepositoryResolutionFails(): void
    {
        $constraint = new Exists('Ghost');
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);
        $validation = new Validation($this->validator, new ExistsFixtureOrder());
        $validation->field = 'ghost_id';
        $validation->value = 1;

        $this->entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with('Ghost')
            ->willThrowException($this->createMock(CoreException::class));

        $this->assertFalse($constraint->validate($validation));
    }

    public function testImmutableValidateReturnsTrueForNullValue(): void
    {
        $constraint = new Immutable();
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $entity = new ImmutableFixtureEntity();
        $entity->id = 1;

        $validation = new Validation($this->validator, $entity);
        $validation->field = 'name';
        $validation->value = null;

        $this->assertTrue($constraint->validate($validation));
    }

    public function testImmutableValidateThrowsWhenSourceIsNotEntity(): void
    {
        $constraint = new Immutable();
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $validation = new Validation($this->validator, new stdClass());
        $validation->field = 'name';
        $validation->value = 'changed';

        $this->expectException(InvalidConstraintSourceException::class);
        $constraint->validate($validation);
    }

    public function testImmutableValidateThrowsWhenSourceIsArray(): void
    {
        $constraint = new Immutable();
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $validation = new Validation($this->validator, ['name' => 'changed']);
        $validation->field = 'name';
        $validation->value = 'changed';

        $this->expectException(InvalidConstraintSourceException::class);
        $constraint->validate($validation);
    }

    public function testImmutableValidateReturnsTrueWhenPrimaryKeyNotSet(): void
    {
        $constraint = new Immutable();
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $entity = new ImmutableFixtureEntity();
        $validation = new Validation($this->validator, $entity);
        $validation->field = 'name';
        $validation->value = 'changed';

        $this->entityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(ImmutableFixtureEntity::class)
            ->willReturn('id');

        $this->assertTrue($constraint->validate($validation));
    }

    public function testImmutableValidateReturnsTrueWhenValueUnchanged(): void
    {
        $constraint = new Immutable();
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $entity = new ImmutableFixtureEntity();
        $entity->id = 3;

        $validation = new Validation($this->validator, $entity);
        $validation->field = 'name';
        $validation->value = 'same';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('value')
            ->with(['id' => 3], 'name')
            ->willReturn('same');

        $this->entityMetadata->expects($this->once())->method('getPrimaryKey')->with(ImmutableFixtureEntity::class)->willReturn('id');
        $this->entityMetadata->expects($this->once())->method('getRepository')->with(ImmutableFixtureEntity::class)->willReturn($repository);

        $this->assertTrue($constraint->validate($validation));
    }

    public function testImmutableValidateReturnsFalseWhenValueChanged(): void
    {
        $constraint = new Immutable();
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $entity = new ImmutableFixtureEntity();
        $entity->id = 3;

        $validation = new Validation($this->validator, $entity);
        $validation->field = 'name';
        $validation->value = 'new-value';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('value')
            ->with(['id' => 3], 'name')
            ->willReturn('old-value');

        $this->entityMetadata->expects($this->once())->method('getPrimaryKey')->with(ImmutableFixtureEntity::class)->willReturn('id');
        $this->entityMetadata->expects($this->once())->method('getRepository')->with(ImmutableFixtureEntity::class)->willReturn($repository);

        $this->assertFalse($constraint->validate($validation));
    }

    public function testUniqueValidateThrowsWhenSourceIsArray(): void
    {
        $constraint = new Unique();
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $validation = new Validation($this->validator, ['name' => 'Test']);
        $validation->field = 'name';
        $validation->value = 'Test';

        $this->expectException(InvalidConstraintSourceException::class);
        $constraint->validate($validation);
    }

    public function testUniqueValidateReturnsFalseWhenRepositoryFindsDuplicateWithFiltersAndPrimaryKeyExclusion(): void
    {
        $constraint = new Unique(['company_id', 'status' => 'active']);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $entity = new UniqueFixtureEntity();
        $entity->id = 12;
        $entity->company_id = 8;

        $validation = new Validation($this->validator, $entity);
        $validation->field = 'email';
        $validation->value = 'dup@example.com';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('exists')
            ->with([
                'email' => 'dup@example.com',
                'company_id' => 8,
                'status' => 'active',
                'id!=' => 12,
            ])
            ->willReturn(true);

        $this->entityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(UniqueFixtureEntity::class)
            ->willReturn('id');
        $this->entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(UniqueFixtureEntity::class)
            ->willReturn($repository);

        $this->assertFalse($constraint->validate($validation));
    }

    public function testUniqueValidateReturnsTrueWhenNoDuplicateAndNoPrimaryKeyOnSource(): void
    {
        $constraint = new Unique(['tenant_id']);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $entity = new UniqueFixtureEntity();
        $entity->tenant_id = 5;

        $validation = new Validation($this->validator, $entity);
        $validation->field = 'username';
        $validation->value = 'fresh';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('exists')
            ->with([
                'username' => 'fresh',
                'tenant_id' => 5,
            ])
            ->willReturn(false);

        $this->entityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(UniqueFixtureEntity::class)
            ->willReturn('id');
        $this->entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(UniqueFixtureEntity::class)
            ->willReturn($repository);

        $this->assertTrue($constraint->validate($validation));
    }

    public function testUniqueValidateReturnsFalseWhenDuplicateAndNoPrimaryKeyOnSource(): void
    {
        $constraint = new Unique(['company_id']);
        $this->injectProperty($constraint, 'entityMetadata', $this->entityMetadata);

        $entity = new UniqueFixtureEntity();
        $entity->company_id = 8;

        $validation = new Validation($this->validator, $entity);
        $validation->field = 'email';
        $validation->value = 'dup@example.com';

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects($this->once())
            ->method('exists')
            ->with([
                'email' => 'dup@example.com',
                'company_id' => 8,
            ])
            ->willReturn(true);

        $this->entityMetadata->expects($this->once())
            ->method('getPrimaryKey')
            ->with(UniqueFixtureEntity::class)
            ->willReturn('id');
        $this->entityMetadata->expects($this->once())
            ->method('getRepository')
            ->with(UniqueFixtureEntity::class)
            ->willReturn($repository);

        $this->assertFalse($constraint->validate($validation));
    }

    private function injectProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setValue($object, $value);
    }
}

class ExistsFixtureOrder extends Entity
{
    public ?int $user_id = null;
    public ?int $ghost_id = null;
}

class ExistsFixtureUser extends Entity
{
    public ?int $user_id = null;
}

class User extends Entity
{
    public ?int $user_id = null;
}

class ImmutableFixtureEntity extends Entity
{
    public ?int $id = null;
    public ?string $name = null;
}

class UniqueFixtureEntity extends Entity
{
    public ?int $id = null;
    public ?int $company_id = null;
    public ?int $tenant_id = null;
}
