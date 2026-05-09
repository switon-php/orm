<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use ReflectionMethod;
use Switon\Core\Attribute\Autowired;
use Switon\Invocation\Attribute\InterceptorInterface;
use Switon\Db\TransactionManagerInterface;
use Throwable;

/**
 * Method interceptor that wraps execution in a database transaction.
 *
 * Road-signs:
 * - preHandle begins transaction
 * - postHandle commits
 * - exceptionHandle rolls back
 * - optional fixed connection
 *
 * Guidance: Let exceptions bubble and keep the transactional work on one logical connection or shard.
 *
 * @see \Switon\Db\TransactionManagerInterface
 * @see \Switon\Invocation\Attribute\InterceptorInterface
 * @see \Switon\Orm\ShardingInterface::getUniqueShard()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Transactional implements InterceptorInterface
{
    #[Autowired] protected TransactionManagerInterface $transactionManager;

    /**
     * Connection name for the transaction.
     *
     * If null, the connection is determined by the first ORM operation.
     *
     * @var string|null
     */
    protected ?string $connectionName;

    /**
     * Creates a new Transaction attribute instance.
     *
     * @param string|null $connectionName Optional connection name. If null, connection is determined by first ORM operation.
     */
    public function __construct(?string $connectionName = null)
    {
        $this->connectionName = $connectionName;
    }

    /**
     * {@inheritDoc}
     */
    public function preHandle(ReflectionMethod $method): bool
    {
        $this->transactionManager->begin($this->connectionName);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postHandle(ReflectionMethod $method, mixed &$return): void
    {
        $this->transactionManager->commitAll();
    }

    /**
     * {@inheritDoc}
     */
    public function onException(ReflectionMethod $method, Throwable $exception): void
    {
        $this->transactionManager->rollbackAll();
    }
}
