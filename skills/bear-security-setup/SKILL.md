---
user-invocable: true
name: bear-security-setup
description: Set up bear/security for BEAR.Sunday projects. Configures installation, psalm.xml taint plugin, composer scripts, and GitHub Actions workflow.
---

# BEAR.Security Setup Skill

Sets up [bear/security](https://github.com/bearsunday/BEAR.Security) for BEAR.Sunday projects.

## Pre-Setup Questions

Use AskUserQuestion tool to confirm:

1. **AI Auditor Authentication**
   - API Key (ANTHROPIC_API_KEY)
   - Claude CLI (Max Plan - no API key required)
   - Don't use AI Auditor

2. **GitHub Actions Workflow**
   - Add workflow
   - Skip

3. **Taint Analysis Targets**
   - Page (Web pages) only
   - App (API) only
   - Both

## Setup Steps

### 1. Installation

```bash
composer require --dev bear/security
```

### 2. Configure psalm.xml

Add taint plugin and stubs to existing `psalm.xml`.

#### Target configuration (based on user selection)

- `Page`: For `html` context serving web pages
- `App`: For `api` context serving APIs

```xml
<?xml version="1.0"?>
<psalm
    xmlns="https://getpsalm.org/schema/config"
    errorLevel="1"
>
    <projectFiles>
        <directory name="src"/>
    </projectFiles>
    <stubs>
        <file name="vendor/bear/security/stubs/AuraSql.phpstub"/>
        <file name="vendor/bear/security/stubs/PDO.phpstub"/>
        <file name="vendor/bear/security/stubs/Qiq.phpstub"/>
    </stubs>
    <plugins>
        <pluginClass class="BEAR\Security\Psalm\ResourceTaintPlugin">
            <targets>
                <!-- Configure based on user selection -->
                <target>Page</target>
                <target>App</target>
            </targets>
        </pluginClass>
    </plugins>
</psalm>
```

### 3. Add composer.json Scripts

```json
{
    "scripts": {
        "security": "./vendor/bin/bear.security-scan src",
        "taint": "./vendor/bin/psalm --taint-analysis 2>&1 | grep -E 'Tainted' || true"
    },
    "scripts-descriptions": {
        "security": "Run SAST security scan",
        "taint": "Run Psalm taint analysis"
    }
}
```

### 4. AI Auditor Setup (Based on Selection)

**For API Key:**
Add to `.env` or environment variables:
```
ANTHROPIC_API_KEY=sk-ant-...
```

**For Claude CLI:**
```bash
# Install Claude CLI if not installed
npm install -g @anthropic-ai/claude-code

# Authenticate
claude auth login
```

### 5. GitHub Actions Workflow (If Selected)

```bash
mkdir -p .github/workflows
cp vendor/bear/security/workflows/security-sast.yml .github/workflows/
```

## Command Reference

After setup, provide these commands:

| Command | Description |
|---------|-------------|
| `composer security` | Run SAST (static analysis) |
| `composer taint` | Run taint analysis |
| `./vendor/bin/bear-security-dast` | Run DAST (dynamic testing) |
| `./vendor/bin/bear-security-audit src` | Run AI audit |

## Stub Reference

| Stub | Purpose |
|------|---------|
| `AuraSql.phpstub` | Marks Aura.Sql query methods as taint sinks |
| `PDO.phpstub` | Marks PDO methods as taint sinks |
| `Qiq.phpstub` | Marks Qiq template output as taint sinks |

## Verification

After setup, verify:

1. `composer security` runs successfully
2. `composer taint` runs successfully
3. If AI Auditor configured, `./vendor/bin/bear-security-audit src` runs
4. If GitHub Actions configured, results appear in Security > Code scanning tab

## Security Workflow

After setup is complete, run the security workflow:

### 1. Run SAST

```bash
vendor/bin/bear.security-scan src
```

### 2. Review & Fix Vulnerabilities

For each finding:
- **Real vulnerability**: Fix the code
- **False positive**: Add `@security-ignore` comment on the same line:

```php
$code; // @security-ignore <issue-type>: <reason>
```

Example:
```php
$path = $this->buildPath($id); // @security-ignore path-traversal: $id is validated integer from router
```

### 3. Run AI Auditor

```bash
vendor/bin/bear-security-audit src
```

Detects business logic issues that pattern matching cannot find:
- IDOR (Insecure Direct Object Reference)
- Mass Assignment
- Race Conditions
- Authorization bypasses

### 4. Verify & Re-scan

- Review all `@security-ignore` comments are justified
- Re-run scans to confirm issues are resolved
- Never ignore a real vulnerability

## Important Guidelines

- **@security-ignore format**: `// @security-ignore <issue-type>: <reason>`
- **Always provide a reason**: Explain why this is a false positive
- **Re-scan after fixes**: Confirm vulnerabilities are resolved
- **Review existing ignores**: Check if previously ignored issues are still valid

## Reporting to User

After completing the security workflow, provide a summary report:

### Report Template

```markdown
## Security Scan Summary

### SAST Results
- **Total findings**: X issues detected
- **Files scanned**: Y files

### Analysis Results
| Finding | File:Line | Assessment | Action |
|---------|-----------|------------|--------|
| TaintedSql | User.php:42 | False positive | @security-ignore added |
| TaintedHtml | Index.php:15 | Real vulnerability | Fixed |

### @security-ignore Added
- `src/Resource/App/User.php:42` - path-traversal: $id is validated integer from router
- `src/Resource/Page/Index.php:28` - tainted-html: Output is escaped by Qiq template

### AI Auditor Results
- Business logic issues: X found
- (List any IDOR, authorization, or other logic issues)

### Action Required
The following items require your review:
1. [ ] Verify @security-ignore comments are appropriate
2. [ ] Review any real vulnerabilities that were fixed
3. [ ] Confirm business logic issues are addressed

### Final Status
- SAST: ✓ Passed (X issues resolved, Y ignored with justification)
- AI Audit: ✓ Passed / ⚠ Issues found
```

### Report Guidelines

- **Always report counts**: Total findings, resolved, and ignored
- **Show locations**: Include file:line for all @security-ignore comments
- **Explain reasoning**: Why each false positive was ignored
- **Require confirmation**: User must review and approve ignored items
- **Be transparent**: Never hide or omit findings

## References

- [BEAR.Security GitHub](https://github.com/bearsunday/BEAR.Security)
- [Security Manual](https://bearsunday.github.io/manuals/1.0/en/security.html)
- [Vulnerability Reference](https://bearsunday.github.io/BEAR.Security/issues/en/)
