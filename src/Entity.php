<?php

declare(strict_types=1);

namespace Switon\Orm;

use ArrayAccess;
use JsonSerializable;
use Stringable;
use Switon\Binding\Attribute\ResolvedBy;
use Switon\Core\ArrayableInterface;
use Switon\Core\Json;
use Switon\Orm\Event\EntityEventInterface;

use function get_object_vars;

/**
 * Base persistent object for ORM entities.
 *
 * Road-signs:
 * - attributes define fields and relations
 * - array access supports relation loading
 * - toArray normalizes JSON-safe values
 * - onEvent is the lifecycle hook entry
 *
 * Guidance: Keep persisted and relation properties explicitly typed; custom objects should normalize via ArrayableInterface or JsonSerializable.
 *
 * @see \Switon\Orm\EntityMetadataInterface
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Orm\EntityFillerInterface
 * @see \Switon\Orm\Event\EntityEventInterface
 *
 * @implements ArrayAccess<string, mixed>
 *
 * @see \Switon\Orm\EntityResolver
 */
#[ResolvedBy(EntityResolver::class)]
class Entity implements ArrayAccess, JsonSerializable, Stringable, ArrayableInterface
{
    /**
     * Creates a new entity instance.
     *
     * Optionally accepts an array of data to initialize the entity properties. All array keys are assigned to
     * corresponding properties with the same name.
     *
     * @param array<string, mixed> $data Optional array of data to initialize entity properties
     *
     * @example
     * $user = new User(['name' => 'John', 'email' => 'john@example.com']);
     */
    public function __construct(array $data = [])
    {
        if ($data) {
            foreach ($data as $field => $value) {
                $this->{$field} = $value;
            }
        }
    }

    /**
     * Assigns values to entity properties from an array or another entity.
     *
     * Only assigns the specified fields. Useful for partial updates or copying specific fields from another entity.
     *
     * @param array<string, mixed>|Entity $data Source data (array or entity instance)
     * @param array<int, string> $fields Array of field names to assign
     *
     * @return static Returns $this for method chaining
     *
     * @example
     * // Assign from array (partial update)
     * $user->assign(['name' => 'Jane'], ['name']);
     * @example
     * // Assign from another entity
     * $user->assign($otherUser, ['name', 'email']);
     */
    public function assign(array|Entity $data, array $fields): static
    {
        if (is_object($data)) {
            foreach ($fields as $field) {
                if (isset($data->$field)) {
                    $this->$field = $data->$field;
                }
            }
        } else {
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $this->$field = $data[$field];
                }
            }
        }

        return $this;
    }

    /**
     * Converts the entity to an array representation.
     *
     * Returns all non-null properties as JSON-safe values.
     * Nested values are normalized by {@see Json::normalizeArray()}.
     * Value objects may implement {@see ArrayableInterface} or {@see JsonSerializable}
     * to control their JSON-safe representation.
     *
     * Null values are excluded from the result.
     *
     * @return array<string, mixed> Array representation of the entity
     *
     * @example
     * $array = $user->toArray();
     * // Returns: ['user_id' => 1, 'name' => 'John', 'email' => 'john@example.com']
     */
    public function toArray(): array
    {
        return Json::normalizeArray(get_object_vars($this));
    }

    /**
     * Handles entity lifecycle events.
     *
     * Override this method in your entity classes to handle lifecycle events. Called automatically by the framework
     * when entity events occur (creating, created, updating, updated, deleting, deleted, restoring, restored, etc.).
     *
     * @param EntityEventInterface $entityEvent The entity event instance
     *
     * @example
     * public function onEvent(EntityEventInterface $entityEvent): void
     * {
     *     if ($entityEvent instanceof EntityCreating) {
     *         $this->created_at = date('Y-m-d H:i:s');
     *     } elseif ($entityEvent instanceof EntityUpdating) {
     *         $this->updated_at = date('Y-m-d H:i:s');
     *     }
     * }
     */
    public function onEvent(EntityEventInterface $entityEvent): void
    {
    }

    /**
     * Checks if a property exists (ArrayAccess interface).
     *
     * @param mixed $offset Property name
     *
     * @return bool True if property exists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Gets a property value (ArrayAccess interface).
     *
     * @param mixed $offset Property name
     *
     * @return mixed Property value
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * Sets a property value (ArrayAccess interface).
     *
     * @param mixed $offset Property name
     * @param mixed $value Property value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Sets a property to null (ArrayAccess interface).
     *
     * @param mixed $offset Property name
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }

    /**
     * Returns entity data for JSON serialization (JsonSerializable interface).
     * Delegates to {@see self::toArray()} to ensure consistent behavior:
     * null values are excluded and nested entities are recursively converted.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Converts the entity to a JSON string representation (Stringable interface).
     *
     * @return string JSON string representation of the entity
     */
    public function __toString(): string
    {
        return Json::stringify($this->toArray());
    }
}
