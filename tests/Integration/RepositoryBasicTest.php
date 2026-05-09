<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionClass;
use Switon\Orm\Repository;
use Switon\Orm\RepositoryInterface;
use Switon\Orm\Tests\TestCase;
use function array_map;
use function class_exists;
use function interface_exists;
use function is_subclass_of;

#[AllowMockObjectsWithoutExpectations]
class RepositoryBasicTest extends TestCase
{
    public function testRepositoryClassExists(): void
    {
        $this->assertTrue(class_exists(Repository::class, true));
        $this->assertTrue(interface_exists(RepositoryInterface::class, true));
    }

    public function testRepositoryImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(Repository::class, RepositoryInterface::class));
    }

    public function testRepositoryInterfaceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RepositoryInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('find', $methods);
        $this->assertContains('create', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('all', $methods);
        $this->assertContains('get', $methods);
    }

    public function testRepositoryClassHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(Repository::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('find', $methods);
        $this->assertContains('create', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('all', $methods);
        $this->assertContains('get', $methods);
    }
}
