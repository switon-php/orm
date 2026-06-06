<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

enum TestStatus: int
{
    case Active = 1;
    case Inactive = 0;
}
