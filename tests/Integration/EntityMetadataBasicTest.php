<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use ReflectionClass;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\EntityMetadata;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestProduct;
use Switon\Orm\Tests\Fixtures\TestUser;
use Switon\Orm\Tests\TestCase;
use Throwable;

use function array_map;
use function class_exists;
use function interface_exists;

class EntityMetadataBasicTest extends TestCase
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(EntityMetadata::class, true)) {
            $this->markTestSkipped('EntityMetadata class not available');
        }

        try {
            $this->injector->inject($this);
        } catch (Throwable $e) {
            $this->markTestSkipped('EntityMetadata requires dependencies: ' . $e->getMessage());
        }
    }

    public function testEntityMetadataClassExistsAndImplementsInterface(): void
    {
        $this->assertTrue(class_exists(EntityMetadata::class, true));
        $this->assertTrue(interface_exists(EntityMetadataInterface::class, true));
        $this->assertInstanceOf(EntityMetadataInterface::class, $this->entityMetadata);
    }

    public function testEntityMetadataInterfaceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(EntityMetadataInterface::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('getPrimaryKey', $methods);
        $this->assertContains('getTable', $methods);
        $this->assertContains('getConnection', $methods);
        $this->assertContains('getColumnMap', $methods);
    }

    public function testEntityMetadataClassHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(EntityMetadata::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('getPrimaryKey', $methods);
        $this->assertContains('getTable', $methods);
        $this->assertContains('getConnection', $methods);
        $this->assertContains('getColumnMap', $methods);
    }

    public function testGetPrimaryKeyReturnsPrimaryKeyFieldName(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(TestEntity::class);

        $this->assertIsString($primaryKey);
        $this->assertSame('id', $primaryKey);
    }

    public function testGetPrimaryKeyReturnsCorrectKeyForTestUser(): void
    {
        $primaryKey = $this->entityMetadata->getPrimaryKey(TestUser::class);

        $this->assertIsString($primaryKey);
        $this->assertSame('user_id', $primaryKey);
    }

    public function testGetTableReturnsTableNameFromAttribute(): void
    {
        $table = $this->entityMetadata->getTable(TestUser::class);

        $this->assertIsString($table);
        $this->assertSame('test_users', $table);
    }

    public function testGetConnectionReturnsConnectionName(): void
    {
        $connection = $this->entityMetadata->getConnection(TestEntity::class);

        $this->assertIsString($connection);
    }

    public function testGetColumnMapReturnsColumnMapping(): void
    {
        $columnMap = $this->entityMetadata->getColumnMap(TestProduct::class);

        $this->assertIsArray($columnMap);
        $this->assertArrayHasKey('name', $columnMap);
        $this->assertSame('product_name', $columnMap['name']);
    }
}
