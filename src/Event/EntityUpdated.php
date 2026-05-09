<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after an entity is updated.
 *
 * Log category: <code>switon.orm.entity.updated</code>
 *
 * @see \Switon\Orm\Event\AbstractEntityEvent
 * @see \Switon\Orm\Event\EntityUpdating
 * @see \Switon\Orm\EntityManager::update() Typical emitter
 */
#[EventLevel(Severity::DEBUG)]
class EntityUpdated extends AbstractEntityEvent
{
}
