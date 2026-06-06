<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Orm\Entity;

/**
 * Dispatched when an update is skipped because no persistent fields changed.
 *
 * Log category: <code>switon.orm.entities.unchanged</code>
 *
 * @see \Switon\Orm\EntityManager::update() Typical emitter
 */
#[EventLevel(Severity::DEBUG)]
class EntityUnchanged implements JsonSerializable
{
    public function __construct(
        public Entity $entity,
        public Entity $original,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'entity' => $this->entity::class,
        ];
    }
}
