<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Tests\Fixtures\TestEntityMetadataChild;
use Switon\Orm\Tests\Fixtures\TestEntityMetadataFillableRules;
use Switon\Orm\Tests\TestCase;
use Switon\Validating\ConstraintInterface;

class EntityMetadataAdditionalCoverageTest extends TestCase
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector->inject($this);
    }

    public function testGetFillableRespectsFillableFalseOverConstraints(): void
    {
        $fillable = $this->entityMetadata->getFillable(TestEntityMetadataFillableRules::class);

        // #[Id] is always fillable
        $this->assertArrayHasKey('id', $fillable);

        // Only #[Id] or #[Fillable] is fillable; constraint-only is not
        $this->assertArrayNotHasKey('title', $fillable);

        // #[Fillable] => fillable
        $this->assertArrayHasKey('explicit', $fillable);
        $this->assertArrayHasKey('fillableAttribute', $fillable);

        // No #[Fillable] => not fillable
        $this->assertArrayNotHasKey('name', $fillable);
        $this->assertArrayNotHasKey('plain', $fillable);
    }

    public function testGetConstraintsIncludesConstraintInstances(): void
    {
        $constraints = $this->entityMetadata->getConstraints(TestEntityMetadataFillableRules::class);

        $this->assertArrayHasKey('title', $constraints);
        $this->assertNotEmpty($constraints['title']);
        $this->assertInstanceOf(ConstraintInterface::class, $constraints['title'][0] ?? null);

        // Even if fillable is false, constraints should still be discoverable
        $this->assertArrayHasKey('name', $constraints);
        $this->assertNotEmpty($constraints['name']);
        $this->assertInstanceOf(ConstraintInterface::class, $constraints['name'][0] ?? null);
    }

    public function testGetFieldsIncludesInheritedAndTraitPropertiesAndSkipsTransient(): void
    {
        $fields = $this->entityMetadata->getFields(TestEntityMetadataChild::class);

        $this->assertContains('id', $fields);
        $this->assertContains('parent_field', $fields);
        $this->assertContains('trait_field', $fields);
        $this->assertContains('child_field', $fields);
        $this->assertNotContains('transient_field', $fields);

        $this->assertSame('id', $this->entityMetadata->getPrimaryKey(TestEntityMetadataChild::class));
    }
}
