---
name: resource-documenter
description: リソースクラスにPHPDocコメントを自動生成する。クラス名・メソッド・パラメータからREST意味論を推測し、確信度付きのコメントを付与。
---

# リソースドキュメント自動生成スキル

## 概要

リソースクラスのPHPDocコメントを自動生成する。クラス名・HTTPメソッド・パラメータから意図を推測し、確信度付きでコメントを付与。

## 実行フロー

### Step 1: @todoマーカーの確認

コメント生成前に以下を説明し、選択を求める：

---

**@todoマーカーを付けますか？**

`@todo 要確認(確信度X):` マーカーを付けると：

- **IDEのTODOリストに表示** → 未レビューのコメントを一覧で確認可能
- **確信度が明示される** → 低確信度のコメントを優先的にレビュー
- **レビュー漏れを防止** → マーカーが残っている = 未確認

マーカーを付けない場合：

- コメントのみ生成される
- 自分で全て確認する必要がある
- 確信度の高いリソースに推奨

**選択肢:**
- **はい**: 全コメントに `@todo 要確認(確信度X):` を付与
- **いいえ**: コメントのみ生成

---

### Step 2: サンプル実行

1ファイルを選んでコメントを生成し、結果を表示：

---

**サンプル: src/Resource/App/Article.php**

```php
/**
 * @todo 要確認(確信度高): 記事リソース
 *
 * 記事の取得・作成・更新・削除を提供。
 */
class Article extends ResourceObject
{
    /**
     * @todo 要確認(確信度高): 記事を取得
     *
     * @param int $id 記事ID
     * @return static
     */
    public function onGet(int $id): static
```

**このスタイルで続けますか？**

- **はい**: 残りのリソースファイルにも適用
- **修正が必要**: スタイルを調整してから続行
- **中止**: このファイルのみで終了

---

### Step 3: 全体適用

「はい」の場合、残りのリソースファイルに順次適用。

## 生成例

### @todoマーカーあり

```php
<?php

declare(strict_types=1);

namespace App\Resource\App;

use BEAR\Resource\ResourceObject;

/**
 * @todo 要確認(確信度高): 記事リソース
 *
 * 記事の取得・作成・更新・削除を提供。
 */
class Article extends ResourceObject
{
    /**
     * @todo 要確認(確信度高): 記事を取得
     *
     * @param int $id 記事ID
     * @return static
     */
    public function onGet(int $id): static
    {
        // ...
    }

    /**
     * @todo 要確認(確信度高): 記事を作成
     *
     * @param string $title タイトル
     * @param string $body 本文
     * @return static 201 Created
     */
    public function onPost(string $title, string $body): static
    {
        // ...
    }

    /**
     * @todo 要確認(確信度高): 記事を削除
     *
     * @param int $id 記事ID
     * @return static 204 No Content
     */
    public function onDelete(int $id): static
    {
        // ...
    }
}
```

## 確信度の判定基準

### 確信度: 高

- クラス名が明確な名詞
  - `Article`, `User`, `Order`, `Product`
- 標準的なCRUDパターン
  - `onGet($id)` → 1件取得
  - `onGet()` → 一覧取得
  - `onPost(...)` → 作成
  - `onPut($id, ...)` → 更新
  - `onDelete($id)` → 削除
- パラメータ名が自己説明的
  - `$id`, `$title`, `$body`, `$email`

### 確信度: 中

- クラス名がドメイン固有語
  - `Hanagumi`, `Flagshop`
- 複合的な操作
  - `onPost` で更新も行う
- パラメータが多い（5個以上）

### 確信度: 低

- クラス名が略語
  - `Art`, `Usr`, `Ord`
- 非標準のメソッドパターン
  - `onGet` で副作用がある
- パラメータの意図が不明
  - `$data`, `$params`, `$options`
- 複雑なビジネスロジック

## 推測パターン

### クラス名から

| パターン | 推測 |
|----------|------|
| `Article`, `Post`, `Blog` | 記事/投稿リソース |
| `User`, `Member`, `Account` | ユーザーリソース |
| `Order`, `Purchase` | 注文リソース |
| `Product`, `Item` | 商品リソース |
| `Category`, `Tag` | 分類リソース |
| `Comment`, `Review` | コメント/レビューリソース |
| `*List`, `*Index` | 一覧リソース |
| `*Detail` | 詳細リソース |

### メソッドから

| メソッド | パラメータ | 推測 |
|----------|------------|------|
| `onGet` | `int $id` | 1件取得 |
| `onGet` | なし or ページング | 一覧取得 |
| `onGet` | 検索条件 | 検索/フィルタ |
| `onPost` | 作成データ | 新規作成 (201) |
| `onPut` | `$id` + データ | 全体更新 |
| `onPatch` | `$id` + 部分データ | 部分更新 |
| `onDelete` | `int $id` | 削除 (204) |

### パラメータ名から

| パラメータ | 推測 |
|------------|------|
| `$id`, `$articleId` | リソース識別子 |
| `$title`, `$name` | 名称 |
| `$body`, `$content` | 本文 |
| `$email`, `$phone` | 連絡先 |
| `$page`, `$limit`, `$offset` | ページネーション |
| `$sort`, `$order` | ソート |
| `$q`, `$keyword`, `$search` | 検索キーワード |

### 属性から

| 属性 | 追加情報 |
|------|----------|
| `#[Embed]` | 埋め込みリソースあり |
| `#[Link]` | 関連リソースへのリンク |
| `#[Cacheable]` | キャッシュ可能 |
| `#[JsonSchema]` | 入力バリデーションあり |

## レビュー後の作業

```bash
# 未レビューのリソースを検索
grep -r "@todo 要確認" src/Resource/

# 確信度低のみ検索
grep -r "確信度低" src/Resource/
```

レビュー完了後、`@todo 要確認(確信度X): ` 部分を削除。

## 生成しないケース

- 既にPHPDocコメントがあるクラス/メソッド（上書きしない）
- 抽象クラス、トレイト
- テスト用のリソース
