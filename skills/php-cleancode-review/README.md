# php-cleancode-review

PHP コードの品質を評価するスキル。

## 概要

PHPMD メトリクスと一般的なプログラミング基準でコードレビューを行います:

- Cyclomatic Complexity / NPath Complexity
- 命名規則（変数、メソッド、ブール値）
- コード構造（ネスト、早期リターン）
- エラーハンドリング
- 型安全性

## 使用方法

```
/php-cleancode-review
```

## 評価項目

| カテゴリ | 内容 |
|----------|------|
| PHPMDメトリクス | CC, NPath, パラメータ数, フィールド数 |
| 命名規則 | 長さ、曖昧さ、ブール接頭辞 |
| コード構造 | ネスト深度、メソッド長、else削減 |
| エラーハンドリング | 例外設計、catchブロック |
| 型安全性 | 型指定、mixed使用 |

## 出力例

```
## ファイル評価: src/Service/ArticleService.php

### PHPMDメトリクス

| メトリクス | 値 | 評価 |
|-----------|-----|------|
| Cyclomatic Complexity | 8 | A |
| NPath Complexity | 120 | A |

### 総合評価: A
```

## 困った人のコード図鑑

スキルには以下のアンチパターン検出が含まれます:

- God Class（神クラス）
- コピペ戦士
- Primitive Obsession
- Static Cola（静的メソッド中毒）
- Service Locator
- Mixed脳

## 関連スキル

- [bear-cleancode-review](../bear-cleancode-review/) - BEAR.Sunday固有の評価基準
