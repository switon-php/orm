<?php

declare(strict_types=1);

namespace Switon\Orm;

/**
 * Normalizes repository filters before {@see \Switon\Query\QueryInterface::where()}; bind another implementation to customize.
 *
 * Road-signs:
 * - preprocess filter array
 * - map ui filters to query ops
 * - entity class aware conversion
 * - consumed by repository where
 * - replace via interface binding
 *
 * Guidance: Keep preprocessing deterministic and side-effect free so repository filtering remains predictable.
 *
 * @see \Switon\Orm\FilterPreprocessor
 * @see \Switon\Orm\AbstractRepository::where()
 * @see \Switon\Http\RequestInterface::filters()
 * @see \Switon\Query\QueryInterface::where()
 */
interface FilterPreprocessorInterface
{
    /**
     * @param array<string, mixed> $filters
     * @param class-string<Entity> $entityClass
     *
     * @return array<string, mixed>
     */
    public function preprocess(array $filters, string $entityClass): array;
}
