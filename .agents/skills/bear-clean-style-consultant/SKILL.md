---
user-invocable: true
name: bear-clean-style-consultant
description: >-
  Plan BEAR.Sunday clean-style and semantic-refactor batches without editing
  code. Decide whether to apply BEAR clean style, how to split Level 1/2/3
  changes, whether a ResourceClient call should become a GET Embed, whether
  information belongs in an app:// resource or the Page (到達可能性/reachability),
  whether template loops should become Result Generator projections, when to adopt
  BDR/Input DTOs/AffectedRows/JsonSchema/ALPS-HAL/smoke tests/SQLQuality/PHPMD,
  and which execution skill should run next. Use when user says "should we
  apply clean style", "clean style consultant", "クリーンスタイル相談",
  "migration plan", "リファクタ方針", "どのレベルか", "Embedにすべきか",
  "Level判定", or asks for advice before implementation. Read-only skill —
  does not edit files.
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

The canonical Level definitions (with the architecture and topic-file index)
are in `bear-clean-style/references/clean-style-conventions.md`. The table
below reframes them for consultation (the question to ask, the recommendation
to give).

| Level | Name | Consultant question | Typical recommendation |
|---|---|---|---|
| 1 | Surface cleanup | “安全に直せるか？” | return `static`, body literal, method order, naming/SQL alignment in small batches |
| 2 | Contract and QA hardening | “移行前に契約やテストを固めるべきか？” | JsonSchema in/out, body array-shape PHPDoc, ALPS/Link/Embed, ApiDoc/OpenAPI, smoke tests, hypermedia tests, SQLQuality, PHPMD |
| 3 | Semantic refactor | “責務を移すべきか？” | BDR, Query/Command split, typed Result, named `Generator`, Template Projection Lift, Input DTO, FileUpload, AffectedRows |

## Decision guide

### Reachability (premise)

Before deciding Embed, decide **where the information lives**. The prior axis is 到達可能性 (reachability): information must be operable in the App context.

- Domain information that should exist on the App surface is defined as an App resource (`app://`). The Page **references** it, does not own it.
- Being an App resource means the information is reachable from ALL of: HAL API, CLI (`#[Cli]`), `#[Embed]`, `#[Link]`, `#[Cacheable]`, JSON Schema, ALPS. The context is one-directional: Page → App may reference; App → Page does not exist.
- Ask one question: **"should this information exist on the App surface?"** Yes → App; No → Page.
- **Failure (hole, severe):** only the Page assembles information that has no corresponding `app://`. It is trapped on the HTML island and invisible from API/CLI/Embed — a reachability hole in the API surface. Forbidden. Even at 1:1, prefer Page reads App to keep the information resident in the App context.
- **Failure (duplication, minor):** the Page re-assembles information that already exists in App. Hurts DRY but preserves reachability; lean toward referencing.
- Only pure presentation derivatives may be Page-owned: `bodyHtml`, CSRF token, form display state, empty-list messages, auth toggles, not-found guards — things with no meaning on the API surface.

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

この相談の中心は Level X です（候補ごとの Level は下表を参照）。

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
