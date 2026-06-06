<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestOrderWithMappedPrimaryKey extends Entity
{
    #[Id]
    #[Column('order_id')]
    public int $id;

    public string $order_no;

    public int $status;
}
