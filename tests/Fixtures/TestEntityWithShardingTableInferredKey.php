<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;

#[Table('test_orders:order_id%8')]
class TestEntityWithShardingTableInferredKey extends Entity
{
    // Should infer 'order_id' from table name 'test_orders:order_id%8' -> base 'test_orders' -> last segment 'orders' -> singularized 'order' + '_id'
    public int $order_id;

    public string $order_no;
}
