<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Use when createMany() receives a value that is not an Entity instance.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\EntityManagerInterface::createMany()
 * @see \Switon\Orm\EntityManager::createMany()
 */
class CreateManyInvalidEntityException extends Exception
{
}
