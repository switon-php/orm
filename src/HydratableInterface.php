<?php

declare(strict_types=1);

namespace Switon\Orm;

/**
 * Contract for value objects that participate in ORM field hydration.
 *
 * Guidance: Implement this when a field needs entity and field context during cast-in and custom dehydrate logic during cast-out.
 *
 * @see \Switon\Orm\EntityHydrator
 * @see \Switon\Core\ArrayableInterface
 * @see \JsonSerializable
 */
interface HydratableInterface
{
    /**
     * Create a value object from raw field data.
     *
     * The parent entity and field name are provided so the implementation may
     * inspect sibling fields or metadata when building the value object.
     *
     * @param Entity $entity Parent entity being hydrated
     * @param string $field Field name being hydrated
     * @param mixed $value Raw field value
     *
     * @return static Hydrated value object
     */
    public static function hydrate(Entity $entity, string $field, mixed $value): static;

    /**
     * Convert the value object back to storage-friendly data.
     *
     * @return mixed Persistence-friendly value
     */
    public function dehydrate(): mixed;
}
