# Input shape and validation

Do not apply DTOs everywhere. Choose by the shape's needs, not by seeking pattern coverage.

## Decision rule

**Stay scalar** when:

- the parameter list is short and flat;
- every parameter maps 1:1 to a JSON Schema property;
- there is no nested structure or tri-state/partial-update semantic.

`#[JsonSchema(params: '<entity>_<verb>.json')]` validates the named arguments and the method signature *is* the contract. This is the default.

**Use an Input DTO** when any of:

- parameter count crosses the readability threshold (~7+ fields);
- a field has tri-state or partial-update semantics (`null` / `[]` / non-empty list where omitted ≠ explicit empty);
- the fields cohere as a named struct meaningful beyond the resource (e.g. an OAuth `code`/`state` exchange);
- upload fields should become FileUpload value objects rather than ad-hoc `$_FILES` / array shapes.

Define `<Vendor>\<Project>\Input\<Action>Input` as `final readonly class` with `#[Input]` on each constructor parameter:

```php
final readonly class ArticleCreateInput
{
    public function __construct(
        #[Input] public string $slug,
        #[Input] public string $title,
        // …
    ) {}
}
```

Type the resource argument as `#[Input] <Dto>`. BEAR.Resource's `InputParam` materialises the object from the flat request array before the method runs.

## Where DTOs do not belong

DTOs belong at the Resource boundary. Do not pass DTOs through to Command interfaces unless the Resource-to-Command path is truly a transparent passthrough. Resource code should unpack named scalar arguments when it also orchestrates side effects such as tag syncing or natural-key re-select (see [data-contract.md](data-contract.md)).

## Typed-array DTO pitfall

If validation runs *after* DTO hydration, a typed `array` property may throw `TypeError` before JSON Schema sees invalid input. For array-shaped request fields:

1. Accept `mixed` in the DTO constructor.
2. Coalesce omitted values intentionally — `mixed` always allows null in `Ray\InputQuery`'s default-value resolution. An omitted `tagIds` arrives as `null`, not as the constructor's declared default. Coalesce to `[]` for create-style, `null` for tri-state update.
3. Perform the minimal `is_array` guard. Throw `BEAR\Resource\Exception\ParameterException` (maps to 400) for bad shapes.
4. Let JSON Schema's `items` / `minimum` keep doing per-element validation.

## Application validation with injected services

Use parameter-level `#[Validate(Service::class, 'method')]` for stateful invariants that depend on injected collaborators (uniqueness checks, cross-entity rules):

```php
public function onPost(
    #[Input, Validate(ArticleValidator::class, 'create')] ArticleCreateInput $input,
): static
```

The validator method receives the already-materialised argument and returns `ValidationErrors`; it must not throw for expected violations. `ValidationInterceptor` merges all such errors and raises `ValidationFailedException` (422) only after the validation pass. Page resources catch the same exception and re-render the form with the field messages.

Keep JSON Schema responsible for shape/range; keep `#[Validate]` for invariants the schema cannot know.

## Page resources stay scalar (provisional)

Even when a Page resource crosses the 7-field threshold or has tri-state form input, keep the parameter list scalar for now. ApiDoc does not yet consume the phpdoc input-param expansion, so a Page Input DTO would not surface in the documentation. Treat ApiDoc gaining input-param support as the migration trigger; until then, scalar wins for Page resources.
