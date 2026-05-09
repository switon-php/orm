<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Core\Exception as CoreException;
use Switon\Orm\Exception as OrmException;
use Switon\Orm\Exception\EntityNotFoundException;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;

/**
 * Contract tests for ORM exception hierarchy (stable for catch-by-namespace).
 */
class OrmExceptionTest extends TestCase
{
    public function testBaseOrmExceptionExtendsCoreException(): void
    {
        try {
            OrmException::raise('orm');
        } catch (OrmException $e) {
            $this->assertInstanceOf(CoreException::class, $e);
            $this->assertInstanceOf(OrmException::class, $e);
        }
    }

    public function testConcreteOrmExceptionsExtendOrmException(): void
    {
        try {
            EntityNotFoundException::raiseForEntityNotFound(TestEntity::class, ['id' => 1]);
        } catch (EntityNotFoundException $e) {
            $this->assertInstanceOf(OrmException::class, $e);
            $this->assertInstanceOf(CoreException::class, $e);
        }
    }
}
