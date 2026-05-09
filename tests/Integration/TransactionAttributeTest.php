<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use Switon\Db\Exception\CrossDatabaseTransactionException;
use Switon\Db\TransactionManagerInterface;
use Switon\Invoking\InvokerInterface;
use Switon\Orm\Attribute\Transactional;
use Switon\Orm\Tests\TestCase;

/**
 * Integration tests for Transaction attribute.
 */
class TransactionAttributeTest extends TestCase
{
    protected TransactionManagerInterface $transactionManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionManager = $this->container->get(TransactionManagerInterface::class);
    }

    public function testTransactionAttributeStartsTransaction(): void
    {
        $service = new class {
            #[Transactional]
            public function testMethod(): string
            {
                return 'result';
            }
        };

        $caller = $this->container->get(InvokerInterface::class);
        $result = $caller->invoke([$service, 'testMethod']);

        $this->assertSame('result', $result);
        $this->assertSame([], $this->transactionManager->getActiveConnectionNames());
    }

    public function testTransactionAttributeRollsBackOnException(): void
    {
        $service = new class {
            #[Transactional]
            public function testMethod(): void
            {
                throw new \RuntimeException('Test exception');
            }
        };

        $caller = $this->container->get(InvokerInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $caller->invoke([$service, 'testMethod']);
        } finally {
            $this->assertSame([], $this->transactionManager->getActiveConnectionNames());
        }
    }

    public function testTransactionAttributeWithMultipleMethods(): void
    {
        $service = new class {
            #[Transactional]
            public function method1(): string
            {
                return 'result1';
            }

            #[Transactional]
            public function method2(): string
            {
                return 'result2';
            }
        };

        $caller = $this->container->get(InvokerInterface::class);

        $result1 = $caller->invoke([$service, 'method1']);
        $this->assertSame('result1', $result1);
        $this->assertSame([], $this->transactionManager->getActiveConnectionNames());

        $result2 = $caller->invoke([$service, 'method2']);
        $this->assertSame('result2', $result2);
        $this->assertSame([], $this->transactionManager->getActiveConnectionNames());
    }

    public function testTransactionAttributePreservesReturnValue(): void
    {
        $service = new class {
            #[Transactional]
            public function testMethod(): array
            {
                return ['key' => 'value', 'number' => 42];
            }
        };

        $caller = $this->container->get(InvokerInterface::class);
        $result = $caller->invoke([$service, 'testMethod']);

        $this->assertSame(['key' => 'value', 'number' => 42], $result);
    }

    public function testTransactionAttributeWithNestedTransactionThrowsException(): void
    {
        $service = new class($this->transactionManager) {
            protected TransactionManagerInterface $transactionManager;

            public function __construct(TransactionManagerInterface $transactionManager)
            {
                $this->transactionManager = $transactionManager;
            }

            #[Transactional]
            public function outerMethod(): void
            {
                $this->transactionManager->begin(null);
            }
        };

        $caller = $this->container->get(InvokerInterface::class);

        $this->expectException(CrossDatabaseTransactionException::class);
        $this->expectExceptionMessageMatches('/Transaction already active/');

        try {
            $caller->invoke([$service, 'outerMethod']);
        } finally {
            $this->assertSame([], $this->transactionManager->getActiveConnectionNames());
        }
    }

}
