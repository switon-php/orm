<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted before an entity is updated.
 *
 * Log category: <code>switon.orm.entity.updating</code>
 *
 * @see \Switon\Orm\Event\AbstractEntityEvent
 * @see \Switon\Orm\Event\EntityUpdated
 * @see \Switon\Orm\EntityManager::update() Typical emitter
 */
#[EventLevel(Severity::DEBUG)]
class EntityUpdating extends AbstractEntityEvent
{
}
