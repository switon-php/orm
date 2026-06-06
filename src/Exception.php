<?php

declare(strict_types=1);

namespace Switon\Orm;

/**
 * Base exception for ORM errors.
 *
 * @see \Switon\Orm\Exception Concrete exception types in this component
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\EntityManager
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\AbstractRepository
 * @see \Switon\Orm\RelationManagerInterface
 */
class Exception extends \Switon\Core\Exception
{
}
