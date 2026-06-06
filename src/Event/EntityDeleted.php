<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after an entity is deleted.
 *
 * Log category: <code>switon.orm.entity.deleted</code>
 *
 * @see \Switon\Orm\Event\AbstractEntityEvent
 * @see \Switon\Orm\Event\EntityDeleting
 * @see \Switon\Orm\EntityManager Typical emitter
 */
#[EventLevel(Severity::DEBUG)]
class EntityDeleted extends AbstractEntityEvent
{
}
