---
name: named-to-qualifier
description: Named文字列をQualifier属性に変換する。#[Named('string')]を型安全な#[QualifierClass]に置き換え、NamedModuleの設定も更新する。
---

# Named to Qualifier 変換スキル

## 概要

`#[Named('string_key')]` による文字列ベースのDI識別を、型安全な `#[QualifierClass]` に変換する。

## 変換前後

```php
// Before: 文字列ベース
public function __construct(
    #[Named('api_endpoint')] private readonly string $endpoint,
    #[Named('max_retry')] private readonly int $maxRetry,
) {}

// After: Qualifier属性ベース
public function __construct(
    #[ApiEndpoint] private readonly string $endpoint,
    #[MaxRetry] private readonly int $maxRetry,
) {}
```

## 手順

### 1. Named文字列の使用箇所を検索

```bash
grep -r "#\[Named(" src/ --include="*.php" | grep -v "^Binary"
```

### 2. 使用されているキーを一覧化

```bash
grep -roh "#\[Named(['\"][^'\"]*['\"])" src/ --include="*.php" | sort | uniq -c | sort -rn
```

### 3. Qualifier属性クラスを作成

各Namedキーに対してQualifier属性を作成：

```php
<?php

declare(strict_types=1);

namespace {Project}\Annotation;

use Attribute;
use Ray\Di\Di\Qualifier;

#[Attribute(Attribute::TARGET_PARAMETER)]
#[Qualifier]
final class ApiEndpoint
{
}
```

**命名規則:**
- `api_endpoint` → `ApiEndpoint`
- `max_retry_count` → `MaxRetryCount`
- スネークケースをパスカルケースに変換

### 4. 使用箇所を置換

```php
// Before
#[Named('api_endpoint')]

// After
#[ApiEndpoint]
```

### 5. NamedModuleの設定を更新

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

### 6. コード整形

```bash
composer cs-fix
```

use文の追加・削除・並び替えは自動で行われる。

## Qualifier属性テンプレート

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

## グループ化の指針

関連するQualifierは同じディレクトリにまとめる：

```
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

## 変換対象の判断

| パターン | 変換すべきか | 理由 |
|----------|--------------|------|
| 環境依存値 | ✅ | 差し替えが必要 |
| 設定値 | ✅ | テスト時に変更したい |
| ドメイン定数 | ❌ → enum | 差し替え不要 |
| 技術仕様 | ❌ → const | コードと不可分 |

## 変換しない方がよいケース

- 1-2箇所でしか使われていないNamed
- 近い将来削除予定の機能
- ドメイン不変値（enum化すべき）

## チェックリスト

- [ ] Named文字列の一覧を作成
- [ ] 各キーに対してQualifier属性クラスを作成
- [ ] `#[Named('key')]` を `#[QualifierClass]` に置換
- [ ] NamedModuleの設定を更新
- [ ] `composer cs-fix` でコード整形
- [ ] テスト実行で動作確認
