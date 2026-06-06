<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Stringable;

class TestStringableValue implements Stringable
{
    public function __construct(protected string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
