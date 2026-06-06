<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Core\ArrayableInterface;
use Switon\Orm\Tests\Fixtures\TestColor;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestEntityWithObjects;
use Switon\Orm\Tests\Fixtures\TestJsonSerializableValue;
use Switon\Orm\Tests\Fixtures\TestPriority;
use Switon\Orm\Tests\Fixtures\TestStatus;
use Switon\Orm\Tests\Fixtures\TestStringableValue;
use Switon\Orm\Tests\TestCase;
use DateTimeImmutable;
use stdClass;

use function json_decode;
use function json_encode;

class EntityTest extends TestCase
{
    public function testConstructorInitializesPropertiesFromArray(): void
    {
        $entity = new TestEntity([
            'id' => 1,
            'name' => 'Test Name',
            'status' => 1,
        ]);

        $this->assertSame(1, $entity->id);
        $this->assertSame('Test Name', $entity->name);
        $this->assertSame(1, $entity->status);
    }

    public function testConstructorHandlesEmptyArray(): void
    {
        $entity = new TestEntity();

        $this->assertInstanceOf(TestEntity::class, $entity);
    }

    public function testOffsetGetReturnsPropertyValue(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $id = $entity['id'];
        $name = $entity['name'];

        $this->assertSame(1, $id);
        $this->assertSame('Test', $name);
    }

    public function testOffsetSetSetsPropertyValue(): void
    {
        $entity = new TestEntity();

        $entity['id'] = 1;
        $entity['name'] = 'Test';

        $this->assertSame(1, $entity->id);
        $this->assertSame('Test', $entity->name);
    }

    public function testOffsetExistsChecksPropertyExistence(): void
    {
        $entity = new TestEntity(['id' => 1]);

        $this->assertTrue(isset($entity['id']));
        $this->assertFalse(isset($entity['non_existent']));
    }

    public function testOffsetUnsetUnsetsProperty(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        // Act
        unset($entity['name']);

        // Assert - property should be uninitialized, not null
        $this->assertFalse(isset($entity['name']), 'Property should be uninitialized after offsetUnset');
        $this->assertSame(1, $entity->id);
    }

    public function testToArrayConvertsEntityToArray(): void
    {
        $entity = new TestEntity([
            'id' => 1,
            'name' => 'Test Name',
            'status' => 1,
        ]);

        $array = $entity->toArray();

        $this->assertIsArray($array);
        $this->assertSame(1, $array['id']);
        $this->assertSame('Test Name', $array['name']);
        $this->assertSame(1, $array['status']);
    }

    public function testToArrayExcludesNullValues(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $entity->status = null;

        $array = $entity->toArray();

        $this->assertArrayNotHasKey('status', $array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
    }

    public function testToArrayConvertsBackedEnumToValue(): void
    {
        // Arrange
        $entity = new TestEntityWithObjects();
        $entity->id = 1;
        $entity->status = TestStatus::Active;
        $entity->color = TestColor::Red;

        // Act
        $array = $entity->toArray();

        // Assert
        $this->assertSame(1, $array['status'], 'Int BackedEnum should convert to ->value');
        $this->assertSame('red', $array['color'], 'String BackedEnum should convert to ->value');
    }

    public function testToArrayConvertsJsonSerializableObject(): void
    {
        // Arrange
        $entity = new TestEntityWithObjects();
        $entity->id = 1;
        $entity->metadata = new TestJsonSerializableValue(['key' => 'val']);

        // Act
        $array = $entity->toArray();

        // Assert
        $this->assertSame(['key' => 'val'], $array['metadata'], 'JsonSerializable should convert via ->jsonSerialize()');
    }

    public function testToArrayConvertsStringableObject(): void
    {
        // Arrange
        $entity = new TestEntityWithObjects();
        $entity->id = 1;
        $entity->label = new TestStringableValue('hello');

        // Act
        $array = $entity->toArray();

        // Assert
        $this->assertSame('hello', $array['label'], 'Stringable should convert to string');
    }

    public function testToArrayConvertsDateTimeToAtomString(): void
    {
        $entity = new class () extends \Switon\Orm\Entity {
            public DateTimeImmutable $created_at;
        };
        $entity->created_at = new DateTimeImmutable('2024-01-02 03:04:05+00:00');

        $array = $entity->toArray();

        $this->assertSame('2024-01-02T03:04:05+00:00', $array['created_at']);
    }

    public function testToArrayConvertsArrayableObject(): void
    {
        $entity = new class () extends \Switon\Orm\Entity {
            public mixed $payload = null;
        };
        $entity->payload = new class () implements ArrayableInterface {
            public function toArray(): array
            {
                return [
                    'id' => 11,
                    'created_at' => new DateTimeImmutable('2024-01-02 03:04:05+00:00'),
                ];
            }
        };

        $array = $entity->toArray();

        $this->assertSame(
            [
                'id' => 11,
                'created_at' => '2024-01-02T03:04:05+00:00',
            ],
            $array['payload']
        );
    }

    public function testToArrayDoesNotAutoInvokeToArrayMethodOnNonArrayableObject(): void
    {
        $entity = new class () extends \Switon\Orm\Entity {
            public mixed $payload = null;
        };
        $entity->payload = new class () {
            public function toArray(): array
            {
                return ['id' => 99];
            }
        };

        $array = $entity->toArray();

        $this->assertArrayNotHasKey('payload', $array);
    }

    public function testToArrayConvertsEntityArraysWithoutResettingPointer(): void
    {
        $childA = new class () extends \Switon\Orm\Entity {
            public int $id;
            public string $name;
        };
        $childA->id = 1;
        $childA->name = 'A';

        $childB = new class () extends \Switon\Orm\Entity {
            public int $id;
            public string $name;
        };
        $childB->id = 2;
        $childB->name = 'B';

        $entity = new class () extends \Switon\Orm\Entity {
            public array $items = [];
        };
        $entity->items = ['first' => $childA, 'second' => $childB];

        $array = $entity->toArray();

        $this->assertSame(
            [
                'items' => [
                    'first' => ['id' => 1, 'name' => 'A'],
                    'second' => ['id' => 2, 'name' => 'B'],
                ],
            ],
            $array
        );
    }

    public function testToArrayRecursivelyConvertsJsonSafeValuesInsideArrays(): void
    {
        $child = new class () extends \Switon\Orm\Entity {
            public int $id;
            public string $name;
        };
        $child->id = 9;
        $child->name = 'nested';

        $entity = new class () extends \Switon\Orm\Entity {
            public array $items = [];
        };
        $entity->items = [
            new DateTimeImmutable('2024-01-02 03:04:05+00:00'),
            TestStatus::Active,
            TestPriority::High,
            new TestJsonSerializableValue(['key' => 'val']),
            new TestStringableValue('hello'),
            $child,
            new stdClass(),
            ['color' => TestColor::Blue, 'stamp' => new DateTimeImmutable('2024-01-03 04:05:06+00:00')],
        ];

        $array = $entity->toArray();

        $this->assertSame('2024-01-02T03:04:05+00:00', $array['items'][0]);
        $this->assertSame(1, $array['items'][1]);
        $this->assertSame('High', $array['items'][2]);
        $this->assertSame(['key' => 'val'], $array['items'][3]);
        $this->assertSame('hello', $array['items'][4]);
        $this->assertSame(['id' => 9, 'name' => 'nested'], $array['items'][5]);
        $this->assertNull($array['items'][6]);
        $this->assertSame(
            [
                'color' => 'blue',
                'stamp' => '2024-01-03T04:05:06+00:00',
            ],
            $array['items'][7]
        );
    }

    public function testToArraySkipsUnknownObjects(): void
    {
        // Arrange
        $entity = new TestEntityWithObjects();
        $entity->id = 1;
        $entity->unknown = new stdClass();

        // Act
        $array = $entity->toArray();

        // Assert
        $this->assertArrayNotHasKey('unknown', $array, 'Unknown objects should be silently skipped');
        $this->assertArrayHasKey('id', $array);
    }

    public function testJsonSerializeDelegatesToToArray(): void
    {
        // Arrange
        $entity = new TestEntityWithObjects();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->status = TestStatus::Active;

        // Act
        $serialized = $entity->jsonSerialize();
        $array = $entity->toArray();

        // Assert
        $this->assertSame($array, $serialized, 'jsonSerialize() should delegate to toArray()');
    }

    public function testJsonSerializeExcludesNullValues(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        // Act
        $json = json_encode($entity);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertArrayNotHasKey('status', $decoded, 'Null values should be excluded from JSON');
        $this->assertSame(1, $decoded['id']);
        $this->assertSame('Test', $decoded['name']);
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $json = json_encode($entity);
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertSame(1, $decoded['id']);
        $this->assertSame('Test', $decoded['name']);
    }

    public function testToStringConvertsEntityToJsonString(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $string = (string)$entity;
        $decoded = json_decode($string, true);

        $this->assertIsString($string);
        $this->assertSame(1, $decoded['id']);
        $this->assertSame('Test', $decoded['name']);
    }

    public function testAssignAssignsValuesFromArray(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Original']);

        $result = $entity->assign(['name' => 'Updated', 'status' => 1], ['name', 'status']);

        $this->assertSame($entity, $result);
        $this->assertSame('Updated', $entity->name);
        $this->assertSame(1, $entity->status);
        $this->assertSame(1, $entity->id);
    }

    public function testAssignAssignsValuesFromEntity(): void
    {
        $source = new TestEntity(['id' => 1, 'name' => 'Source Name', 'status' => 1]);
        $target = new TestEntity(['id' => 2, 'name' => 'Target Name']);

        $target->assign($source, ['name', 'status']);

        $this->assertSame('Source Name', $target->name);
        $this->assertSame(1, $target->status);
        $this->assertSame(2, $target->id);
    }

    public function testToArrayConvertsEntityItemsInsideMixedArrayEvenWhenFirstItemIsScalar(): void
    {
        $child = new class () extends \Switon\Orm\Entity {
            public int $id;
            public string $name;
        };
        $child->id = 7;
        $child->name = 'child';

        $entity = new class () extends \Switon\Orm\Entity {
            public array $items = [];
        };
        $entity->items = [0, $child];

        $array = $entity->toArray();

        $this->assertSame(0, $array['items'][0]);
        $this->assertSame(['id' => 7, 'name' => 'child'], $array['items'][1]);
    }

    public function testToArrayHandlesMixedArrayWhenFirstItemIsEntityAndLaterItemIsScalar(): void
    {
        $child = new class () extends \Switon\Orm\Entity {
            public int $id;
            public string $name;
        };
        $child->id = 8;
        $child->name = 'first';

        $entity = new class () extends \Switon\Orm\Entity {
            public array $items = [];
        };
        $entity->items = [$child, 0];

        $array = $entity->toArray();

        $this->assertSame(['id' => 8, 'name' => 'first'], $array['items'][0]);
        $this->assertSame(0, $array['items'][1]);
    }
}
