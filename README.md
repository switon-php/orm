# Switon ORM Package

Data mapping, repositories, and persistence for Switon Framework.

## Installation

```bash
composer require switon/orm
```

**Requirements:** PHP 8.3+

## Quick Start

```php
use Switon\Orm\Entity;
use Switon\Orm\Attribute\{Table, Id, Column};

#[Table('users')]
class User extends Entity
{
    #[Id]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column]
    public string $email;
}
```

Docs: https://docs.switon.dev/latest/orm

## License

MIT.
