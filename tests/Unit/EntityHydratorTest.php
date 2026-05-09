<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ArrayableInterface;
use Switon\Core\Exception\RuntimeException;
use Switon\Orm\Attribute\DateFormat;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\HydratableInterface;
use Switon\Orm\Tests\Fixtures\TestColor;
use Switon\Orm\Tests\Fixtures\TestPriority;
use Switon\Orm\Tests\Fixtures\TestStatus;
use Switon\Orm\Tests\TestCase;

#[DateFormat('Y-m-d H:i:s')]
class TestHydratorEntity extends Entity
{
    #[Id]
    public int $id;

    public ?string $name = null;
    public ?int $age = null;
    public ?float $ratio = null;
    public bool $active = false;
    public ?TestStatus $status = null;
    public ?TestPriority $priority = null;
    public ?TestColor $color = null;
    public ?array $metadata = null;
    public ?DateTimeImmutable $created_at = null;
}

#[DateFormat('U')]
class TestHydratorFieldDateFormatEntity extends Entity
{
    #[Id]
    public int $id;

    #[DateFormat('Y-m-d H:i:s')]
    public ?DateTimeImmutable $created_at = null;

    public ?DateTimeImmutable $updated_at = null;
}

class TestHydratableValueObject implements HydratableInterface, ArrayableInterface
{
    protected bool $uppercaseOnDehydrate = false;

    public function __construct(public string $value)
    {
    }

    public static function hydrate(Entity $entity, string $field, mixed $value): static
    {
        $prefix = $entity->prefix ?? '';
        $prefix = $prefix !== '' ? $prefix . ':' : '';

        $instance = new static($prefix . (string)$value . '@' . $field);
        $instance->uppercaseOnDehydrate = ($entity->prefix ?? '') !== '';

        return $instance;
    }

    public function dehydrate(): mixed
    {
        if ($this->uppercaseOnDehydrate) {
            return strtoupper($this->value);
        }

        return $this->value;
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}

class TestHydratableEntity extends Entity
{
    #[Id]
    public int $id;

    public ?string $prefix = null;

    public ?TestHydratableValueObject $payload = null;
}

#[AllowMockObjectsWithoutExpectations]
class EntityHydratorTest extends TestCase
{
    #[Autowired] protected EntityHydratorInterface $entityHydrator;

    public function testHydrateCastsCommonTypes(): void
    {
        $entity = $this->entityHydrator->hydrate(TestHydratorEntity::class, [
            'id' => '7',
            'name' => 'Alice',
            'age' => '42',
            'ratio' => '2.5',
            'active' => 'false',
            'status' => 1,
            'priority' => 'High',
            'color' => 'red',
            'metadata' => '{"tags":["orm","hydrator"]}',
            'created_at' => '2024-01-02 03:04:05',
        ]);

        $this->assertInstanceOf(TestHydratorEntity::class, $entity);
        $this->assertSame(7, $entity->id);
        $this->assertSame('Alice', $entity->name);
        $this->assertSame(42, $entity->age);
        $this->assertSame(2.5, $entity->ratio);
        $this->assertFalse($entity->active);
        $this->assertSame(TestStatus::Active, $entity->status);
        $this->assertSame(TestPriority::High, $entity->priority);
        $this->assertSame(TestColor::Red, $entity->color);
        $this->assertSame(['tags' => ['orm', 'hydrator']], $entity->metadata);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->created_at);
        $this->assertSame('2024-01-02 03:04:05', $entity->created_at->format('Y-m-d H:i:s'));
    }

    public function testHydrateIntoKeepsUnlistedFieldsUntouched(): void
    {
        $entity = new TestHydratorEntity();
        $entity->id = 1;
        $entity->name = 'Original';
        $entity->age = 18;

        $this->entityHydrator->hydrateInto($entity, [
            'name' => 'Updated',
            'metadata' => '{"role":"admin"}',
        ], ['name', 'metadata']);

        $this->assertSame(1, $entity->id);
        $this->assertSame('Updated', $entity->name);
        $this->assertSame(18, $entity->age);
        $this->assertSame(['role' => 'admin'], $entity->metadata);
    }

    public function testDehydrateConvertsCommonTypes(): void
    {
        $entity = new TestHydratorEntity();
        $entity->id = 9;
        $entity->name = 'Alice';
        $entity->age = 42;
        $entity->ratio = 2.5;
        $entity->active = true;
        $entity->status = TestStatus::Inactive;
        $entity->priority = TestPriority::Low;
        $entity->color = TestColor::Blue;
        $entity->metadata = ['tags' => ['orm', 'hydrator']];
        $entity->created_at = new DateTimeImmutable('2024-01-02 03:04:05');

        $data = $this->entityHydrator->dehydrate($entity);

        $this->assertSame(9, $data['id']);
        $this->assertSame('Alice', $data['name']);
        $this->assertSame(42, $data['age']);
        $this->assertSame(2.5, $data['ratio']);
        $this->assertTrue($data['active']);
        $this->assertSame(0, $data['status']);
        $this->assertSame('Low', $data['priority']);
        $this->assertSame('blue', $data['color']);
        $this->assertSame('{"tags":["orm","hydrator"]}', $data['metadata']);
        $this->assertSame('2024-01-02 03:04:05', $data['created_at']);
    }

    public function testHydrateAndDehydrateSupportFieldLevelDateFormatOverride(): void
    {
        $entity = $this->entityHydrator->hydrate(TestHydratorFieldDateFormatEntity::class, [
            'id' => 1,
            'created_at' => '2024-01-02 03:04:05',
            'updated_at' => 1704164645,
        ]);

        $this->assertInstanceOf(DateTimeImmutable::class, $entity->created_at);
        $this->assertSame('2024-01-02 03:04:05', $entity->created_at?->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->updated_at);
        $this->assertSame(1704164645, (int)$entity->updated_at?->format('U'));

        $data = $this->entityHydrator->dehydrate($entity);

        $this->assertSame('2024-01-02 03:04:05', $data['created_at']);
        $this->assertSame(1704164645, $data['updated_at']);
    }

    public function testHydrateAndDehydrateSupportHydratableValueObjectsWithEntityContext(): void
    {
        $entity = $this->entityHydrator->hydrate(TestHydratableEntity::class, [
            'id' => 1,
            'prefix' => 'pre',
            'payload' => 'raw',
        ]);

        $this->assertInstanceOf(TestHydratableValueObject::class, $entity->payload);
        $this->assertSame('pre:raw@payload', $entity->payload?->value);

        $data = $this->entityHydrator->dehydrate($entity);

        $this->assertSame('PRE:RAW@PAYLOAD', $data['payload']);
    }

    public function testHydrateThrowsOnInvalidJsonForArrayField(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode JSON');

        $this->entityHydrator->hydrate(TestHydratorEntity::class, [
            'id' => 1,
            'metadata' => '{"broken":',
        ]);
    }

    public function testHydrateThrowsOnNonArrayDecodedJsonForArrayField(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected JSON array payload');

        $this->entityHydrator->hydrate(TestHydratorEntity::class, [
            'id' => 1,
            'metadata' => '"just-string"',
        ]);
    }

    public function testHydrateEnumThrowsWhenCaseDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('case "NotExists" does not exist');

        $this->entityHydrator->hydrate(TestHydratorEntity::class, [
            'id' => 1,
            'priority' => 'NotExists',
        ]);
    }

    public function testHydrateEnumReturnsSameInstanceWhenAlreadyHydratedEnum(): void
    {
        $status = TestStatus::Inactive;

        $entity = $this->entityHydrator->hydrate(TestHydratorEntity::class, [
            'id' => 1,
            'status' => $status,
        ]);

        $this->assertSame($status, $entity->status);
    }

    public function testHydrateEnumThrowsWhenBackedEnumCaseDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('case "999" does not exist');

        $this->entityHydrator->hydrate(TestHydratorEntity::class, [
            'id' => 1,
            'status' => 999,
        ]);
    }

    public function testHydrateDateValueReturnsSameInstanceWhenValueIsDateTimeInterface(): void
    {
        $createdAt = new DateTimeImmutable('2024-01-02 03:04:05');
        $updatedAt = new DateTimeImmutable('2024-01-02 03:04:05');

        $entity = $this->entityHydrator->hydrate(TestHydratorFieldDateFormatEntity::class, [
            'id' => 1,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        $this->assertSame($createdAt, $entity->created_at);
        $this->assertSame($updatedAt, $entity->updated_at);
    }

    public function testHydrateDateValueThrowsWhenDateDoesNotMatchFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to hydrate date');

        $this->entityHydrator->hydrate(TestHydratorFieldDateFormatEntity::class, [
            'id' => 1,
            'created_at' => 'invalid date payload',
            'updated_at' => 1704164645,
        ]);
    }

    public function testHydrateIntoNormalizesKeyedFieldsMapWhenFieldsProvided(): void
    {
        $entity = new TestHydratorEntity();
        $entity->id = 1;
        $entity->name = 'Original';
        $entity->metadata = ['role' => 'guest'];

        $this->entityHydrator->hydrateInto($entity, [
            'name' => 'Updated',
            'metadata' => '{"role":"admin"}',
        ], [
            'name' => true,
            'metadata' => true,
        ]);

        $this->assertSame('Updated', $entity->name);
        $this->assertSame(['role' => 'admin'], $entity->metadata);
    }

    public function testDehydrateThrowsWhenJsonEncodingFailsForArrayField(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode JSON');

        // Invalid UTF-8 sequence to force json_encode to throw with JSON_THROW_ON_ERROR.
        $bad = "\xB1\x31";

        $entity = new TestHydratorEntity();
        $entity->id = 1;
        $entity->metadata = [
            'tags' => ['orm', $bad],
        ];

        $this->entityHydrator->dehydrate($entity);
    }
}
