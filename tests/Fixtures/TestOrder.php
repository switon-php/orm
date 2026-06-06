<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestOrder extends Entity
{
    #[Id]
    public int $order_id;

    public string $order_no;
}
