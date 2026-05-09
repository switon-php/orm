<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

class TestJsonSerializableValue implements \JsonSerializable
{
    public function __construct(protected array $data)
    {
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
