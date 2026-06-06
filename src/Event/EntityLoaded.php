<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Orm\Entity;

/**
 * Dispatched after an entity is hydrated from query rows.
 *
 * Log category: <code>switon.orm.entity.loaded</code>
 *
 * @see \Switon\Orm\AbstractRepository::hydrateEntity() Typical emitter
 * @see \Switon\Orm\Event\EntitiesLoaded
 */
#[EventLevel(Severity::DEBUG)]
class EntityLoaded implements JsonSerializable
{
    public function __construct(public Entity $entity)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'entity' => $this->entity::class,
        ];
    }
}
