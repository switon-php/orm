<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestProduct extends Entity
{
    #[Id]
    public int $product_id;

    #[Column('product_name')]
    public string $name;

    #[Column('product_price')]
    public float $price;

    public int $stock;
}
