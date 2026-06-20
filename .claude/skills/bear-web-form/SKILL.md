---
user-invocable: true
name: bear-web-form
description: >-
  Policy for BEAR.Sunday apps that take form-style request input. First decide
  which of three architectures you are in — (A1) server-rendered form-object
  (Ray.WebFormModule + #[FormValidation] + Aura antiCsrf), (A2) server-rendered
  schema-decomposed (#[JsonSchema] string-typed + ray/csrf #[CsrfToken] + a
  getErrors() handler), or (B) client-rendered SPA/JSON (real JSON types +
  ray/csrf #[SameOrigin] + SameSite + client validation) — because typing,
  CSRF, error surfacing, and testing differ per architecture. Use when the user
  says "web form", "HTML form", "form validation", "フォーム", "確認画面",
  "Ray.WebFormModule", "#[FormValidation]", "#[JsonSchema]", "CSRF",
  "#[CsrfToken]", "#[SameOrigin]", "SameSite", "SPA form", "Vuelidate",
  "400 on submit", "empty field 400", "string vs int schema", or when a form
  submit returns an opaque 400. Distilled from BEAR.AppKata (A1),
  BEAR.Examples + MyVendor.Cms (A2), and Hpplus.Spur (B).
---

# BEAR Web Form

## Purpose

Codify how a BEAR.Sunday app should handle **form-style request input** — how it types the request, validates, protects against CSRF, surfaces errors to the user, and is tested. The recurring failure this prevents: a valid submit returns an **opaque `400`**, or CSRF is bolted on with the wrong mechanism, because the app mixed rules from architectures that do not share them.

This is a project-style policy distilled from real BEAR projects, **not** framework law. The first job is to place yourself in one of three architectures; the per-architecture rules follow.

## Decide the architecture first

The axis is **(1) where the form is rendered** — server or client — and **(2) whether the validation rules must be one source of truth shared across surfaces** (a browser/AJV client, a separate API).

| | **(A1) Server-rendered form-object** | **(A2) Server-rendered, schema-decomposed** | **(B) Client-rendered SPA / JSON** |
|---|---|---|---|
| Anchor | BEAR.AppKata | BEAR.Examples, MyVendor.Cms | Hpplus.Spur |
| Renders | server (Aura form object + Qiq) | server (Twig/Qiq) | client (Vue/React) |
| Validation | Aura.Filter in the Form class, `#[FormValidation]` | JSON Schema `#[JsonSchema(params:)]` | JSON Schema (server backstop) + client (e.g. Vuelidate) |
| Rules shared as one SSOT? | no — server-only | yes — one schema for server + client + API | yes — schema |
| Wire types | strings (form) | strings (form) → convert at boundary | real JSON types (string/bool/int) |
| CSRF | Aura antiCsrf token (bundled in the form) | ray/csrf `#[CsrfToken]` (synchronizer token) | ray/csrf `#[SameOrigin]` (Origin/Referer) + SameSite |
| Per-field errors | `useFieldMessage` + form re-render | `getErrors()` handler → `{errors}` body + HTML list | client validator + server backstop |
| Choose when | a purely server form, no other consumer of the rules | the same validation must also serve a JS/AJV client or an API | headless / SPA / mobile |

**The decision:** *does the same validation serve more than this one server form?* No → **A1** (fewest moving parts, cohesive). Yes → **A2** (one JSON Schema SSOT). Client renders → **B**. A1 and A2 are both current and valid — the choice is about SSOT sharing, **not** old vs new. "Type to the wire" and "ray/csrf" are common to all; the specifics differ below.

## Shared foundation (A1 & A2): an HTML form speaks only strings

- An HTML form submits **only strings**. An unfilled optional field arrives as `""` (still present), never `null`/absent.
- **JSON Schema has no type coercion** (the spec): `"type":"integer"` rejects both `"10"` and `""`. Validator-specific coercion (AJV `coerceTypes`, justinrainbow `CHECK_MODE_TYPE_CAST`) is non-standard, mutating, and per-validator — and even `TYPE_CAST` only coerces a *numeric* string (`"10"`→int), never `""`. Don't lean a shared schema on it.
- So a blank-able field typed `integer` fails two ways: the `integer` **schema** rejects the `""` at validation (→ `400`, the usual path); and if `""` ever reached an `int` **parameter** with no catching schema, PHP would `TypeError`. Either way blank is a latent failure, though blank is valid input.

The bug is structural: any `integer`/`number` field a form can leave blank is a latent `400`. (B does not have this problem — its client sends real JSON types.)

## (A1) Server-rendered form-object — Ray.WebFormModule + `#[FormValidation]`

Anchor: **BEAR.AppKata** (`bear/resource` 1.32, attribute-based). The Form class is the cohesive unit and is a **current** style, not a deprecated one.

- A `Form` class (`extends AbstractForm`) `init()`s: `setField()` (field + HTML render attribs), `$this->filter->validate(...)->is(...)` (Aura.Filter validation rules), and `useFieldMessage()` (the per-field message). CSRF is mixed in via an antiCsrf token (e.g. AppKata's `AntiCsrfSetter` trait injecting `Aura\Input\AntiCsrfInterface`) — a synchronizer token rendered into the form.
- The resource marks the mutating method `#[FormValidation]`; on failure the form **re-renders with per-field errors**, on success `$this->form->getValues()`.
- Validation is **Aura.Filter** — server-only, so it **cannot** be the SSOT for a JS client or an API. That is the deliberate trade for cohesion.
- **Use when** the form is purely server-rendered and nothing else consumes its rules. Don't reach for JSON Schema here just because it's newer — A1 is simpler when there's one consumer.

## (A2) Server-rendered, schema-decomposed — JSON Schema SSOT + ray/csrf + Twig/Qiq

Anchor: **BEAR.Examples, MyVendor.Cms**. Decompose the monolith into focused pieces so the validation can be one SSOT.

- **Type request schemas to the wire.** Form fields are `"string"` + string constraints (`pattern`/`enum`/`format`/`maxLength`); string enums (`["draft","valid"]`); `$ref` to shared `json_schema/` definitions. Reserve `"integer"`/typed for inputs whose wire is genuinely typed JSON. Note: `CHECK_MODE_TYPE_CAST` lets a numeric string pass an `integer` schema, so a *required, always-filled* integer survives (Cms types IDs this way) — but an **optional integer a form can leave blank** arrives as `""` and `400`s, so **string-type anything blank-able**.
- **Convert at the PHP boundary** after the schema passes (`$stock = ($stock === '' || $stock === null) ? null : (int) $stock;`) — never a coercive `int` param for a form field.
- **One schema, both ends.** The same `*.json` is the structural SSOT for the server, the client (AJV), and the API.
- **CSRF:** ray/csrf **`#[CsrfToken]`** (synchronizer token); render the hidden token field.
- **Surface errors via the framework hook.** `bear/resource` 1.33.0+ delivers a `JsonSchemaRequestException` carrying structured `$e->getErrors()` (`->byProperty()`, `->format()`). Bind a `JsonSchemaRequestExceptionHandlerInterface` in bootstrap, throw a domain `ValidationException` carrying the `field => messages` map, and have one `ExceptionStatusMapper` emit it as a JSON `{errors}` body *and* an HTML per-field list, so JSON and HTML share one shape. (Today BEAR.Examples/Cms build the handler + `ValidationException` + mapper but catch it *per-resource* in the Page layer for HTML re-render; surfacing the map **centrally** — so every HAL+JSON caller gets it too — is the completing step.)
- **Layer validation:** structural in JSON Schema; contextual/domain (uniqueness, FK, stock, state) in the resource/Becoming with typed exceptions, same error shape.
- **Use when** the validation must also serve a JS/AJV client or an API — one schema, no duplicate rule sets.

## (B) Client-rendered SPA / JSON API

Anchor: **Hpplus.Spur**. The client (Vue/React) renders and submits **JSON**, so the rules invert.

- **Type to the JSON wire.** Use real types — `boolean` for flags, `integer` for genuine integers (ids, page/limit). Most fields are still `string` because the data is string (ISO date-time, enum *codes*, ULIDs, slugs, URLs) — a convention, **not** the "forms send only strings" constraint. `$ref` shared definitions throughout; no `errorMessage` needed.
- **CSRF:** ray/csrf **`#[SameOrigin]`** (Origin/Referer validation) + **SameSite=Lax** cookies. This is a valid **token-less** defense for a JSON API: a cross-site `fetch` with `Content-Type: application/json` triggers a CORS preflight and the `Origin` header can't be spoofed. (ray/csrf's origin mode descends from Spur's own `#[CsrfProtection]` interceptor — its author's feedback shaped the package.)
- **Error surfacing:** the **client** validates for per-field UX (e.g. Vuelidate); the server's `#[JsonSchema]` is the **security backstop** — a generic `400`/message is acceptable because the client already showed the field detail. (Pre-1.33 servers like Spur only had a flattened message anyway.)
- **Use when** the surface is headless / SPA / mobile.

## Cross-cutting (all three)

- **Type to the wire, not the domain.** A1/A2: strings → convert at the boundary. B: real JSON types. Either way the schema models what the *actual client* sends.
- **CSRF is request-authenticity, not validation**, and is mandated by **cookie/ambient auth** (a browser auto-sends the session cookie). Use **ray/csrf**: `#[CsrfToken]` (token — server forms) vs `#[SameOrigin]` (origin — JSON/SPA); add **SameSite** always. A1 instead bundles Aura's antiCsrf token. A Bearer-token-only API needs no CSRF (the token isn't auto-sent).
- **Surface per-field errors:** A1 `useFieldMessage` + re-render; A2 `getErrors` handler → `{errors}`/list; B client validator. Never leave a bare `400` for a human.
- **Test the way the actual client submits.** A1/A2: a server form sends **only strings** and **all** rendered fields (incl empty optionals and the hidden CSRF token) — `submit()` must be string-only and round-trip the rendered form, plus a real-browser sample. B: a JSON body + the client's own rules.

  **Worked failure.** A product edit form left `stock` blank → `stock=''` → the `integer` schema (and `int $stock`) `400`ed. The HTML test passed because `submit()` sent a minimal `{productCode, productName, price02}` (no `stock`), masking it. Round-tripping the rendered form (which carries `stock=''`), or one browser click, would have caught it.

## Checklist

- [ ] Architecture chosen (A1 / A2 / B) by *rendering locus* + *is the validation a shared SSOT?* — not by fashion.
- [ ] **A1**: validation + per-field messages (`useFieldMessage`) + antiCsrf token live in the Form class; `#[FormValidation]` on the method; failure re-renders the form.
- [ ] **A2**: form-submitted fields `string`-typed (blank-able ⇒ never bare `integer`); convert at the boundary; one JSON Schema SSOT; `#[CsrfToken]`; a bound `JsonSchemaRequestExceptionHandler` emits `{errors}` + HTML list.
- [ ] **B**: schema typed to the JSON wire; `#[SameOrigin]` + SameSite; client validation present; server schema is the backstop.
- [ ] CSRF present and matched to the surface (token vs origin); SameSite set.
- [ ] Errors reach the user per-field (re-render / `{errors}` / client) — never a bare `400`.
- [ ] Tests submit the way the real client does (A1/A2 string-only + round-trip the rendered form incl. CSRF token + a browser sample; B JSON + client rules). A guard test keeps form-route schemas from drifting back to a blank-able `integer`.

## References

- **BEAR.AppKata** (A1) — `Ray.WebFormModule` + `#[FormValidation]` (attribute-migrated) + Aura antiCsrf form-object; the cohesive server-form style.
- **BEAR.Examples / MyVendor.Cms** (A2) — `#[JsonSchema]` SSOT, `JsonSchemaRequestExceptionHandlerInterface` → `ValidationException` → one `ExceptionStatusMapper` for `{errors}` body + HTML list; MyVendor.Cms attaches schema `errorMessage` (ajv-errors), Examples/Spur keep messages in code.
- **Hpplus.Spur** (B) — production SPA + JSON API: JSON-typed schemas, `#[SameOrigin]` (Origin/Referer) + SameSite CSRF, Vuelidate client validation, server schema backstop.
- [BEAR.Sunday validation manual](https://bearsunday.github.io/manuals/1.0/en/validation.html) — `#[JsonSchema]`, request/response exception split, `getErrors()` (`byProperty()`/`format()`), default `ThrowableHandler` 400.
- BEAR.Resource 1.33.0 — the `JsonSchemaRequestException` (4xx) / `JsonSchemaResponseException` (5xx) split ([#369](https://github.com/bearsunday/BEAR.Resource/pull/369)) and the request-exception handler hook ([#370](https://github.com/bearsunday/BEAR.Resource/pull/370)); the structured-failure source (`getErrors()`) for A2.
- [Ray.Csrf](https://github.com/ray-di/Ray.Csrf) — `#[CsrfToken]` (token, A2) and `#[SameOrigin]` (origin, B); the standard CSRF package, shaped by the Spur author's feedback.
- [Ray.WebFormModule](https://github.com/ray-di/Ray.WebFormModule) — the A1 form-object module (`AbstractForm`, `#[FormValidation]`, Aura antiCsrf).
- Related skills: `bear-hypermedia` (JSON-API `#[Link]`/`#[Embed]`), `bear-clean-style` (resource conventions, JsonSchema in/out, Input DTO).
