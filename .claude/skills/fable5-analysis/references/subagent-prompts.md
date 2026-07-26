# Subagent prompt templates

Paste these into `Agent` tool calls. Always set `subagent_type: general-purpose`
and `model: opus`. Dispatch all analysts in one message (parallel); then all
verifiers in one message (parallel).

Fill the `{{...}}` slots. Keep the "return conclusions and evidence, not a
narration of your reasoning" line — it is deliberate (see the reasoning_extraction
note in SKILL.md).

---

## Analyst prompt

```
You are one of several independent analysts. Do not assume the others exist; reach your own conclusion.

Question: {{one-sentence question from Phase 0}}
A complete answer must address: {{success criteria from Phase 0}}
Your assigned lens: {{lens — see catalog below}}. Analyze primarily through this lens.

Rules:
- Gather evidence with tools before asserting anything. Do not guess.
- Label every claim: [evidenced: <file:line / command output / source>] or [inferred].
- Before you finish, try to disprove your own conclusion; if it survives, report it; if not, revise it.
- Match effort to the stakes; do not pad.

Return ONLY this structure (conclusions and evidence — not a narration of your reasoning):
- ANSWER: <your conclusion in 1–3 sentences>
- KEY EVIDENCE: <bulleted, each with its [evidenced: ...] pointer>
- CONFIDENCE: <high | medium | low> — <one line why>
- OPEN QUESTIONS: <what you could not verify>
```

## Verifier prompt

```
You are an adversarial verifier. Your only job is to try to REFUTE the claim below by checking the actual artifacts yourself — not by re-reading the analyst's text.

Claim to test: {{one conclusion from Phase 1}}
Its stated evidence: {{the analyst's KEY EVIDENCE for that claim}}
Where to look: {{files / commands / sources}}

Rules:
- Independently verify each evidence pointer. If a pointer does not support the claim, say so.
- Actively search for the strongest counter-evidence, a missed case, a wrong assumption, or an unverified leap.
- Default to skeptical: if you cannot confirm the claim, mark it UNVERIFIED, not true.

Return ONLY this structure:
- VERDICT: <upheld | refuted | unverified>
- BASIS: <the specific evidence you checked, with [evidenced: ...] pointers>
- STRONGEST OBJECTION: <the best case against the claim, even if it ultimately fails>
```

---

## Lens catalog (Phase 1)

Assign a different lens to each analyst so the ensemble diverges:

- **Mechanism-first** — how does it actually work, step by step, from the source?
- **Evidence-first** — what do the artifacts (code, logs, data, docs) directly show, ignoring the expected story?
- **Counterexample-first** — assume the obvious answer is wrong; hunt for the case that breaks it.
- **Per-hypothesis** — take exactly one candidate from Phase 0 and test it hard.
- **Boundary/edge** — inputs, limits, concurrency, failure modes, and what happens outside the happy path.
- **Historical** — what changed recently (git history, diffs) and does the timeline explain the behavior?

## Fan-out reference (from SKILL.md Phase 0)

| Depth | Analysts | Verifiers |
|-------|----------|-----------|
| Light | 1 | 1 |
| Standard (default) | 2–3 | 2 |
| Deep | 3–5 | 3 |
