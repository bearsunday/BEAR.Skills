---
name: bear-audit-fix
description: >-
  Read a BEAR.ApiDoc documentation audit (audit.html / audit.md) and remediate
  each reported gap by dispatching to the right skill: missing PHPDoc summaries,
  missing request/response JSON Schema, or a missing #[Alps] attribute. Use when
  the user says "fix the audit", "監査の不足を直す", "close documentation gaps",
  "audit.html を直して", or asks to act on a BEAR.ApiDoc audit report.
user-invocable: true
---

# BEAR.ApiDoc Audit Fix

BEAR.ApiDoc's audit (`audit.html` / `audit.md`) reports *where* documentation is
missing. This skill closes the loop: it reads the audit and **fixes** each gap by
dispatching to the skill that owns that kind of artifact.

The audit is machine-actionable because each finding carries a `findingType`
token (bound to the `audit.xml` report profile). That token is the dispatch key.

## When to Use This Skill

- You ran a BEAR.ApiDoc audit (`format=audit`, e.g. `composer docs-audit`) and want the gaps fixed, not just listed.
- The user points at `docs/audit.html` / `docs/audit.md` and asks to act on it.
- You want documentation coverage (summaries, schemas, ALPS) brought up across a project.

## Dispatch table (findingType → fix → skill)

| `findingType` | Audit message | Fix | Skill to invoke |
|---|---|---|---|
| `class-summary` | Missing resource class summary. | Add a class-level PHPDoc summary | **bear-documenter** |
| `operation-summary` | Missing operation summary. | Add a method-level PHPDoc summary on `on{Get,Post,…}` | **bear-documenter** |
| `response-schema` | Missing response schema. | Create response JSON Schema + `#[JsonSchema(schema:)]` | **fake-json-to-schema** (from `var/fake/`) |
| `request-schema` | Missing request schema for non-path body input. | Create request param JSON Schema + `#[JsonSchema(params:)]` | **fake-json-to-schema** / hand-author from the method's input params |
| `alps` | Missing ALPS attribute. | Add `#[Alps]` to the class or method | **bear-to-alps** |

If a sibling skill is unavailable in the current environment, fall back to editing by hand following that skill's conventions — never invent schemas or semantics (see Safety).

## Execution Flow

### Step 1: Obtain the audit

Prefer an existing `docs/audit.html`. If absent or stale, regenerate it:

```bash
# uses the project's audit apidoc.xml (format=audit)
php vendor/bin/apidoc -c apidoc.audit.xml   # or: composer docs-audit
```

### Step 2: Parse findings

Read `audit.html` and extract, per `<section class="operation">`:

- the operation: `<h3>METHOD /path</h3>` (and the `id="op-…"` anchor),
- each `<li class="finding">` and its `<code class="findingType">…</code>` token.

The `findingType` token is the reliable dispatch key. `audit.md` is a fallback —
its bullets are human messages (`- Missing response schema.`), so map message →
type with the table above when only Markdown is available.

### Step 3: Resolve the target

For each `METHOD /path`, resolve the BEAR.Sunday resource class and the `on{Method}`
handler (via the app's routing / resource scan). Scope every fix to that exact
class+method — do not touch unrelated code.

### Step 4: Dispatch and fix

Group findings by `findingType` and invoke the mapped skill once per target.
Apply fixes in low-risk order: summaries → schemas → ALPS. After generating a
schema, wire it with the matching `#[JsonSchema]` attribute on the method.

### Step 5: Re-audit and loop

Regenerate the audit and diff. Repeat until findings are empty or the remainder
are non-auto-fixable. Report:

- **Fixed** — gap closed and verified by re-audit.
- **Needs review** — applied with low confidence (left with a `@todo 要確認` marker).
- **Skipped** — not auto-fixable, with the reason.

## Safety

This skill follows the same discipline as `bear-documenter`:

- **No fabrication.** Summaries are inferred from the class/method/params with a
  confidence level; schemas come from *actual* fake responses (`var/fake/`), not
  invented shapes. ALPS ids reuse existing semantics where they exist.
- **Confidence markers.** Low-confidence edits carry `@todo 要確認(確信度X):` so a
  human reviews them. A clean audit is necessary but not sufficient — coverage is
  not correctness.
- **Verify, don't assume.** Each fix is confirmed by re-running the audit; report
  failures honestly rather than claiming the gap is closed.
- **Scope.** One target per fix; never bulk-edit beyond the operations the audit
  named.

## findingType reference

Defined by the BEAR.ApiDoc audit profile (`docs/alps/audit.xml`):

- `response-schema` — the operation declares no response JSON Schema.
- `request-schema` — a non-path body input exists but no request JSON Schema validates it.
- `class-summary` — the resource class has no PHPDoc summary.
- `operation-summary` — the `on*` handler has no PHPDoc summary.
- `alps` — the class/method carries no `#[Alps]` attribute (only reported when an ALPS profile is configured).

## Related

- **bear-documenter** — PHPDoc generation (summaries).
- **bear-to-alps** — ALPS profile / `#[Alps]` attributes.
- **fake-json-to-schema** — JSON Schema from `var/fake/` responses.
- BEAR.ApiDoc — generates the audit this skill consumes.
