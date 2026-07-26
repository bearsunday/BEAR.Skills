# Tests, smoke, and quality gates

- Prefer hermetic fake tests for the default suite. The default `vendor/bin/phpunit` run must not require a database.
- Add SQL smoke tests for placeholder/parameter coverage and basic prepare/execute validity.
- Add MediaQuery / Resource smoke tests for broad wiring coverage.
- Add hypermedia workflow tests for user-story rel chains (see [hypermedia.md](hypermedia.md)) and a separate `*ContractTest` for the HAL envelope shape.
- Use Koriym.SqlQuality for SQL plan/performance checks when available or requested.
- Use PHPMD complexity reports to prioritize large semantic refactors, not to mandate a single style.
- Avoid mocks for internal dependencies when fake contexts provide a better behavioural surface. External services use Docker; internal dependencies use Fake classes from `tests/Fake/`.

## Page not-found regression test

For each Page resource that loads a primary entity by id, add a `testNotFoundRendersErrorTemplate` case. This pins the per-entity `*NotFoundException` guard at the top of the template (see [resource-patterns.md](resource-patterns.md) — "Not found"). Without it, a regression that removes the guard fails silently with a PHP warning instead of an error template.

## Test contexts

| Context | Purpose |
|---|---|
| `test-hal-api-app` | PHPUnit App-side suite; composes `FakeModule` via `TestModule` |
| `html-test-hal-api-app` | Page tests that render against `FakeSqlQuery` |

When module bindings change, clear the DI cache for every context that was used. The default test suite must remain DB-free.
