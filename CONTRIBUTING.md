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

## Code coverage

```bash
ddev composer test:coverage   # report in .Build/coverage/html
```

ddev ships PCOV switched off; the script enables it for the run. The report covers the TYPO3
version currently installed, so the storage of the other major version shows as uncovered.
CI collects coverage on TYPO3 13.4 and 14.3 and merges both into the `coverage-report`
artifact, with a summary on the workflow run.

## Conventions

- TYPO3 Coding Guidelines; `php-cs-fixer` is the arbiter.
- PHPStan level 7. Do not grow `phpstan-baseline.neon` — fix the finding instead.
- New user-facing strings go into both `locallang*.xlf` and their `de.` counterparts.
- Every behavioural change needs a test. File parsing is covered by unit tests; anything
  that touches `tx_scheduler_task` by functional tests on both TYPO3 13.4 and 14.3.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).
