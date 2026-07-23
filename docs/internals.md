Internals
=========

Notes for contributors working on Voyti itself — not needed to simply use the package (see
[yii.rocks/voyti](https://www.yii.rocks/voyti/) for that).

## Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). Both `pdo_sqlite` and `intl` must be enabled in the PHP
CLI used to run it. To run the full suite:

```bash
composer phpunit
```

To run a single test or filter:

```bash
vendor/bin/phpunit --filter testMethodName
vendor/bin/phpunit tests/Service/User/RegisterServiceTest.php
```

Tests do not boot the DI container — `tests/Support/ControllerHarness.php` builds the object graph manually with
in-memory fakes. `tests/ContainerWiringTest.php` is the one exception, booting a real container purely to assert
`config/di.php` resolves.

## Coverage

Coverage is a separate concern from mutation score and isn't produced by `composer infection` alone — check it
explicitly:

```bash
vendor/bin/phpunit --coverage-text --colors=never --coverage-filter=src
```

Confirm the `Summary:` block shows `Methods: 100.00%` and `Lines: 100.00%`. Infection only generates mutants for
lines PHPUnit's coverage run actually executes, so a method with zero coverage produces zero mutants and passes
mutation testing silently.

## Mutation testing

The package is checked with the [Infection](https://infection.github.io/) mutation testing framework, enforcing
`min-msi=100` and `min-covered-msi=100`:

```bash
composer infection
```

A full run mutates all of `src` and pays for the whole PHPUnit suite as its coverage-collection pass, so it takes
minutes even on a fast machine. For fast local feedback while iterating on a change, scope it to the diff instead:

```bash
vendor/bin/infection --git-diff-lines --git-diff-base=main --map-source-class-to-test --threads=max
```

This narrows both the mutated lines and the coverage-collection pass down to what's actually changed and the tests
covering it — a single-line change measured this way runs in seconds instead of a minute-plus. It's an intermediate
check only, not a substitute for the full `composer infection` run before committing: it can't catch mutants on
lines outside the diff, and unlike the full run it isn't enforced by any script or CI gate.

## Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/) at `errorLevel 1`, the strictest setting:

```bash
composer psalm
```

## Code style

[PHP CS Fixer](https://cs.symfony.com/) enforces `@PER-CS3.0` plus alphabetically ordered class elements and
imports:

```bash
composer php-cs-fixer
```
