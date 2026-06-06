<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;

class PassthroughEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
