# ORM Package Tests

Test suite for the Switon ORM package, organized by test type and speed.

## Test Structure

```
tests/
├── Unit/                    # Fast unit tests (mocked dependencies)
│   └── Relation/            # Relation-specific tests
├── Integration/             # Database integration tests (SQLite :memory:)
├── Fixtures/                # Test fixtures and mock classes
├── DatabaseTestCase.php     # Base class for integration tests
└── TestCase.php             # Base class for unit tests
```

## Running Tests

Run from the package root:

```bash
# Run all tests
vendor/bin/phpunit --configuration tests/phpunit.xml.dist

# Run specific test suite
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=unit
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=integration
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=all

# Run specific test method
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --filter testMethodName

# Run with PHPUnit options
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --coverage-html tests/coverage
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --stop-on-failure
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --verbose
```

**Note**: Install dev dependencies first so `vendor/bin/phpunit` is available.

## Test Quality Standards

### Code Quality

- **Strict Types**: All test code uses `declare(strict_types=1)`
- **No Warnings**: Clean execution with zero warnings
- **Descriptive Names**: Test methods clearly describe what they test
- **Proper Assertions**: Use specific assertions (`assertDatabaseHas`, etc.)

### Test Structure

- **Arrange-Act-Assert**: Clear test phases
- **One Assertion Per Test**: Focus on single behavior
- **Descriptive Messages**: Helpful failure messages
- **Proper Cleanup**: Tests clean up after themselves

## Troubleshooting

For debugging failing tests:

```bash
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --filter testMethodName
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --verbose
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testdox
```

## Contributing

When adding new tests:

- Use `Unit/` for isolated logic tests (with mocked dependencies)
- Use `Integration/` for tests requiring database (uses SQLite :memory:)
- Follow naming convention: `[Component]Test.php`
- Use appropriate base class: `TestCase` or `DatabaseTestCase`

---

*See main README.md for package overview and documentation.*
