# BEAR.Skills (alpha)

Claude Code skills for BEAR.Sunday framework development.

> **Note:** This project is in alpha stage. Skills are experimental and not fully tested.

## Overview

A collection of AI-powered skills that understand BEAR.Sunday's resource-oriented architecture. These skills help maintain code quality, generate boilerplate, and enforce framework conventions.

## Available Skills

| Skill | Command | Description |
|-------|---------|-------------|
| bear-resource-generator | `/bear-resource-generator` | Generate complete resource sets from specifications |
| bear-review | `/bear-review` | Code quality evaluation with PHPMD metrics |
| bear-preflight | `/bear-preflight` | Pre-deployment comprehensive check |
| bear-security-setup | `/bear-security-setup` | Set up BEAR.Security with SAST |
| bear-cache-strategy | `/bear-cache-strategy` | Apply cache attributes to resources |
| bear-hypermedia | `/bear-hypermedia` | Add #[Link] and generate HyperMedia tests |
| bear-resource-test | `/bear-resource-test` | Generate smoke test dataProvider |
| sql-quality | `/sql-quality` | SQL performance analysis |
| const-documenter | `/const-documenter` | Auto-generate PHPDoc for constants |
| resource-documenter | `/resource-documenter` | Auto-generate PHPDoc for resources |
| named-to-qualifier | `/named-to-qualifier` | Convert Named to Qualifier attributes |
| fix-return-static | `/fix-return-static` | Convert return types to static |

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

### Claude Code Plugin (Recommended)

```bash
# 1. Add marketplace
/plugin marketplace add bearsunday/BEAR.Skills

# 2. Install (all 12 skills included)
/plugin install bear-skills
```

### Update

```bash
/plugin update bear-skills
```

### Remove

```bash
/plugin uninstall bear-skills
```

### Manual Installation (Alternative)

```bash
git clone https://github.com/bearsunday/BEAR.Skills.git
cp -r BEAR.Skills/skills/ /path/to/your/project/.claude/skills/
```

## Requirements

- Claude Code CLI
- BEAR.Sunday project

## References

- [BEAR.Sunday Documentation](https://bearsunday.github.io/)
- [Ray.Di Documentation](https://ray-di.github.io/)

## License

MIT
