---
user-invocable: true
name: fix-return-static
description: ResourceクラスのResourceObject戻り値型をstaticに一括変換する。BEAR.Sundayのモダンな記法への移行時に使用。
---

# ResourceObject → static 一括変換スキル

## 概要

BEAR.Sundayリソースクラスの戻り値型 `ResourceObject` を `static` に変換する。

## 変換対象

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

## 実行手順

### 1. 対象ファイルの確認

```bash
grep -r "): ResourceObject" src/Resource --include="*.php" | wc -l
```

### 2. 一括変換の実行

```bash
find src/Resource -name "*.php" -exec sed -i '' 's/): ResourceObject/): static/g' {} +
```

### 3. 不要なuse文の削除

変換後、`use BEAR\Resource\ResourceObject;` が戻り値型のためだけに使われていた場合は削除する。

```bash
# 確認（ResourceObjectが他で使われていないファイル）
grep -l "use BEAR\\\\Resource\\\\ResourceObject;" src/Resource --include="*.php" | while read f; do
  if ! grep -q "extends ResourceObject" "$f"; then
    echo "$f"
  fi
done
```

### 4. コーディング規約の適用

```bash
composer cs-fix
```

### 5. テストの実行

```bash
composer test
```

## 注意事項

- `extends ResourceObject` は変更しない（クラス継承は維持）
- テストが通ることを確認してからコミット
- 大量の変更になるため、専用ブランチで作業推奨
