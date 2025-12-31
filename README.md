# BEAR.Skills

Claude Code skills for BEAR.Sunday framework development.

## Overview

A collection of AI-powered skills that understand BEAR.Sunday's resource-oriented architecture. These skills help maintain code quality, generate boilerplate, and enforce framework conventions.

## Skills

### Code Quality

#### bear-review

Comprehensive code quality evaluation using PHPMD metrics and BEAR.Sunday-specific patterns.

| Category | Checks |
|----------|--------|
| Metrics | Cyclomatic Complexity, NPath, Parameters, Fields |
| Resource Design | Embed usage, body assignment, loop delegation |
| DI | Constructor injection, trait prohibition, Provider overuse |
| REST | 201 + Location header, HTTP status codes |
| Type Safety | Exception design, DateTime immutability |

#### sql-quality

SQL performance analysis using [Koriym.SqlQuality](https://github.com/koriym/Koriym.SqlQuality).

- Full table scan detection
- Inefficient JOIN analysis
- Index invalidation warnings

### Code Generation

#### bear-resource-generator

Generate complete resource sets from specifications or ALPS profiles.

```
Specification → Migration + Query/Command + SQL + Entity + Resource + JsonSchema + Tests
```

### Documentation

#### const-documenter

Auto-generate PHPDoc for constant classes with confidence levels.

#### resource-documenter

Auto-generate PHPDoc for Resource classes based on REST semantics.

### Refactoring

#### named-to-qualifier

Convert `#[Named('string')]` to type-safe `#[Qualifier]` attributes.

```php
// Before
#[Named('api_endpoint')] string $endpoint

// After
#[ApiEndpoint] string $endpoint
```

#### fix-return-static

Bulk convert `ResourceObject` return types to `static`.

### Resource Enhancement

#### bear-hypermedia

Add `#[Link]` attributes and generate HyperMedia tests expressing use cases as workflows.

#### bear-cache-strategy

Analyze resources and apply appropriate cache attributes (`#[Cacheable]`, `#[DonutCache]`, TTL).

#### bear-resource-test

Generate smoke test dataProvider covering all resource endpoints.

### Security

#### bear-security-setup

Set up [BEAR.Security](https://github.com/bearsunday/BEAR.Security) with SAST, AI Auditor, and GitHub Actions.

### Deployment

#### bear-preflight

Pre-deployment comprehensive check covering compile, security, performance, quality, and configuration.

```text
✅ Compile    - bear.compile + runtime bindings (Ray.MediaQuery Entity, etc.)
✅ Security   - SAST, hardcoded credentials, env settings
✅ Performance - Cache attributes, SQL quality, N+1 detection
✅ Quality    - Static analysis, tests, coverage
✅ Dependencies - composer audit, lock file
✅ Configuration - Context, environment variables
```

## Installation

```bash
# Copy skills to your project
cp -r .claude/skills/ /path/to/your/project/.claude/skills/
```

## Requirements

- Claude Code CLI
- BEAR.Sunday project

## References

- [BEAR.Sunday Documentation](https://bearsunday.github.io/)
- [Ray.Di Documentation](https://ray-di.github.io/)

## License

MIT
