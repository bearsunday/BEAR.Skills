# bear-cleancode-review

BEAR.Sunday プロジェクトのコード品質を評価するスキル。

## 概要

BEAR.Sunday 固有の評価基準でコードレビューを行います:

- リソース設計（Embed、body代入、責務分離）
- 依存性注入（Ray.Di パターン）
- HTTPステータスコード
- JsonSchema バリデーション
- AOP（インターセプター）

一般的な PHP 評価基準については `php-cleancode-review` スキルを使用します。

## 使用方法

```
/bear-cleancode-review
```

## 評価項目

| カテゴリ | 内容 |
|----------|------|
| リソース設計 | Embed使用、body代入パターン、責務分離 |
| 依存性注入 | コンストラクタ注入、インターフェース依存 |
| HTTPセマンティクス | ステータスコード、Locationヘッダー |
| バリデーション | JsonSchema による宣言的検証 |
| 横断的関心事 | インターセプターでの分離 |

## 出力例

```
## ファイル評価: src/Resource/App/Article.php

### BEAR.Sunday固有評価

| 項目 | 評価 | コメント |
|------|------|----------|
| リソース設計 | A | Embed適切、単一責任 |
| 依存性注入 | A | コンストラクタ注入のみ |
| HTTPステータス | OK | 201 + Location |

### 総合評価: A
```

## 関連スキル

- [php-cleancode-review](../php-cleancode-review/) - 一般的なPHPコード品質評価
