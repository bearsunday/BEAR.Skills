# BEAR.Skills

Claude Code skills for BEAR.Sunday framework development.

## Skills

### bear-review

Code quality evaluation using PHPMD metrics and BEAR.Sunday-specific patterns.

**Evaluates:**
- PHPMD metrics (Cyclomatic Complexity, NPath, Parameters, Fields)
- Resource design (Embed usage, body assignment, loop delegation)
- Dependency injection (constructor injection, trait prohibition)
- Type safety, exception design, try-catch patterns
- PHP 8 attributes, validation, AOP, authentication

### bear-hypermedia

Add `#[Link]` attributes to resources and implement HyperMedia tests.

**Features:**
- Analyze resources and add appropriate `#[Link]` declarations
- Generate HyperMedia tests that express use cases as workflows
- Generate ALPS profiles from resource classes

### bear-resource-test

Generate smoke test dataProvider for all resources.

**Features:**
- Scan resource classes and extract method signatures
- Generate dataProvider with method, URI, query, expected code
- Single test class tests all resources

### bear-cache-strategy

Add cache attributes to resources.

**Features:**
- Detect resources without cache declarations
- Classify as content API or computation API
- Apply appropriate `#[Cacheable]`, `#[DonutCache]`, or TTL-based caching

### fix-return-static

Bulk convert `ResourceObject` return types to `static`.

**Usage:**
```bash
find src/Resource -name "*.php" -exec sed -i '' 's/): ResourceObject/): static/g' {} +
```

### sql-quality

SQL performance analysis using [Koriym.SqlQuality](https://github.com/koriym/Koriym.SqlQuality).

**Detects:**
- Full table scans
- Inefficient JOINs
- Index invalidation by functions

## Installation

Copy `.claude/skills/` to your project's `.claude/` directory.

## License

MIT
