---
user-invocable: true
name: bear-refactor
description: BEAR.Sundayプロジェクトのリファクタリングツール。ResourceObject→static変換、Named→Qualifier変換を提供。
---

# BEAR.Sunday リファクタリングスキル

## 概要

BEAR.Sundayプロジェクトのコードをモダンな記法に変換するリファクタリングツール。以下の2つの操作を提供。

## 1. ResourceObject → static 一括変換

BEAR.Sundayリソースクラスの戻り値型 `ResourceObject` を `static` に変換する。

### 変換対象

```php
// 変換前
public function onGet(): ResourceObject
public function onPost(string $name): ResourceObject
public function onPut(int $id): ResourceObject
public function onPatch(int $id): ResourceObject
public function onDelete(int $id): ResourceObject

// 変換後
public function onGet(): static
public function onPost(string $name): static
public function onPut(int $id): static
public function onPatch(int $id): static
public function onDelete(int $id): static
```

### 実行手順

#### 1. 対象ファイルの確認

```bash
grep -r "): ResourceObject" src/Resource --include="*.php" | wc -l
```

#### 2. 一括変換の実行

```bash
find src/Resource -name "*.php" -exec sed -i '' 's/): ResourceObject/): static/g' {} +
```

#### 3. 不要なuse文の削除

変換後、`use BEAR\Resource\ResourceObject;` が戻り値型のためだけに使われていた場合は削除する。

```bash
# 確認（ResourceObjectが他で使われていないファイル）
grep -l "use BEAR\\\\Resource\\\\ResourceObject;" src/Resource --include="*.php" | while read f; do
  if ! grep -q "extends ResourceObject" "$f"; then
    echo "$f"
  fi
done
```

#### 4. コーディング規約の適用

```bash
composer cs-fix
```

#### 5. テストの実行

```bash
composer test
```

### 注意事項

- `extends ResourceObject` は変更しない（クラス継承は維持）
- テストが通ることを確認してからコミット
- 大量の変更になるため、専用ブランチで作業推奨

## 2. Named → Qualifier 変換

`#[Named('string_key')]` による文字列ベースのDI識別を、型安全な `#[QualifierClass]` に変換する。

### 変換前後

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

### 手順

#### 1. Named文字列の使用箇所を検索

```bash
grep -r "#\[Named(" src/ --include="*.php" | grep -v "^Binary"
```

#### 2. 使用されているキーを一覧化

```bash
grep -roh "#\[Named(['\"][^'\"]*['\"])" src/ --include="*.php" | sort | uniq -c | sort -rn
```

#### 3. Qualifier属性クラスを作成

各Namedキーに対してQualifier属性を作成:

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

#### 4. 使用箇所を置換

```php
// Before
#[Named('api_endpoint')]

// After
#[ApiEndpoint]
```

#### 5. NamedModuleの設定を更新

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

#### 6. コード整形

```bash
composer cs-fix
```

use文の追加・削除・並び替えは自動で行われる。

### Qualifier属性テンプレート

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

### グループ化の指針

関連するQualifierは同じディレクトリにまとめる:

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

### 変換対象の判断

| パターン | 変換すべきか | 理由 |
|----------|--------------|------|
| 環境依存値 | ✅ | 差し替えが必要 |
| 設定値 | ✅ | テスト時に変更したい |
| ドメイン定数 | ❌ → enum | 差し替え不要 |
| 技術仕様 | ❌ → const | コードと不可分 |

### 変換しない方がよいケース

- 1-2箇所でしか使われていないNamed
- 近い将来削除予定の機能
- ドメイン不変値（enum化すべき）

### チェックリスト

- [ ] Named文字列の一覧を作成
- [ ] 各キーに対してQualifier属性クラスを作成
- [ ] `#[Named('key')]` を `#[QualifierClass]` に置換
- [ ] NamedModuleの設定を更新
- [ ] `composer cs-fix` でコード整形
- [ ] テスト実行で動作確認
