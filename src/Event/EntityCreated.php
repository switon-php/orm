<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after an entity is created.
 *
 * Log category: <code>switon.orm.entity.created</code>
 *
 * @see \Switon\Orm\Event\AbstractEntityEvent
 * @see \Switon\Orm\Event\EntityCreating
 * @see \Switon\Orm\EntityManager Typical emitter
 */
#[EventLevel(Severity::DEBUG)]
class EntityCreated extends AbstractEntityEvent
{
}
