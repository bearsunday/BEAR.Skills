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
- 201 Created + Location header patterns
- DateTime immutability, Provider overuse

### bear-resource-generator

Generate complete BEAR.Sunday resource sets from specifications.

**Generates:**
- Phinx migrations
- Query/Command interfaces (CQRS)
- SQL files (flat structure in `var/sql/`)
- Entity classes (readonly properties)
- Resource classes with full CRUD
- JsonSchema (request/response)
- Tests (unit and integration)

### bear-security-setup

Set up [bear/security](https://github.com/bearsunday/BEAR.Security) for BEAR.Sunday projects.

**Features:**
- Configure psalm.xml with taint plugin and stubs
- Add composer scripts for security scanning
- Set up AI Auditor (API key or Claude CLI)
- Add GitHub Actions workflow
- Security workflow with reporting guidelines

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

### const-documenter

Auto-generate PHPDoc comments for constant classes.

**Features:**
- Infer intent from constant names and values
- Add confidence levels (high/medium/low)
- Optional `@todo` markers for review

### named-to-qualifier

Convert `#[Named('string')]` to type-safe `#[Qualifier]` attributes.

**Features:**
- Search and list Named string usage
- Generate Qualifier attribute classes
- Update NamedModule configuration

### resource-documenter

Auto-generate PHPDoc comments for Resource classes.

**Features:**
- Infer intent from class names and method signatures
- Add confidence levels based on REST semantics
- Optional `@todo` markers for review

## Installation

Copy `.claude/skills/` to your project's `.claude/` directory.

## License

MIT
