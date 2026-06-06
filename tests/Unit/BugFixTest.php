<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionClass;
use Switon\Orm\AbstractEntityManager;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadata;
use Switon\Orm\Exception\PrimaryKeyNotFoundException;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestEntityWithUnitEnum;
use Switon\Orm\Tests\Fixtures\TestPriority;
use Switon\Orm\Tests\Fixtures\TestStatus;
use Switon\Orm\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class BugFixTest extends TestCase
{
    // =========================================================================
    // Issue 2: Entity::offsetUnset() sets to null instead of unsetting
    // =========================================================================

    public function testOffsetUnsetResetsTypedPropertyToUninitialized(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        // Act - unset via ArrayAccess
        unset($entity['id']);

        // Assert - property should be uninitialized, not null
        $this->assertFalse(isset($entity['id']), 'Property should be uninitialized after offsetUnset');
    }

    public function testOffsetUnsetDoesNotThrowTypeErrorForNonNullableInt(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 42]);

        // Act - should NOT throw TypeError (the old code set to null which would fail on non-nullable int)
        unset($entity['id']);

        // Assert
        $this->assertFalse(isset($entity->id), 'Non-nullable int property should be uninitialized after unset');
    }

    public function testOffsetUnsetWorksForNullableProperty(): void
    {
        // Arrange
        $entity = new TestEntity(['id' => 1, 'name' => 'Hello']);

        // Act
        unset($entity['name']);

        // Assert
        $this->assertFalse(isset($entity['name']), 'Nullable property should be uninitialized after offsetUnset');
    }

    // =========================================================================
    // Issue 6: Entity::toArray() — silently skips UnitEnum
    // =========================================================================

    public function testToArrayConvertsUnitEnumToName(): void
    {
        // Arrange
        $entity = new TestEntityWithUnitEnum();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->priority = TestPriority::High;

        // Act
        $array = $entity->toArray();

        // Assert - UnitEnum should be converted to its name string
        $this->assertSame('High', $array['priority'], 'UnitEnum should be converted to ->name');
    }

    public function testToArrayConvertsBackedEnumToValue(): void
    {
        // Arrange
        $entity = new TestEntityWithUnitEnum();
        $entity->id = 1;
        $entity->status = TestStatus::Active;

        // Act
        $array = $entity->toArray();

        // Assert - BackedEnum should still convert to ->value (not ->name)
        $this->assertSame(1, $array['status'], 'BackedEnum should be converted to ->value');
    }

    public function testToArrayHandlesBothEnumTypesOnSameEntity(): void
    {
        // Arrange
        $entity = new TestEntityWithUnitEnum();
        $entity->id = 1;
        $entity->priority = TestPriority::Low;
        $entity->status = TestStatus::Inactive;

        // Act
        $array = $entity->toArray();

        // Assert
        $this->assertSame('Low', $array['priority'], 'UnitEnum should use ->name');
        $this->assertSame(0, $array['status'], 'BackedEnum should use ->value');
    }

    // =========================================================================
    // Issue 3: EntityMetadata error message says #[PrimaryKey] instead of #[Id]
    // =========================================================================

    public function testPrimaryKeyNotFoundExceptionMentionsIdAttribute(): void
    {
        // Arrange - entity with no #[Id], no 'id' property, and no inferable primary key
        $entityClass = TestEntityNoPrimaryKey::class;

        // Act & Assert
        try {
            $metadata = $this->make(EntityMetadata::class);
            $metadata->getPrimaryKey($entityClass);
            $this->fail('Expected PrimaryKeyNotFoundException');
        } catch (PrimaryKeyNotFoundException $e) {
            $this->assertStringContainsString('#[Id]', $e->getMessage(), 'Error message should reference #[Id] attribute');
            $this->assertStringNotContainsString('#[PrimaryKey]', $e->getMessage(), 'Error message should NOT reference #[PrimaryKey]');
        }
    }

    // =========================================================================
    // Issue 4: AbstractEntityManager::validate() and dispatchEvent() should be protected
    // =========================================================================

    public function testValidateMethodIsProtected(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AbstractEntityManager::class);
        $method = $reflection->getMethod('validate');

        // Assert
        $this->assertTrue($method->isProtected(), 'validate() should be protected');
    }

    public function testDispatchEventMethodIsProtected(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AbstractEntityManager::class);
        $method = $reflection->getMethod('dispatchEvent');

        // Assert
        $this->assertTrue($method->isProtected(), 'dispatchEvent() should be protected');
    }

    // =========================================================================
    // Issue 8: EntityMetadata::buildRelations() redundant null check removed
    // =========================================================================

    public function testBuildRelationsDoesNotContainRedundantNullCheck(): void
    {
        // Arrange - verify the method works correctly after removing redundant null check
        $metadata = $this->make(EntityMetadata::class);

        // Act - should work without error (no null attribute after non-empty array check)
        $relations = $metadata->getRelations(TestEntity::class);

        // Assert
        $this->assertIsArray($relations);
    }
}

/**
 * Test entity with no primary key defined (no #[Id], no 'id' property, no inferable key).
 */
#[\Switon\Orm\Attribute\Table('test_no_pk_xyz')]
class TestEntityNoPrimaryKey extends Entity
{
    public string $foo;
    public string $bar;
}
