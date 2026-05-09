<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Orm\AbstractEntityManager;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Query\QueryInterface;
use Switon\Validating\ConstraintInterface;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
class AbstractEntityManagerCoverageTest extends TestCase
{
    public function testValidateSkipsFieldAssignmentWhenConstraintFailsAndBreaks(): void
    {
        $entity = new class extends Entity {
            public ?string $name = null;
        };
        $entity->name = 'original';

        $validator = $this->createMock(ValidatorInterface::class);
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $queryBuilder = $this->createMock(QueryBuilderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $relationManager = $this->createMock(RelationManagerInterface::class);

        $constraint1 = $this->createMock(ConstraintInterface::class);
        $constraint2 = $this->createMock(ConstraintInterface::class);

        $constraint1->expects($this->once())->method('validate')->willReturn(false);
        $constraint1->expects($this->once())->method('getMessage')->willReturn('invalid');
        $constraint2->expects($this->never())->method('validate');

        $entityMetadata->expects($this->once())
            ->method('getConstraints')
            ->with($entity::class)
            ->willReturn([
                'name' => [$constraint1, $constraint2],
            ]);

        $validator->expects($this->once())
            ->method('beginValidate')
            ->willReturnCallback(static function (array|object $source) use ($validator, $entity): Validation {
                // Use real Validation so AbstractEntityManager's control-flow is exercised.
                return new Validation($validator, $source);
            });

        $validator->expects($this->once())
            ->method('formatMessage')
            ->willReturn('error');

        $validator->expects($this->once())
            ->method('endValidate')
            ->with($this->isInstanceOf(Validation::class));

        $em = new TestEntityManager($queryBuilder);
        $em->setForTest($entityMetadata, $validator, $eventDispatcher, $relationManager);

        $em->runValidate($entity, ['name']);

        // Since first constraint fails, AbstractEntityManager should not assign validation value back to the entity field.
        $this->assertSame('original', $entity->name);
    }

    public function testValidateAssignsNewValueBackToEntityWhenAllConstraintsPass(): void
    {
        $entity = new class extends Entity {
            public ?string $name = null;
        };
        $entity->name = 'original';

        $validator = $this->createMock(ValidatorInterface::class);
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $queryBuilder = $this->createMock(QueryBuilderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $relationManager = $this->createMock(RelationManagerInterface::class);

        $constraint1 = $this->createMock(ConstraintInterface::class);
        $constraint2 = $this->createMock(ConstraintInterface::class);

        $seen = ['field' => null, 'value' => null];
        $constraint1->expects($this->once())->method('validate')->willReturnCallback(static function (Validation $validation) use (&$seen): bool {
            $seen['field'] = $validation->field;
            $seen['value'] = $validation->value;
            $validation->value = 'validated';
            return true;
        });
        $constraint1->expects($this->never())->method('getMessage');
        $constraint2->expects($this->once())->method('validate')->willReturn(true);

        $entityMetadata->expects($this->once())
            ->method('getConstraints')
            ->with($entity::class)
            ->willReturn([
                'name' => [$constraint1, $constraint2],
            ]);

        $validator->expects($this->once())
            ->method('beginValidate')
            ->willReturnCallback(static function (array|object $source) use ($validator): Validation {
                return new Validation($validator, $source);
            });

        $validator->expects($this->never())
            ->method('formatMessage');

        $validator->expects($this->once())
            ->method('endValidate')
            ->with($this->isInstanceOf(Validation::class));

        $em = new TestEntityManager($queryBuilder);
        $em->setForTest($entityMetadata, $validator, $eventDispatcher, $relationManager);

        $em->runValidate($entity, ['name']);

        $this->assertSame('name', $seen['field']);
        $this->assertSame('original', $seen['value']);
        $this->assertSame('validated', $entity->name);
    }

    public function testQueryDelegatesToQueryBuilderCreate(): void
    {
        $queryBuilder = $this->createMock(QueryBuilderInterface::class);
        $query = $this->createMock(QueryInterface::class);

        $queryBuilder->expects($this->once())
            ->method('create')
            ->with('App\\Entity\\User', 'u')
            ->willReturn($query);

        $em = new TestEntityManager($queryBuilder);

        $this->assertSame($query, $em->query('App\\Entity\\User', 'u'));
    }
}

/**
 * Minimal concrete EntityManager for exercising AbstractEntityManager::validate/query.
 */
class TestEntityManager extends AbstractEntityManager
{
    public function __construct(private QueryBuilderInterface $qb)
    {
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->qb;
    }

    public function create(Entity $entity): Entity
    {
        return $entity;
    }

    public function createMany(array $entities): array
    {
        return $entities;
    }

    public function put(Entity $entity): Entity
    {
        return $entity;
    }

    public function update(Entity $entity, Entity $original): Entity
    {
        return $entity;
    }

    public function delete(Entity $entity): Entity
    {
        return $entity;
    }

    public function setForTest(
        EntityMetadataInterface  $entityMetadata,
        ValidatorInterface       $validator,
        EventDispatcherInterface $eventDispatcher,
        RelationManagerInterface $relationManager,
    ): void
    {
        $this->entityMetadata = $entityMetadata;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->relationManager = $relationManager;
    }

    public function runValidate(Entity $entity, array $fields): void
    {
        $this->validate($entity, $fields);
    }
}

