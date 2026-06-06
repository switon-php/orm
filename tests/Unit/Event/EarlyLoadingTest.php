<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Event;

use Switon\Orm\Entity;
use Switon\Orm\Event\EarlyLoading;
use Switon\Orm\Tests\TestCase;

class EarlyLoadingTest extends TestCase
{
    public function testEventSetsExpectedProperties(): void
    {
        $entities = [new Entity(), new Entity()];
        $event = new EarlyLoading(Entity::class, 'posts', $entities);

        $this->assertSame(Entity::class, $event->entityClass);
        $this->assertSame('posts', $event->relationName);
        $this->assertSame($entities, $event->entities);
    }
}
