<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit\Event;

use Switon\Orm\Entity;
use Switon\Orm\Event\LazyLoaded;
use Switon\Orm\Tests\TestCase;

class LazyLoadedTest extends TestCase
{
    public function testEventSetsExpectedProperties(): void
    {
        $entity = new Entity();
        $event = new LazyLoaded($entity, 'posts');

        $this->assertSame($entity, $event->entity);
        $this->assertSame('posts', $event->relationName);
    }
}
