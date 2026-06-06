<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use ReflectionClass;
use Switon\Orm\EntityManager;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\Tests\TestCase;

use function array_map;
use function class_exists;
use function interface_exists;
use function is_subclass_of;

class EntityManagerBasicTest extends TestCase
{
    public function testEntityManagerClassExists(): void
    {
        $this->assertTrue(class_exists(EntityManager::class, true));
        $this->assertTrue(interface_exists(EntityManagerInterface::class, true));
    }

    public function testEntityManagerImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(EntityManager::class, EntityManagerInterface::class));
    }

    public function testEntityManagerInterfaceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(EntityManagerInterface::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('create', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('put', $methods);
        $this->assertContains('query', $methods);
    }

    public function testEntityManagerClassHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(EntityManager::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('create', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('put', $methods);
        $this->assertContains('query', $methods);
    }
}
