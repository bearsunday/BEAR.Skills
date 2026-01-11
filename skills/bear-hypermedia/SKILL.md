---
user-invocable: true
name: bear-hypermedia
description: リソースクラスに#[Link]を追加し、HyperMediaテストでユースケースを表現する。API設計改善時に使用。
---

# BEAR.Sunday ハイパーメディア実装スキル

## 目的

1. リソースクラスに `#[Link]` を追加して遷移可能なアクションを宣言
2. HyperMediaテストでユースケース（ワークフロー）を表現

## 手順

### 1. リソースクラスの分析

リソースを読み、可能なアクションを特定:

- 編集できる → `rel: 'edit'`
- 削除できる → `rel: 'delete'`
- 詳細がある → `rel: 'item'`
- 一覧に戻れる → `rel: 'collection'`
- 次/前がある → `rel: 'next'` / `rel: 'prev'`

### 2. #[Link]の追加

```php
use BEAR\Resource\Annotation\Link;

#[Link(rel: 'edit', href: '/article/{id}/edit')]
#[Link(rel: 'delete', href: '/article/{id}', method: 'delete')]
#[Link(rel: 'comments', href: '/article/{id}/comments')]
public function onGet(int $id): static
```

### 3. HyperMediaテストの実装

ユースケースをテストで表現:

```php
/**
 * 記事編集ワークフロー
 *
 * [記事一覧] --item--> [記事詳細] --edit--> [編集] --update--> [記事詳細]
 */
public function testArticleEditWorkflow(): void
{
    // 記事一覧を取得
    $articles = $this->resource->get('app://self/articles');

    // 最初の記事の詳細へ遷移
    $article = $this->resource->href('item', $articles);
    $this->assertSame(200, $article->code);

    // 編集へ遷移
    $edit = $this->resource->href('edit', $article);
    $this->assertSame(200, $edit->code);

    // 更新を実行
    $updated = $this->resource->href('update', $edit, ['title' => 'New Title']);
    $this->assertSame(200, $updated->code);
}
```

## ユースケース例

### 記事管理

```text
[記事一覧] --item--> [記事詳細] --edit--> [編集フォーム] --update--> [記事詳細]
                         |
                         +--delete--> [記事一覧]
                         |
                         +--comments--> [コメント一覧]
```

### ユーザー登録

```text
[トップ] --signup--> [登録フォーム] --create--> [確認] --verify--> [完了]
```

## リソースクラスからALPS生成

リソースクラスを分析してALPSプロファイルを生成する。

### マッピング

| BEAR.Sunday | ALPS |
|-------------|------|
| リソースクラス | State（状態） |
| `#[Link(rel, href)]` | Transition（遷移） |
| `#[Embed(rel, src)]` | 埋め込み状態 |
| メソッド引数 | Semantic descriptor |
| onGet | safe transition |
| onPost | unsafe transition |
| onPut/onDelete | idempotent transition |

### 生成手順

1. リソースクラスを読む
2. クラス名 → State ID
3. `#[Link]` → Transition（relからgo/do判定）
4. `#[Embed]` → 埋め込み参照
5. 引数 → Semantic descriptor
6. ALPSスキルで生成・検証

### 例: ArticleリソースからALPS

```php
// Resource
#[Link(rel: 'edit', href: '/article/{id}/edit')]
#[Link(rel: 'delete', href: '/article/{id}', method: 'delete')]
#[Embed(rel: 'author', src: 'app://self/user{?id}')]
#[Embed(rel: 'comments', src: 'app://self/article/{id}/comments')]
public function onGet(int $id): static
```

↓ 生成

```json
{
  "id": "ArticleDetail",
  "title": "Article Detail",
  "descriptor": [
    {"href": "#articleId"},
    {"href": "#Author"},
    {"href": "#Comments"},
    {"href": "#goEdit"},
    {"href": "#doDelete"}
  ]
}
```

### ALPSスキルとの連携

生成後は `/alps` スキルで:
- 検証: `asd --validate profile.json`
- 図生成: `asd profile.json`
- 改善提案を取得

## 参考資料

- [BEAR.Sunday リソース](https://bearsunday.github.io/manuals/1.0/ja/resource.html)
- [BEAR.Sunday テスト](https://bearsunday.github.io/manuals/1.0/ja/test.html)
- [ALPS Specification](http://alps.io/spec/)
