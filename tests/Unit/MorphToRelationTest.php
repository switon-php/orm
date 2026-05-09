<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Relation\MorphToRelation;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
final class MorphToRelationTest extends TestCase
{
    public function testGetRelatedQueryDelegatesToEntityMetadataForSelfPlaceholder(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $query = $this->createMock(QueryInterface::class);

        $entityMetadata->expects($this->once())
            ->method('createQuery')
            ->with(TestSelfEntity::class)
            ->willReturn($query);

        $relation = new MorphToRelation('morph_table', 'morph_id');

        $selfProperty = new \ReflectionProperty(MorphToRelation::class, 'selfEntityClass');
        $selfProperty->setValue($relation, TestSelfEntity::class);

        $metaProperty = new \ReflectionProperty(MorphToRelation::class, 'entityMetadata');
        $metaProperty->setValue($relation, $entityMetadata);

        $this->assertSame($query, $relation->getRelatedQuery());
    }
}

final class TestSelfEntity extends Entity
{
    public ?int $id = null;
}
