---
user-invocable: true
name: bear-clean-style-consultant
description: Consultation and migration-planning skill for BEAR.Sunday clean style and semantic refactors. Use when asked whether to apply BEAR clean style, how to split Level 1-3 changes, whether a ResourceClient call should become GET Embed, whether template loop/if logic should move to Result Generator projections, whether to use BDR, Input DTOs, AffectedRows, JsonSchema, ALPS/HAL, smoke tests, SQLQuality, PHPMD, or which BEAR execution skill should be used next. This skill does not edit code.
---

# BEAR Clean Style Consultant

## Purpose

Advise on BEAR.Sunday clean-style and semantic-refactor decisions before code is changed. This is a consultation skill: inspect, classify, explain tradeoffs, and propose batches. Do not edit files with this skill.

Use `bear-clean-style` after the user chooses an implementation batch.

## Operating stance

- Treat clean style as an opinionated project style, not a universal BEAR.Sunday rule.
- Prefer “should we?” and “in what order?” over immediate implementation.
- Separate mechanical cleanup from semantic refactor risk.
- Say when not to apply a pattern. A good consultation has explicit “やらないこと”.
- Read project-local docs and source before giving project-specific advice.

## Consultation levels

| Level | Name | Consultant question | Typical recommendation |
|---|---|---|---|
| 1 | Surface cleanup | “安全に直せるか？” | return `static`, body literal, method order, naming/SQL alignment in small batches |
| 2 | Contract and QA hardening | “移行前に契約やテストを固めるべきか？” | JsonSchema in/out, body array-shape PHPDoc, ALPS/Link/Embed, ApiDoc/OpenAPI, smoke tests, hypermedia tests, SQLQuality, PHPMD |
| 3 | Semantic refactor | “責務を移すべきか？” | BDR, Query/Command split, typed Result, named `Generator`, Template Projection Lift, Input DTO, FileUpload, AffectedRows |

## Decision guide

### Embed

- `#[Embed]` is only for GET representation composition.
- Recommend Embed when an `onGet` response includes related resource representations and the rel is a taxonomy noun.
- Do not recommend Embed for POST/PUT/DELETE, validation, authorization, write orchestration, or transient data used only for branching.

### Template Projection Lift

Recommend Level 3 when templates contain loop-local domain branching or repeated display computation:

- entity status filtering inside `foreach`;
- summary/date/url/relative-time calculation in a loop;
- duplicated card/feed/list item logic across pages.

Do not recommend moving purely presentational conditions such as empty-list messages, selected form options, validation errors, layout/auth toggles, or Page not-found guards.

### Input DTO and FileUpload

Recommend Input DTOs when the Resource boundary has many fields, nested/structured input, tri-state collection semantics, or upload fields that should become value objects. Do not recommend DTOs for short, flat scalar signatures.

### AffectedRows

Recommend `AffectedRows` only when the caller genuinely needs DML metadata. Keep ordinary Resource-facing Command methods `void` when the Resource already checks existence before UPDATE/DELETE.

### SQLQuality and PHPMD

Recommend SQLQuality for query-plan/performance confidence. Recommend PHPMD complexity reports to choose refactor targets, not as proof that one design style is mandatory.

## Workflow

1. Inspect local guidance, docs, resource/query/template/test structure, and current diffs.
2. Identify candidate changes and classify each as Level 1, 2, or 3.
3. For each candidate, decide: **do now**, **defer**, **do not apply**, or **needs explicit product/API decision**.
4. Propose a small PR batch and name the execution skill to use next.
5. Stop at advice unless the user explicitly switches to implementation.

## Output format

```markdown
## 判定

この相談は Level X です。

## 推奨

| Candidate | Level | Decision | Why | Next skill |
|---|---:|---|---|---|
| ... | 1 | Do now | ... | bear-clean-style |

## やらないこと

- ...

## リスク

- ...

## 次のPR候補

1. ...
2. ...
```

Keep the answer concrete. Prefer file examples when project source was inspected.
