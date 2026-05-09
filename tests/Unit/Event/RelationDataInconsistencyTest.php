<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Event;

use Switon\Orm\Event\RelationDataInconsistency;
use Switon\Orm\Tests\TestCase;

class RelationDataInconsistencyTest extends TestCase
{
    public function testEventCanBeCreated(): void
    {
        $event = new RelationDataInconsistency(
            relationName: 'posts',
            parentEntityClass: 'App\Entity\User',
            relatedEntityClass: 'App\Entity\Post',
            foreignKeyField: 'user_id',
            orphanedForeignKeyValues: [999, 1000],
            orphanedCount: 2,
            totalRelatedRecords: 10,
        );

        $this->assertSame('posts', $event->relationName);
        $this->assertSame('App\Entity\User', $event->parentEntityClass);
        $this->assertSame('App\Entity\Post', $event->relatedEntityClass);
        $this->assertSame('user_id', $event->foreignKeyField);
        $this->assertSame([999, 1000], $event->orphanedForeignKeyValues);
        $this->assertSame(2, $event->orphanedCount);
        $this->assertSame(10, $event->totalRelatedRecords);
    }

    public function testGetOrphanedPercentage(): void
    {
        $event = new RelationDataInconsistency(
            relationName: 'posts',
            parentEntityClass: 'App\Entity\User',
            relatedEntityClass: 'App\Entity\Post',
            foreignKeyField: 'user_id',
            orphanedForeignKeyValues: [999, 1000],
            orphanedCount: 2,
            totalRelatedRecords: 10,
        );

        $this->assertSame(20.0, $event->getOrphanedPercentage());
    }

    public function testGetOrphanedPercentageWithZeroTotal(): void
    {
        $event = new RelationDataInconsistency(
            relationName: 'posts',
            parentEntityClass: 'App\Entity\User',
            relatedEntityClass: 'App\Entity\Post',
            foreignKeyField: 'user_id',
            orphanedForeignKeyValues: [],
            orphanedCount: 0,
            totalRelatedRecords: 0,
        );

        $this->assertSame(0.0, $event->getOrphanedPercentage());
    }

    public function testIsSevereWithDefaultThreshold(): void
    {
        // 20% orphaned - should be severe (default threshold is 10%)
        $severeEvent = new RelationDataInconsistency(
            relationName: 'posts',
            parentEntityClass: 'App\Entity\User',
            relatedEntityClass: 'App\Entity\Post',
            foreignKeyField: 'user_id',
            orphanedForeignKeyValues: [999, 1000],
            orphanedCount: 2,
            totalRelatedRecords: 10,
        );

        $this->assertTrue($severeEvent->isSevere());

        // 5% orphaned - should not be severe
        $minorEvent = new RelationDataInconsistency(
            relationName: 'posts',
            parentEntityClass: 'App\Entity\User',
            relatedEntityClass: 'App\Entity\Post',
            foreignKeyField: 'user_id',
            orphanedForeignKeyValues: [999],
            orphanedCount: 1,
            totalRelatedRecords: 20,
        );

        $this->assertFalse($minorEvent->isSevere());
    }

    public function testIsSevereWithCustomThreshold(): void
    {
        $event = new RelationDataInconsistency(
            relationName: 'posts',
            parentEntityClass: 'App\Entity\User',
            relatedEntityClass: 'App\Entity\Post',
            foreignKeyField: 'user_id',
            orphanedForeignKeyValues: [999, 1000],
            orphanedCount: 2,
            totalRelatedRecords: 10,
        );

        // 20% orphaned
        $this->assertTrue($event->isSevere(threshold: 15.0));  // 20% > 15%
        $this->assertFalse($event->isSevere(threshold: 25.0)); // 20% < 25%
    }

    public function testEventPropertiesAreReadonly(): void
    {
        $event = new RelationDataInconsistency(
            relationName: 'posts',
            parentEntityClass: 'App\Entity\User',
            relatedEntityClass: 'App\Entity\Post',
            foreignKeyField: 'user_id',
            orphanedForeignKeyValues: [999],
            orphanedCount: 1,
            totalRelatedRecords: 10,
        );

        // Verify properties are readonly by checking they can't be modified
        $reflection = new \ReflectionClass($event);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
