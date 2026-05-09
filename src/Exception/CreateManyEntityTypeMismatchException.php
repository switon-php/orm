<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Use when createMany() mixes different Entity classes in one call.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\EntityManagerInterface::createMany()
 * @see \Switon\Orm\EntityManager::createMany()
 */
class CreateManyEntityTypeMismatchException extends Exception
{
}
