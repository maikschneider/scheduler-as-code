# Contributing

Thanks for taking the time.

## Getting set up

```bash
ddev start
ddev composer install
ddev init-typo3
```

## Before opening a pull request

```bash
ddev composer sca          # php-cs-fixer, phpstan, editorconfig, xliff
ddev composer test:unit
ddev composer test:functional
```

## Conventions

- TYPO3 Coding Guidelines; `php-cs-fixer` is the arbiter.
- PHPStan level 7. Do not grow `phpstan-baseline.neon` — fix the finding instead.
- New user-facing strings go into both `locallang*.xlf` and their `de.` counterparts.
- Every behavioural change needs a test. File parsing is covered by unit tests; anything
  that touches `tx_scheduler_task` by functional tests on both TYPO3 13.4 and 14.3.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).
