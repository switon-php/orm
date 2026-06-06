<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Orm\Entity;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestProduct;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\TestCase;

use function json_decode;
use function json_encode;
use function time;

class EntityAdvancedTest extends TestCase
{
    public function testOffsetExistsReturnsTrueForExistingProperties(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $this->assertTrue(isset($entity['id']));
        $this->assertTrue(isset($entity['name']));
        $this->assertFalse(isset($entity['nonexistent']));
    }

    public function testOffsetGetReturnsPropertyValues(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $this->assertSame(1, $entity['id']);
        $this->assertSame('Test', $entity['name']);
    }

    public function testOffsetSetModifiesPropertyValues(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $entity['name'] = 'Changed';

        $this->assertSame('Changed', $entity['name']);
        $this->assertSame('Changed', $entity->name);
    }

    public function testOffsetUnsetRemovesPropertyValues(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        // Act
        unset($entity['name']);

        // Assert - property should be uninitialized, not accessible
        $this->assertFalse(isset($entity['name']), 'Property should be uninitialized after offsetUnset');
    }

    public function testEntityHandlesDifferentPropertyTypes(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test', 'status' => 100]);

        $this->assertIsInt($entity->id);
        $this->assertIsString($entity->name);
        $this->assertIsInt($entity->status);
        $this->assertSame(1, $entity->id);
        $this->assertSame('Test', $entity->name);
        $this->assertSame(100, $entity->status);
    }

    public function testEntityHandlesNullableProperties(): void
    {
        // Pass explicit null so nullable properties are initialized (PHP 8+ typed properties)
        $entity = new TestEntity(['id' => 1, 'name' => null, 'status' => null]);

        $this->assertNull($entity->name);
        $this->assertNull($entity->status);
    }

    public function testToArrayReturnsAllProperties(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test', 'status' => 100]);

        $array = $entity->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertSame(1, $array['id']);
        $this->assertSame('Test', $array['name']);
        $this->assertSame(100, $array['status']);
    }

    public function testToArrayExcludesNullValues(): void
    {
        $entity = new TestEntity(['id' => 1]);

        $array = $entity->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayNotHasKey('name', $array);
        $this->assertArrayNotHasKey('status', $array);
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $data = $entity->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
    }

    public function testEntityCanBeJsonEncoded(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test', 'status' => 100]);

        $json = json_encode($entity);
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertJson($json);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['id']);
        $this->assertSame('Test', $decoded['name']);
        $this->assertSame(100, $decoded['status']);
    }

    public function testMultipleEntityInstancesAreIndependent(): void
    {
        $entity1 = new TestEntity(['id' => 1, 'name' => 'Entity1']);
        $entity2 = new TestEntity(['id' => 2, 'name' => 'Entity2']);

        $entity1->name = 'Changed1';
        $entity2->name = 'Changed2';

        $this->assertSame('Changed1', $entity1->name);
        $this->assertSame('Changed2', $entity2->name);
        $this->assertNotSame($entity1->name, $entity2->name);
    }

    public function testEntityWorksWithDifferentSubclasses(): void
    {
        $testEntity = new TestEntity(['id' => 1, 'name' => 'Test']);
        $testUser = new TestUser(['user_id' => 1, 'username' => 'user1', 'email' => 'user1@example.com', 'created_at' => time()]);
        $testProduct = new TestProduct(['product_id' => 1, 'name' => 'Product', 'price' => 99.99, 'stock' => 10]);

        $this->assertInstanceOf(Entity::class, $testEntity);
        $this->assertInstanceOf(Entity::class, $testUser);
        $this->assertInstanceOf(Entity::class, $testProduct);

        $this->assertSame('Test', $testEntity->name);
        $this->assertSame('user1', $testUser->username);
        $this->assertSame('Product', $testProduct->name);
    }
}
