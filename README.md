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
/bear-cleancode-review
```

Or describe your task naturally - Claude will automatically select the appropriate skill.

## Available Skills

### Code Quality

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `php-cleancode-review` | General PHP quality review | "Check the code quality of this class" |
| `bear-cleancode-review` | BEAR.Sunday specific review | "Review this resource for framework patterns" |
| `sql-quality` | SQL performance analysis | "Analyze the SQL queries for performance issues" |

### Code Generation

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-resource-generator` | Generate complete resource sets | "Generate a Ticket resource with CRUD" |
| `alps-to-bear` | Generate from ALPS profile | "Create resources from this ALPS profile" |

### Refactoring

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `named-to-qualifier` | Convert Named to Qualifier | "Replace #[Named] with type-safe qualifiers" |
| `fix-return-static` | Fix return types | "Convert ResourceObject returns to static" |

### Documentation

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `const-documenter` | PHPDoc for constants | "Add documentation to constant classes" |
| `resource-documenter` | PHPDoc for resources | "Document this resource class" |

### Resource Enhancement

| Skill | Purpose | Example Prompt |
|-------|---------|----------------|
| `bear-hypermedia` | Add Link attributes | "Add hypermedia links to resources" |
| `bear-cache-strategy` | Apply cache attributes | "Analyze and add cache attributes" |
| `bear-resource-test` | Generate smoke tests | "Generate tests for all resources" |

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
│   ├── bear-cleancode-review/
│   └── php-cleancode-review/
└── .claude-plugin/      # Plugin manifest
```

## References

- [BEAR.Sunday Documentation](https://bearsunday.github.io/)
- [Ray.Di Documentation](https://ray-di.github.io/)
- [BEAR.Sunday llms.txt](https://bearsunday.github.io/llms-full.txt)

---

**Stop coding blind. Just ask your AI.**
