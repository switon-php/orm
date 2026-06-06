# Switon ORM Package

[![ORM CI](https://img.shields.io/github/actions/workflow/status/switon-php/orm/ci.yml?branch=main&label=ORM%20CI)](https://github.com/switon-php/orm/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's entity mapping layer for attribute-driven models, repository queries, relations, lifecycle events,
sharding-aware writes, and pagination boundaries.

## Highlights

- **Relationship mapping:** `Entity` classes declare linked records through attributes.
- **Relationship loading:** `RelationManager` handles eager and lazy loading.
- **Attribute-based mapping:** `Entity` classes declare tables, columns, IDs, and constraints through attributes.
- **Runtime-only properties:** `#[Transient]` keeps temporary fields out of persistence.
- **Repository queries:** `RepositoryInterface` and `Repository` cover filters, lookup, relation-backed reads, and
  pagination.
- **Safe write flow:** create and update paths include fill, validation, events, and `#[Transactional]` support.
- **Shard-safe writes:** writes resolve a single shard before they run.

## Installation

```bash
composer require switon/orm
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Orm\Attribute\{Id, Table, Transient};
use Switon\Orm\Entity;
use Switon\Orm\Page;
use Switon\Orm\Repository;

#[Table('users')]
class User extends Entity
{
    #[Id]
    public ?int $id = null;

    public string $name;

    public string $email;

    #[Transient]
    public ?string $displayName = null;
}

final class UserRepository extends Repository
{
}

final class UserController
{
    #[Autowired] protected UserRepository $userRepository;

    public function indexAction(Page $page): \Switon\Query\Paginator
    {
        return $this->userRepository->paginate($page);
    }
}
```

Docs: https://docs.switon.dev/latest/orm

## License

MIT.
