<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Db\Fragment\Increment;
use Switon\Orm\Tests\TestCase;

class IncrementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

    }

    public function testSetFieldReturnsSelfForMethodChaining(): void
    {
        $increment = new Increment(1);

        $result = $increment->setField('view_count');

        $this->assertSame($increment, $result);
    }

    public function testGetExpressionReturnsSqlExpressionWithOperator(): void
    {
        $increment = new Increment(1, '+');
        $increment->setField('view_count');

        $expression = $increment->getExpression();

        $this->assertSame('view_count = view_count + :view_count', $expression);
    }

    public function testGetExpressionReturnsSqlExpressionWithMinusOperator(): void
    {
        $increment = new Increment(5, '-');
        $increment->setField('stock');

        $expression = $increment->getExpression();

        $this->assertSame('stock = stock - :stock', $expression);
    }

    public function testGetBindReturnsParameterBindings(): void
    {
        $increment = new Increment(10, '+');
        $increment->setField('counter');

        $bind = $increment->getBind();

        $this->assertIsArray($bind);
        $this->assertArrayHasKey('counter', $bind);
        $this->assertSame(10, $bind['counter']);
    }

    public function testGetSqlReturnsSameAsGetExpression(): void
    {
        $increment = new Increment(1, '+');
        $increment->setField('count');

        $expression = $increment->getExpression();
        $expressionResult = $increment->getExpression();

        $this->assertSame($expression, $expressionResult);
    }
}
