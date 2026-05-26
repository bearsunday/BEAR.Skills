# BEAR.Skills

**AI-Powered Skills for BEAR.Sunday Development**

Claude Code skills that understand BEAR.Sunday's resource-oriented architecture. Generate code, review quality, and enforce framework conventions with natural language.

---

## Natural Language Development

Just tell your AI assistant what you want:

**English:**
```text
"Review the Article resource for BEAR.Sunday best practices"
"Generate a User resource with CRUD operations"
"Check if my DI patterns follow Ray.Di conventions"
```

**日本語:**
```text
"Articleリソースをレビューして"
"CRUDを持つUserリソースを生成して"
"DIパターンがRay.Diの規約に従っているか確認して"
```

## Requirements

- Claude Code
- BEAR.Sunday project

## Installation

### Claude Code Plugin (Recommended)

```bash
# 1. Add marketplace
/plugin marketplace add bearsunday/BEAR.Skills

# 2. Install
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

## Quick Start

```text
/bear-review
/bear-clean-style-consultant
/bear-clean-style
```

Or describe your task naturally - Claude will automatically select the appropriate skill.

## Available Skills

### Code Quality

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-review` | Code quality review | "Review this resource" |

### Code Generation

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-resource-gen` | Generate complete resource sets | "Generate a Ticket resource with CRUD" |
| `bear-from-alps` | Generate project from ALPS profile | "Create resources from this ALPS profile" |

### ALPS Integration

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-to-alps` | Extract ALPS profile from project | "Generate ALPS profile from resources" |
| `bear-from-alps` | Generate project from ALPS profile | "Build a project from this ALPS profile" |

### Refactoring

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-clean-style-consultant` | Plan clean-style and semantic-refactor batches | "Should this project apply BEAR clean style?" |
| `bear-clean-style` | Apply BEAR clean-style refactors | "Apply Level 1 clean-style cleanup" |
| `bear-refactor` | Refactoring tools | "Convert Named to Qualifier" |

#### About BEAR clean style

`bear-clean-style` is opinionated — it applies conventions distilled from one reference project ([MyVendor.Cms](https://github.com/bearsunday/MyVendor.Cms)), not universal BEAR.Sunday rules. Treat it as a project style. Use `bear-clean-style-consultant` first to pick a level and confirm fit; `bear-clean-style` then makes the edits in small, reviewable batches.

The three levels are stacked by risk — go top-down on a legacy project, not bottom-up.

**Level 1 — Surface cleanup** (mechanical, behaviour-preserving)

- **`static` return type** — `on*` handlers that return `$this` declare `: static` so the resource type chain stays accurate at every call site.
- **Body literal** — write `$this->body = [...]` (or `+=` when `#[Embed]` injects slots) instead of scattered `$this->body['k'] = $v;`. The response shape reads top-to-bottom as JSON.
- **Method order** — `__construct`, public `on*` in HTTP-verb order (`onGet → onPost → onPut → onDelete`), private helpers last. Reading top-to-bottom mirrors the public surface first.
- **Query/Command/SQL naming alignment** — `#[DbQuery('article_item')] public function item(int $id)` speaks one vocabulary across SQL filename, method name, and attribute id.
- **Property naming** — read interfaces as queryable nouns (`$article`), write interfaces with `Cmd` suffix (`$articleCmd`). `$this->article->item($id)` reads as a source; `$this->articleCmd->add(...)` reads as a tool.
- **Domain exceptions** — replace generic `LogicException` / `RuntimeException` in `src/` with a project `Exception\<DomainName>Exception` subclass.

**Level 2 — Contract and QA hardening** (adds confidence before semantic changes)

- **JSON Schema in/out** — `#[JsonSchema(schema:, params:)]` validates response shape and request body; `var/json_schema/<entity>.json` and `var/json_validate/<entity>_<verb>.json` stay in lock-step with the method signature.
- **Body array-shape PHPDoc** — `/** @property array{...} $body */` on the Resource class so static analysis and IDEs see the JSON shape.
- **ALPS IDs** — entity-prefixed semantic IDs (`articleId`, `articleSlug`, `categoryParentId`) instead of bare `id` / `slug` to avoid collision across entities.
- **`#[Link]` rel cleanup** — Choreography verbs (`goArticleList`, `goAuthor`, `doCreateArticle`, `doDeleteTag`) sourced from the ALPS profile.
- **`#[Embed]` rel cleanup** — Taxonomy nouns (`author`, `category`, `tagList`) only. `#[Embed(rel: 'goAuthor')]` is wrong by construction.
- **ApiDoc / OpenAPI output** — generated docs (`docs/openapi.json`, `docs/index.html`, `docs/llms.txt`) stay in sync with the schemas.
- **Hypermedia workflow tests** — one file per user story under `tests/Hypermedia/`, `#[Depends]`-linked steps, testdox output reads as the narrative.
- **HAL envelope contract test** — `_links` (Choreography) vs `_embedded` (Taxonomy) pinned in a separate `*ContractTest` so layer slips fail one isolated test.
- **SQL smoke tests** — placeholder/parameter coverage and basic prepare/execute validity for every SQL file.
- **Resource smoke tests** — broad MediaQuery / Resource wiring coverage without a real DB.
- **SQLQuality** — Koriym.SqlQuality plan/performance checks for query files that matter.
- **PHPMD complexity gates** — used to prioritize semantic-refactor targets, not to mandate a single style.
- **`#[Validate]` application validation** — stateful invariants (uniqueness checks, cross-entity rules) via injected services, surfaced as 422 with field-level errors.
- **Page not-found template guard** — per-entity `*NotFoundException` thrown at the top of `templates/Page/<X>.php` so 404s reach the error template instead of warning on null property access.

**Level 3 — Semantic refactor** (one slice at a time; tests required even when behaviour is preserved)

- **BDR Read/Write split** — `<Entity>QueryInterface` for reads, `<Entity>CommandInterface` for writes; both under `src/Query/` but never mixed.
- **Ray.MediaQuery `#[DbQuery]`** — interfaces bind directly to SQL files; no concrete query class.
- **`src/Result/*` typed projections** — Ray.MediaQuery result objects only (not domain entities, controllers, or service helpers).
- **Named `Generator` traversals** — `ArticleSelection::published()`, `ArticleSelection::feed($now)` — read-side views, not entity methods.
- **Template Projection Lift** — loop-local domain branching (`if ($article->isPublished())`, `summary()`, date math) moves from templates to Result objects.
- **Input DTO at Resource boundary** — `<Vendor>\Input\<Action>Input` (`final readonly` with `#[Input]`) when fields cross the readability threshold or have tri-state semantics. Not pushed through Command interfaces.
- **FileUpload value objects** — replace ad-hoc `$_FILES` / array shapes with typed value objects at the Resource boundary.
- **`AffectedRows`** — explicit DML metadata return only when a non-Resource caller needs it; canonical Resource-facing Command methods stay `void`.
- **Natural-key reselect after insert** — `bySlug()` / `byEmail()` / `byFilename()` instead of `lastInsertId()` to recover the assigned id.
- **`#[Pager]` / `PagesInterface` pagination** — collection reads return paged results with `total` / `hasNext` / `maxPerPage`; Pagerfanta `ArrayAdapter` mirrors the contract in the fake.
- **`#[Cacheable]` Shape A** — `#[Embed]` alone is the cache contract; `QueryRepository::setCacheDependency` merges children automatically.
- **`#[Cacheable]` Shape B** — explicit `UriTagInterface::fromAssoc()` to `Header::SURROGATE_KEY` for N URIs whose count or parameters come from the database. Never mix A and B on the same response.
- **SELECT / Entity column-order lock-step** — Ray.MediaQuery's `FetchNewInstance` binds positionally, so SELECT order and entity constructor order must match exactly. The scanner flags drift as P1.

### Documentation

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-documenter` | PHPDoc auto-generation | "Document this class" |

### Resource Enhancement

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-hypermedia` | Add #[Link] attributes | "Add hypermedia links to resources" |
| `bear-cacheable` | Apply cache attributes | "Analyze and add cache attributes" |

### Testing

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-smoke-test` | Generate 4-layer smoke tests | "Generate smoke tests for all resources" |

### Security & Deployment

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-security-setup` | Setup BEAR.Security | "Configure security scanning" |
| `bear-preflight` | Pre-deploy checks | "Run deployment preflight checks" |

## Directory Structure

```text
BEAR.Skills/
├── .claude/skills/      # Development
├── skills/              # Distribution (plugin)
│   ├── bear-cacheable/
│   ├── bear-clean-style/
│   ├── bear-clean-style-consultant/
│   ├── bear-documenter/
│   ├── bear-from-alps/
│   ├── bear-hypermedia/
│   ├── bear-preflight/
│   ├── bear-refactor/
│   ├── bear-resource-gen/
│   ├── bear-review/
│   ├── bear-security-setup/
│   ├── bear-smoke-test/
│   └── bear-to-alps/
└── .claude-plugin/      # Plugin manifest
```

## References

- [BEAR.Sunday Documentation](https://bearsunday.github.io/)
- [Ray.Di Documentation](https://ray-di.github.io/)
- [BEAR.Sunday llms.txt](https://bearsunday.github.io/llms-full.txt)

---

**Stop coding blind. Just ask your AI.**
