<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted before an entity is created.
 *
 * Log category: <code>switon.orm.entity.creating</code>
 *
 * @see \Switon\Orm\Event\AbstractEntityEvent
 * @see \Switon\Orm\Event\EntityCreated
 * @see \Switon\Orm\EntityManager Typical emitter
 */
#[EventLevel(Severity::DEBUG)]
class EntityCreating extends AbstractEntityEvent
{
}
