<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Switon\Core\Attribute\Autowired;
use Switon\Core\MakerInterface;
use Switon\Orm\Entity;
use Switon\Orm\EntityFiller;
use Switon\Orm\EntityFillerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\PropertyWriteFillerInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestEntityWithEntityFillerFields;
use Switon\Orm\Tests\Fixtures\TestEntityWithEntityFillerStringFields;
use Switon\Orm\Tests\Fixtures\TestEntityWithPropertyWriteFillerFields;
use Switon\Orm\Tests\TestCase;
use Switon\Principal\IdentityInterface;

use function date;
use function time;

#[AllowMockObjectsWithoutExpectations]
class EntityFillerTest extends TestCase
{
    #[Autowired] protected EntityFillerInterface $entityFiller;
    protected MockObject|IdentityInterface $identity;

    protected function setUp(): void
    {
        parent::setUp();

        // Get the IdentityInterface stub that parent TestCase already set in setUpContainer()
        // Store reference for test method configuration
        $this->identity = $this->container->get(IdentityInterface::class);

        // Note: Default expectations are already set in parent TestCase::setUpContainer()
        // Individual test methods will override these expectations as needed
    }

    public function testFillCreatedFillsAllFieldsForAuthenticatedUser(): void
    {
        $entity = new TestEntityWithEntityFillerFields(['id' => 1, 'name' => 'Test']);
        $userId = 123;
        $userName = 'John Doe';
        $beforeTime = time();

        // Reset stub expectations and set new ones for this test
        // Need to create mock here (not stub) because we're dynamically changing expectations
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('isGuest')->willReturn(false);
        $this->identity->method('getId')->willReturn($userId);
        $this->identity->method('getName')->willReturn($userName);
        $this->identity->method('getRoles')->willReturn([]);

        // Update container and re-inject into EntityFiller
        $this->container->remove(IdentityInterface::class);
        $this->container->set(IdentityInterface::class, $this->identity);
        $this->container->remove(EntityFillerInterface::class);
        $this->injector->inject($this);

        $this->entityFiller->onCreating($entity);
        $afterTime = time();

        $this->assertGreaterThanOrEqual($beforeTime, $entity->created_at);
        $this->assertLessThanOrEqual($afterTime, $entity->created_at);
        $this->assertSame($userName, $entity->created_by);
        $this->assertGreaterThanOrEqual($beforeTime, $entity->updated_at);
        $this->assertLessThanOrEqual($afterTime, $entity->updated_at);
        $this->assertSame($userName, $entity->updated_by);
    }

    public function testFillCreatedFillsFieldsWithGuestValues(): void
    {
        $entity = new TestEntityWithEntityFillerFields(['id' => 1, 'name' => 'Test']);
        $beforeTime = time();

        $this->identity->method('isGuest')->willReturn(true);
        $this->identity->method('getId')->willReturn(0);
        $this->identity->method('getName')->willReturn('');

        $this->entityFiller->onCreating($entity);
        $afterTime = time();

        $this->assertGreaterThanOrEqual($beforeTime, $entity->created_at);
        $this->assertLessThanOrEqual($afterTime, $entity->created_at);
        $this->assertSame('', $entity->created_by);
        $this->assertGreaterThanOrEqual($beforeTime, $entity->updated_at);
        $this->assertLessThanOrEqual($afterTime, $entity->updated_at);
        $this->assertSame('', $entity->updated_by);
    }

    public function testFillCreatedDoesNotOverwriteExistingValues(): void
    {
        $existingCreatedTime = 1000;
        $existingCreatorId = 999;
        $existingCreatorName = 'Existing Creator';
        $entity = new TestEntityWithEntityFillerFields([
            'id' => 1,
            'name' => 'Test',
            'created_at' => $existingCreatedTime,
            'created_by' => $existingCreatorName,
        ]);

        $this->identity->method('isGuest')->willReturn(false);
        $this->identity->method('getId')->willReturn(123);
        $this->identity->method('getName')->willReturn('New User');

        $this->entityFiller->onCreating($entity);

        $this->assertSame($existingCreatedTime, $entity->created_at);
        $this->assertSame($existingCreatorName, $entity->created_by);
    }

    public function testFillUpdatedFillsAllFieldsForAuthenticatedUser(): void
    {
        $entity = new TestEntityWithEntityFillerFields(['id' => 1, 'name' => 'Test']);
        $userId = 456;
        $userName = 'Jane Smith';
        $beforeTime = time();

        // Reset stub expectations and set new ones for this test
        // Need to create mock here (not stub) because we're dynamically changing expectations
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('isGuest')->willReturn(false);
        $this->identity->method('getId')->willReturn($userId);
        $this->identity->method('getName')->willReturn($userName);
        $this->identity->method('getRoles')->willReturn([]);

        // Update container and re-inject into EntityFiller
        $this->container->remove(IdentityInterface::class);
        $this->container->set(IdentityInterface::class, $this->identity);
        $this->container->remove(EntityFillerInterface::class);
        $this->injector->inject($this);

        $this->entityFiller->onUpdating($entity);
        $afterTime = time();

        $this->assertGreaterThanOrEqual($beforeTime, $entity->updated_at);
        $this->assertLessThanOrEqual($afterTime, $entity->updated_at);
        $this->assertSame($userName, $entity->updated_by);
    }

    public function testFillUpdatedFillsFieldsWithGuestValues(): void
    {
        $entity = new TestEntityWithEntityFillerFields(['id' => 1, 'name' => 'Test']);
        $beforeTime = time();

        $this->identity->method('isGuest')->willReturn(true);
        $this->identity->method('getId')->willReturn(0);
        $this->identity->method('getName')->willReturn('');

        $this->entityFiller->onUpdating($entity);
        $afterTime = time();

        $this->assertGreaterThanOrEqual($beforeTime, $entity->updated_at);
        $this->assertLessThanOrEqual($afterTime, $entity->updated_at);
        $this->assertSame('', $entity->updated_by);
    }

    public function testFillUpdatedAlwaysOverwritesExistingValues(): void
    {
        $existingUpdatedTime = 2000;
        $existingUpdatorName = 'Old Updator';
        $entity = new TestEntityWithEntityFillerFields([
            'id' => 1,
            'name' => 'Test',
            'updated_at' => $existingUpdatedTime,
            'updated_by' => $existingUpdatorName,
        ]);

        $newUserId = 777;
        $newUserName = 'New Updator';
        $beforeTime = time();

        // Reset stub expectations and set new ones for this test
        // Need to create mock here (not stub) because we're dynamically changing expectations
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('isGuest')->willReturn(false);
        $this->identity->method('getId')->willReturn($newUserId);
        $this->identity->method('getName')->willReturn($newUserName);
        $this->identity->method('getRoles')->willReturn([]);

        // Update container and re-inject into EntityFiller
        $this->container->remove(IdentityInterface::class);
        $this->container->set(IdentityInterface::class, $this->identity);
        $this->container->remove(EntityFillerInterface::class);
        $this->injector->inject($this);

        $this->entityFiller->onUpdating($entity);
        $afterTime = time();

        $this->assertGreaterThanOrEqual($beforeTime, $entity->updated_at);
        $this->assertLessThanOrEqual($afterTime, $entity->updated_at);
        $this->assertNotSame($existingUpdatedTime, $entity->updated_at);
        $this->assertSame($newUserName, $entity->updated_by);
    }

    public function testSetAtUsesProvidedTimestampForStringField(): void
    {
        $filler = new class () extends EntityFiller {
            public function setDateFormat(string $format): void
            {
                $this->date_format = $format;
            }

            public function callSetAt(Entity $entity, string $field, int $timestamp): void
            {
                $this->setAt($entity, $field, $timestamp);
            }
        };

        $this->injector->inject($filler);

        $timestamp = 1704067200;
        $format = 'Y-m-d H:i:s';

        $entity = new TestEntityWithEntityFillerStringFields([
            'id' => 1,
            'name' => 'Test',
        ]);

        $filler->setDateFormat($format);
        $filler->callSetAt($entity, 'created_at', $timestamp);

        $this->assertSame(
            date($format, $timestamp),
            $entity->created_at,
            'setAt() should format string timestamp fields using the provided timestamp parameter'
        );
    }

    public function testPropertyWriteFillersApplyOnCreating(): void
    {
        $entity = new TestEntityWithPropertyWriteFillerFields([
            'id' => 1,
            'status' => 1,
            'editor_id' => 0,
            'read_at' => 99,
        ]);

        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('isGuest')->willReturn(false);
        $this->identity->method('getId')->willReturn(321);
        $this->identity->method('getName')->willReturn('Writer');
        $this->identity->method('getRoles')->willReturn([]);

        $this->container->remove(IdentityInterface::class);
        $this->container->set(IdentityInterface::class, $this->identity);
        $this->container->remove(EntityFillerInterface::class);
        $this->injector->inject($this);

        $this->entityFiller->onCreating($entity);

        $this->assertSame(321, $entity->owner_id);
        $this->assertSame('Writer', $entity->owner_name);
        $this->assertSame(0, $entity->read_at);
        $this->assertSame(321, $entity->editor_id);
        $this->assertGreaterThan(0, $entity->published_at);
    }

    public function testCurrentTimeKeepsExistingValueWhenConfiguredAsWhenEmpty(): void
    {
        $entity = new TestEntityWithPropertyWriteFillerFields([
            'id' => 1,
            'status' => 1,
            'published_at' => 123,
        ]);

        $this->entityFiller->onUpdating($entity);

        $this->assertSame(123, $entity->published_at);
    }

    public function testCurrentTimeUsesElseValueWhenConditionDoesNotMatch(): void
    {
        $entity = new TestEntityWithPropertyWriteFillerFields([
            'id' => 1,
            'status' => 0,
            'published_at' => 123,
        ]);

        $this->entityFiller->onUpdating($entity);

        $this->assertSame(0, $entity->published_at);
    }

    public function testOnCreatingDoesNothingWhenEntityHasNoAuditOrStrategyFields(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Only core columns']);

        $this->entityFiller->onCreating($entity);

        $this->assertSame(1, $entity->id);
        $this->assertSame('Only core columns', $entity->name);
    }

    public function testSetAtSkipsWhenMetadataFieldTypeIsNotIntOrString(): void
    {
        $filler = new class () extends EntityFiller {
            public function callSetAt(Entity $entity, string $field, int $timestamp): void
            {
                $this->setAt($entity, $field, $timestamp);
            }
        };

        $entity = new class () extends Entity {
            public float $marker = 1.25;
        };

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->expects($this->once())
            ->method('getFieldType')
            ->with($entity::class, 'marker')
            ->willReturn('float');

        $metaProp = (new ReflectionClass(EntityFiller::class))->getProperty('entityMetadata');
        $metaProp->setAccessible(true);
        $metaProp->setValue($filler, $metadata);

        $filler->callSetAt($entity, 'marker', 99_999);

        $this->assertSame(1.25, $entity->marker);
    }

    public function testSetBySkipsWhenMetadataFieldTypeIsNotIntOrString(): void
    {
        $filler = new class () extends EntityFiller {
            public function callSetBy(Entity $entity, string $field): void
            {
                $this->setBy($entity, $field);
            }
        };

        $entity = new class () extends Entity {
            public float $actor = 2.5;
        };

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->expects($this->once())
            ->method('getFieldType')
            ->with($entity::class, 'actor')
            ->willReturn('float');

        $identity = $this->createStub(IdentityInterface::class);
        $identity->method('isGuest')->willReturn(false);
        $identity->method('getId')->willReturn(42);
        $identity->method('getName')->willReturn('ignored');

        $metaProp = (new ReflectionClass(EntityFiller::class))->getProperty('entityMetadata');
        $metaProp->setAccessible(true);
        $metaProp->setValue($filler, $metadata);

        $idProp = (new ReflectionClass(EntityFiller::class))->getProperty('identity');
        $idProp->setAccessible(true);
        $idProp->setValue($filler, $identity);

        $filler->callSetBy($entity, 'actor');

        $this->assertSame(2.5, $entity->actor);
    }

    public function testPropertyWriteStrategiesUseMakerWhenInjected(): void
    {
        $this->assertInstanceOf(EntityFiller::class, $this->entityFiller);

        $strategy = new class () implements PropertyWriteFillerInterface {
            public int $creatingCalls = 0;

            public int $updatingCalls = 0;

            public function onCreating(Entity $entity, ReflectionProperty $property): void
            {
                $this->creatingCalls++;
            }

            public function onUpdating(Entity $entity, ReflectionProperty $property): void
            {
                $this->updatingCalls++;
            }
        };

        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->exactly(10))
            ->method('make')
            ->willReturnCallback(static fn (string $name, array $arguments) => $strategy);

        $makerProp = (new ReflectionClass(EntityFiller::class))->getProperty('maker');
        $makerProp->setAccessible(true);
        $previousMaker = $makerProp->getValue($this->entityFiller);

        $makerProp->setValue($this->entityFiller, $maker);

        try {
            $entity = new TestEntityWithPropertyWriteFillerFields([
                'id' => 1,
                'status' => 1,
                'editor_id' => 0,
                'read_at' => 99,
            ]);

            $this->entityFiller->onCreating($entity);
            $this->assertSame(5, $strategy->creatingCalls);

            $this->entityFiller->onUpdating($entity);
            $this->assertSame(5, $strategy->updatingCalls);
        } finally {
            $makerProp->setValue($this->entityFiller, $previousMaker);
        }
    }
}
