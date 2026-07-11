---
user-invocable: true
name: bear-refactor
description: Refactoring tool for BEAR.Sunday projects. Provides ResourceObject to static conversion, Named to Qualifier conversion, and trivial getter removal. Use when user says "戻り値をstaticに", "NamedをQualifierに", "getterを外す", "modernize return types", "type-safe DI", or asks to refactor resource return types, value objects, or DI bindings.
---

# BEAR.Sunday Refactoring Skill

## Overview

A refactoring tool that converts BEAR.Sunday project code to modern syntax. Provides the following operations.

## 1. ResourceObject to static Bulk Conversion

Convert the return type `ResourceObject` to `static` in BEAR.Sunday resource classes.

### Conversion Targets

```php
// Before
public function onGet(): ResourceObject
public function onPost(string $name): ResourceObject
public function onPut(int $id): ResourceObject
public function onPatch(int $id): ResourceObject
public function onDelete(int $id): ResourceObject

// After
public function onGet(): static
public function onPost(string $name): static
public function onPut(int $id): static
public function onPatch(int $id): static
public function onDelete(int $id): static
```

### Steps

#### 1. Check Target Files

```bash
grep -r "): ResourceObject" src/Resource --include="*.php" | wc -l
```

#### 2. Run Bulk Conversion

The in-place edit syntax differs by platform. Use the form matching your OS:

```bash
# macOS (BSD sed)
find src/Resource -name "*.php" -exec sed -i '' 's/): ResourceObject/): static/g' {} +

# Linux (GNU sed)
find src/Resource -name "*.php" -exec sed -i 's/): ResourceObject/): static/g' {} +
```

Or use a single portable command with perl:

```bash
find src/Resource -name "*.php" -exec perl -i -pe 's/\): ResourceObject/): static/g' {} +
```

#### 3. Remove Unnecessary use Statements

After conversion, remove `use BEAR\Resource\ResourceObject;` if it was only used for the return type.

```bash
# Check (files where ResourceObject is not used elsewhere)
grep -rl "use BEAR\\\\Resource\\\\ResourceObject;" src/Resource --include="*.php" | while read f; do
  if ! grep -q "extends ResourceObject" "$f"; then
    echo "$f"
  fi
done
```

#### 4. Apply Coding Standards

```bash
composer cs-fix
```

#### 5. Run Tests

```bash
composer test
```

### Notes

- Do not change `extends ResourceObject` (class inheritance must be preserved)
- Confirm tests pass before committing
- Since this produces a large number of changes, working on a dedicated branch is recommended

## 2. Named to Qualifier Conversion

Convert string-based DI identification with `#[Named('string_key')]` to type-safe `#[QualifierClass]`.

### Before and After

```php
// Before: String-based
public function __construct(
    #[Named('api_endpoint')] private readonly string $endpoint,
    #[Named('max_retry')] private readonly int $maxRetry,
) {}

// After: Qualifier attribute-based
public function __construct(
    #[ApiEndpoint] private readonly string $endpoint,
    #[MaxRetry] private readonly int $maxRetry,
) {}
```

### Steps

#### 1. Search for Named String Usage

```bash
grep -r "#\[Named(" src/ --include="*.php" | grep -v "^Binary"
```

#### 2. List All Keys in Use

```bash
grep -roh "#\[Named(['\"][^'\"]*['\"])" src/ --include="*.php" | sort | uniq -c | sort -rn
```

#### 3. Create Qualifier Attribute Classes

Create a Qualifier attribute for each Named key:

```php
<?php

declare(strict_types=1);

namespace {Project}\Annotation;

use Attribute;
use Ray\Di\Di\Qualifier;

/**
 * {Description}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
#[Qualifier]
final class {ClassName}
{
}
```

**Naming Convention:**
- `api_endpoint` -> `ApiEndpoint`
- `max_retry_count` -> `MaxRetryCount`
- Convert snake_case to PascalCase

#### 4. Replace Usage Sites

```php
// Before
#[Named('api_endpoint')]

// After
#[ApiEndpoint]
```

#### 5. Update NamedModule Configuration

```php
// Before
new NamedModule([
    'api_endpoint' => 'https://api.example.com',
    'max_retry' => 3,
]);

// After
use {Project}\Annotation\ApiEndpoint;
use {Project}\Annotation\MaxRetry;

new NamedModule([
    ApiEndpoint::class => 'https://api.example.com',
    MaxRetry::class => 3,
]);
```

#### 6. Format Code

```bash
composer cs-fix
```

Use statement additions, removals, and reordering are handled automatically.

### Grouping Guidelines

Group related Qualifiers in the same directory:

```text
src/Annotation/
├── Api/
│   ├── ApiEndpoint.php
│   ├── ApiTimeout.php
│   └── ApiRetryCount.php
├── Image/
│   ├── ThumbnailSize.php
│   └── MaxImageWidth.php
└── Cache/
    ├── CacheTtl.php
    └── CachePrefix.php
```

### Conversion Decision Guide

| Pattern | Should Convert? | Reason |
|---------|-----------------|--------|
| Environment-dependent values | ✅ | Needs to be swappable |
| Configuration values | ✅ | Want to change during testing |
| Domain constants | ❌ -> enum | No need to swap |
| Technical specs | ❌ -> const | Inseparable from code |

### Cases Where Conversion Is Not Recommended

- Named values used in only 1-2 places
- Features planned for removal in the near future
- Domain invariant values (should be converted to enum)

## 3. Remove Trivial Getter Methods

In BEAR.Sunday code, a method should represent behaviour or a boundary operation.
A method that only returns a stored value is not behaviour:

```php
// Avoid
public function adminId(): string|null
{
    return $this->adminId;
}
```

Prefer a readonly value/context object with public properties:

```php
abstract readonly class AdminSession
{
    /** @param non-empty-string|null $adminId */
    public function __construct(
        public string|null $adminId,
    ) {
    }
}
```

Use methods only when there is behaviour or a framework contract:

- ✅ `isValid($token)`, `assertUnique()`, `changedCount()` when it computes, checks, throws, or expresses a domain operation.
- ✅ `ProviderInterface::get()` because the interface requires it.
- ❌ `exists()`, `value()`, `adminId()`, `customerId()`, `getToken()` when they just return a field.

### PHP Version Note

If the project supports PHP 8.3, do not model this as an interface property.
Use a concrete or abstract readonly value/context class instead. PHP interface
properties are a PHP 8.4+ feature and are not safe for PHP 8.3 projects.

### Detection

Before finishing a refactor, scan for trivial getters:

```bash
python3 - <<'PY'
import re
from pathlib import Path

pat = re.compile(
    r'public function (\w+)\s*\([^)]*\)\s*:\s*[^{]+\{\s*return \$this->(\w+);\s*\}',
    re.S,
)

for base in ['src', 'tests/Fake']:
    root = Path(base)
    if not root.exists():
        continue
    for file in root.rglob('*.php'):
        text = file.read_text()
        for match in pat.finditer(text):
            print(f'{file}: {match.group(1)} -> {match.group(2)}')
PY
```

Any output needs an explicit reason to remain. Acceptable reasons are:

- It implements a third-party/framework interface.
- It performs validation, lazy creation, transformation, I/O, or throws.
- It preserves a public API that cannot be changed in the current task.
