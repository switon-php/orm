# Test Fixtures Documentation

This directory contains PSR-4 test fixture declarations used across ORM package tests.

## File Organization

- Top-level `*.php`: one class, trait, or enum per file under `Switon\\Orm\\Tests\\Fixtures`
- `Entity/InferredSample.php`: entity fixture for repository inference tests
- `Repository/InferredSampleRepository.php`: repository fixture for repository inference tests
- `Pivot/UserRole.php`: pivot fixture for relation tests

## Fixture Rules

- Keep fixtures PSR-4 friendly and single-declaration per file.
- Preserve subdirectories when the fixture namespace already uses them.
