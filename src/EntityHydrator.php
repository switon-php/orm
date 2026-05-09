<?php

declare(strict_types=1);

namespace Switon\Orm;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use JsonSerializable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use function array_is_list;
use function array_key_exists;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function property_exists;

/**
 * Default metadata-driven hydrator for entity field conversion.
 *
 * Road-signs:
 * - casts scalars and enums
 * - parses and formats date values
 * - encodes and decodes JSON fields
 * - delegates custom value objects to HydratableInterface
 *
 * Guidance: Prefer metadata-backed fields; override protected hooks only for stable conversion rules.
 *
 * @see \Switon\Orm\EntityHydratorInterface
 * @see \Switon\Orm\HydratableInterface
 * @see \Switon\Orm\EntityMetadataInterface
 */
class EntityHydrator implements EntityHydratorInterface
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    /**
     * Hydrates a new entity instance from array data.
     *
     * @inheritDoc
     */
    public function hydrate(string $entityClass, array $data, ?array $fields = null): Entity
    {
        $entity = new $entityClass();

        return $this->hydrateInto($entity, $data, $fields);
    }

    /**
     * Hydrates an existing entity instance from array data.
     *
     * @inheritDoc
     */
    public function hydrateInto(Entity $entity, array $data, ?array $fields = null): Entity
    {
        $useDefaultFields = $fields === null;
        $fields ??= $this->entityMetadata->getFields($entity::class);
        $fields = $this->normalizeFields($fields);

        if ($useDefaultFields && $fields === []) {
            $fields = array_keys($data);
        }

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (!property_exists($entity, $field)) {
                continue;
            }

            $entity->$field = $this->hydrateValue($entity, $field, $data[$field]);
        }

        return $entity;
    }

    /**
     * Converts an entity into a persistence payload.
     *
     * @inheritDoc
     */
    public function dehydrate(Entity $entity, ?array $fields = null): array
    {
        $useDefaultFields = $fields === null;
        $fields ??= $this->entityMetadata->getFields($entity::class);
        $fields = $this->normalizeFields($fields);

        if ($useDefaultFields && $fields === []) {
            $fields = array_keys(get_object_vars($entity));
        }

        $values = get_object_vars($entity);

        $data = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $data[$field] = $this->dehydrateValue($entity, $field, $values[$field]);
        }

        return $data;
    }

    /**
     * Normalizes field lists to a simple ordered list of field names.
     *
     * @param array<int|string, mixed> $fields Field list or keyed map
     * @return array<int, string>
     */
    protected function normalizeFields(array $fields): array
    {
        if (array_is_list($fields)) {
            return $fields;
        }

        return array_keys($fields);
    }

    /**
     * Hydrates a single field value.
     *
     * Subclasses may override this to add custom cast rules.
     *
     * @param Entity $entity
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    protected function hydrateValue(Entity $entity, string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $entityClass = $entity::class;
        $type = $this->entityMetadata->getFieldType($entityClass, $field);

        return match ($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            'string' => is_string($value) ? $value : (string)$value,
            'bool' => is_bool($value)
                ? $value
                : !in_array($value, ['', 0, '0', 'false', 'off', 'no'], true),
            'array' => $this->hydrateArrayValue($entity, $field, $value),
            default => $this->hydrateObjectValue($entity, $field, $type, $value),
        };
    }

    /**
     * Dehydrates a single field value.
     *
     * Subclasses may override this to add custom cast rules.
     *
     * @param Entity $entity
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    protected function dehydrateValue(Entity $entity, string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof HydratableInterface) {
            return $value->dehydrate();
        }

        $entityClass = $entity::class;

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->formatDateValue($entityClass, $field, $value);
        }

        if ($value instanceof JsonSerializable) {
            return $this->encodeJson($entityClass, $field, $value->jsonSerialize());
        }

        if (is_array($value)) {
            return $this->encodeJson($entityClass, $field, $value);
        }

        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        return $value;
    }

    /**
     * Hydrates array-typed fields.
     *
     * @param Entity $entity
     * @param string $field
     * @param mixed $value
     * @return array<mixed>
     */
    protected function hydrateArrayValue(Entity $entity, string $field, mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                RuntimeException::raise(
                    'Failed to decode JSON for {entity}.{field}: {error}',
                    [
                        'entity' => $entity::class,
                        'field' => $field,
                        'error' => $e->getMessage(),
                    ]
                );
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        RuntimeException::raise(
            'Failed to hydrate array for {entity}.{field}: expected JSON array payload.',
            [
                'entity' => $entity::class,
                'field' => $field,
            ]
        );
    }

    /**
     * Hydrates an object-typed field.
     *
     * @param Entity $entity
     * @param string $field
     * @param string $type
     * @param mixed $value
     * @return mixed
     */
    protected function hydrateObjectValue(Entity $entity, string $field, string $type, mixed $value): mixed
    {
        if ($type === 'mixed') {
            return $value;
        }

        if ($type !== HydratableInterface::class && is_a($type, HydratableInterface::class, true)) {
            if ($value instanceof $type) {
                return $value;
            }

            return $type::hydrate($entity, $field, $value);
        }

        if (enum_exists($type)) {
            return $this->hydrateEnumValue($type, $entity, $field, $value);
        }

        if (is_a($type, DateTimeInterface::class, true)) {
            return $this->hydrateDateValue($type, $entity, $field, $value);
        }

        return $value;
    }

    /**
     * Hydrates an enum value.
     *
     * @param class-string $type
     * @param Entity $entity
     * @param string $field
     * @param mixed $value
     * @return \UnitEnum
     */
    protected function hydrateEnumValue(string $type, Entity $entity, string $field, mixed $value): \UnitEnum
    {
        if ($value instanceof $type) {
            return $value;
        }

        if (is_a($type, BackedEnum::class, true)) {
            try {
                return $type::from($value);
            } catch (\ValueError $e) {
                RuntimeException::raise(
                    'Failed to hydrate enum for {entity}.{field}: case "{value}" does not exist.',
                    [
                        'entity' => $entity::class,
                        'field' => $field,
                        'value' => is_scalar($value) ? (string)$value : get_debug_type($value),
                    ]
                );
            }
        }

        foreach ($type::cases() as $case) {
            if ($case->name === (string)$value) {
                return $case;
            }
        }

        RuntimeException::raise(
            'Failed to hydrate enum for {entity}.{field}: case "{value}" does not exist.',
            [
                'entity' => $entity::class,
                'field' => $field,
                'value' => is_scalar($value) ? (string)$value : get_debug_type($value),
            ]
        );
    }

    /**
     * Hydrates a date/time value.
     *
     * @param class-string<DateTimeInterface> $type
     * @param Entity $entity
     * @param string $field
     * @param mixed $value
     * @return DateTimeInterface
     */
    protected function hydrateDateValue(string $type, Entity $entity, string $field, mixed $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        $entityClass = $entity::class;
        $format = $this->entityMetadata->getDateFormat($entityClass, $field);
        $dateType = $type === DateTimeInterface::class ? DateTimeImmutable::class : $type;
        $stringValue = is_int($value) ? (string)$value : (string)$value;
        $date = $dateType::createFromFormat($format, $stringValue);

        if ($date instanceof DateTimeInterface) {
            return $date;
        }

        RuntimeException::raise(
            'Failed to hydrate date for {entity}.{field} using format {format}.',
            [
                'entity' => $entityClass,
                'field' => $field,
                'format' => $format,
            ]
        );
    }

    /**
     * Formats a date/time value for storage.
     *
     * @param class-string<Entity> $entityClass
     * @param string $field
     * @param DateTimeInterface $value
     * @return int|string
     */
    protected function formatDateValue(string $entityClass, string $field, DateTimeInterface $value): int|string
    {
        $format = $this->entityMetadata->getDateFormat($entityClass, $field);

        if ($format === 'U') {
            return (int)$value->format('U');
        }

        return $value->format($format);
    }

    /**
     * Encodes a JSON-compatible value.
     *
     * @param class-string<Entity> $entityClass
     * @param string $field
     * @param mixed $value
     * @return string
     */
    protected function encodeJson(string $entityClass, string $field, mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            RuntimeException::raise(
                'Failed to encode JSON for {entity}.{field}: {error}',
                [
                    'entity' => $entityClass,
                    'field' => $field,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
