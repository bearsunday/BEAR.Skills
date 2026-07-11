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

The three levels are stacked by risk — go top-down on a legacy project, not bottom-up:

- **Level 1 — Surface cleanup**: mechanical, behaviour-preserving edits (`: static` return types, body literals, method order, naming alignment, domain exceptions).
- **Level 2 — Contract and QA hardening**: JSON Schema in/out, ALPS IDs, `#[Link]`/`#[Embed]` rel conventions, smoke/hypermedia/contract tests, SQLQuality and PHPMD gates.
- **Level 3 — Semantic refactor**: one slice at a time — BDR Read/Write split, Ray.MediaQuery, Result projections, Input DTOs, pagination, Cacheable Shape A/B.

The full item-by-item catalog lives in [skills/bear-clean-style/SKILL.md](skills/bear-clean-style/SKILL.md) and its `references/` — that is the single source of truth; this README intentionally does not duplicate it.

### Documentation

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-documenter` | PHPDoc auto-generation | "Document this class" |
| `bear-audit-fix` | Fix BEAR.ApiDoc audit gaps | "Fix the documentation audit findings" |

### Resource Enhancement

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-hypermedia` | Add #[Link] attributes | "Add hypermedia links to resources" |
| `bear-cacheable` | Apply cache attributes | "Analyze and add cache attributes" |

### Migration

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-migration` | Migrate legacy PHP apps to BEAR.Sunday | "Migrate this Symfony app to BEAR.Sunday" |

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
│   ├── bear-audit-fix/
│   ├── bear-cacheable/
│   ├── bear-clean-style/
│   ├── bear-clean-style-consultant/
│   ├── bear-documenter/
│   ├── bear-from-alps/
│   ├── bear-hypermedia/
│   ├── bear-migration/
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
