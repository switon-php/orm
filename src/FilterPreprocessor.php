<?php

declare(strict_types=1);

namespace Switon\Orm;

use function array_key_exists;
use function count;
use function date;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function str_contains;
use function str_ends_with;
use function strtotime;
use function substr;

/**
 * Default filter-shape normalizer before repository filters reach Query.
 *
 * Road-signs:
 * - detects date-range filter syntax
 * - rewrites to query operators
 * - converts date boundaries by entity format
 * - returns normalized filters
 *
 * Guidance: Keep this layer for filter normalization only; business rules belong elsewhere.
 *
 * @see \Switon\Orm\FilterPreprocessorInterface
 * @see \Switon\Orm\AbstractRepository
 * @see \Switon\Query\AbstractConditionBuilder::where()
 */
class FilterPreprocessor implements FilterPreprocessorInterface
{
    public function __construct(
        protected EntityMetadataInterface $entityMetadata,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param class-string<Entity> $entityClass
     * @param array<int|string, mixed> $filters
     *
     * @return array<int|string, mixed>
     */
    public function preprocess(array $filters, string $entityClass): array
    {
        foreach ($filters as $filter => $value) {
            if (is_string($filter) && str_ends_with($filter, '@=') && is_array($value) && count($value) === 2) {
                $field = substr($filter, 0, -2);
                if (!array_key_exists(0, $value) || !array_key_exists(1, $value)) {
                    continue;
                }

                $hasMin = isset($value[0]) && $value[0] !== '';
                $hasMax = isset($value[1]) && $value[1] !== '';

                unset($filters[$filter]);

                if ($hasMin || $hasMax) {
                    [$min, $max] = $this->convertDateRange(
                        $entityClass,
                        $field,
                        $hasMin ? $value[0] : null,
                        $hasMax ? $value[1] : null
                    );

                    if ($hasMin && $hasMax) {
                        $filters[$field . '~='] = [$min, $max];
                    } elseif ($hasMin) {
                        $filters[$field . '>='] = $min;
                    } else {
                        $filters[$field . '<='] = $max;
                    }
                }
            }
        }

        return $filters;
    }

    /**
     * @param class-string<Entity> $entityClass
     *
     * @return array{0: mixed, 1: mixed}
     */
    protected function convertDateRange(string $entityClass, string $_field, mixed $min, mixed $max): array
    {
        // Treat "0" as a valid value (PHP truthy checks would skip it).
        if ($min !== '' && is_string($min) && !str_contains($min, ':')) {
            if (is_numeric($min)) {
                $min = (int)$min;
            } else {
                $timestamp = strtotime($min . ' 00:00:00');
                $min = $timestamp !== false ? $timestamp : $min;
            }
        }
        if ($max !== '' && is_string($max) && !str_contains($max, ':')) {
            if (is_numeric($max)) {
                $max = (int)$max;
            } else {
                $timestamp = strtotime($max . ' 23:59:59');
                $max = $timestamp !== false ? $timestamp : $max;
            }
        }

        $format = $this->entityMetadata->getDateFormat($entityClass, $_field);

        if ($format && $format !== 'U') {
            if (is_int($min)) {
                $min = date($format, $min);
            }
            if (is_int($max)) {
                $max = date($format, $max);
            }
        } elseif (!$format) {
            if ($min && is_string($min)) {
                $timestamp = strtotime($min);
                $min = $timestamp !== false ? $timestamp : $min;
            }
            if ($max && is_string($max)) {
                $timestamp = strtotime($max);
                $max = $timestamp !== false ? $timestamp : $max;
            }
        }

        return [$min !== null && $min !== '' ? $min : null, $max !== null && $max !== '' ? $max : null];
    }
}
