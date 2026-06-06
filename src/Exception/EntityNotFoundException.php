<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use JetBrains\PhpStorm\ArrayShape;
use Switon\Core\Json;
use Switon\Core\NotFoundInterface;
use Switon\Orm\Exception;

/**
 * Raised when no entity matches the requested identifier or filters.
 *
 * Guidance: Use this for single-resource misses and return empty collections for list queries.
 *
 * @see \Switon\Core\NotFoundInterface
 * @see \Switon\Orm\RepositoryInterface::get()
 * @see \Switon\Orm\AbstractRepository
 */
class EntityNotFoundException extends Exception implements NotFoundInterface
{
    public string $entityClass;
    public mixed $filters;

    /**
     * Returns HTTP 404 status code for entity not found.
     *
     * This exception represents a user-level error (requested resource doesn't exist),
     * not a developer error, so it has an HTTP status code.
     */
    public function getStatusCode(): int
    {
        return 404;
    }

    #[ArrayShape(['code' => 'int', 'msg' => 'string'])]
    public function getJson(): array
    {
        return ['code' => 404, 'msg' => "Record of '$this->entityClass' Model is not exists"];
    }

    public static function raiseForEntityNotFound(string $entityClass, mixed $filters): never
    {
        $exception = new static('No record found for entity type "{entityClass}" matching filters: {filters}', ['entityClass' => $entityClass, 'filters' => Json::stringify($filters)]);
        $exception->entityClass = $entityClass;
        $exception->filters = $filters;
        throw $exception;
    }
}
