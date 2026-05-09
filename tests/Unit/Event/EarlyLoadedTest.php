<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Event;

use Switon\Orm\Entity;
use Switon\Orm\Event\EarlyLoaded;
use Switon\Orm\Tests\TestCase;

class EarlyLoadedTest extends TestCase
{
    public function testEventSetsExpectedProperties(): void
    {
        $entities = [new Entity(), new Entity()];
        $event = new EarlyLoaded(Entity::class, 'posts', $entities);

        $this->assertSame(Entity::class, $event->entityClass);
        $this->assertSame('posts', $event->relationName);
        $this->assertSame($entities, $event->entities);
    }
}
