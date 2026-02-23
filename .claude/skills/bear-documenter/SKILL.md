---
user-invocable: true
name: bear-documenter
description: BEAR.Sundayプロジェクトの定数クラス・リソースクラスにPHPDocコメントを自動生成する。名前・値・文脈から意図を推測し、確信度付きのコメントを付与。
---

# ドキュメント自動生成スキル

## 概要

定数クラスおよびリソースクラスのPHPDocコメントを自動生成する。名前・値・文脈から意図を推測し、確信度付きでコメントを付与。

## 共通フロー

### @todoマーカーの確認

コメント生成前に以下を説明し、選択を求める:

---

**@todoマーカーを付けますか？**

`@todo 要確認(確信度X):` マーカーを付けると:

- **IDEのTODOリストに表示** → 未レビューのコメントを一覧で確認可能
- **確信度が明示される** → 低確信度のコメントを優先的にレビュー
- **レビュー漏れを防止** → マーカーが残っている = 未確認

マーカーを付けない場合:

- コメントのみ生成される
- 自分で全て確認する必要がある
- 確信度の高い対象に推奨

**選択肢:**
- **はい**: 全コメントに `@todo 要確認(確信度X):` を付与
- **いいえ**: コメントのみ生成

---

### サンプル実行

1ファイルを選んでコメントを生成し、結果を表示:

**このスタイルで続けますか？**

- **はい**: 残りのファイルにも適用
- **修正が必要**: スタイルを調整してから続行
- **中止**: このファイルのみで終了

### レビュー後の作業

レビュー完了後、`@todo 要確認(確信度X):` 部分を削除してコメントのみ残す。

### 生成しないケース

- 既にPHPDocコメントがある対象（上書きしない）
- 明らかにdeprecatedなコード
- テスト用のコード

## 1. 定数ドキュメント

定数クラスのPHPDocコメントを自動生成する。定数名・値・文脈から意図を推測。

### 生成例

#### @todoマーカーあり

```php
<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * @todo 要確認(確信度高): HTMLメタタグ用の定数
 *
 * SEO/OGP用のtitle、descriptionテンプレートを定義。
 */
final class MetaTag
{
    /** @todo 要確認(確信度高): 全ページ共通のタイトル接尾辞 */
    public const TITLE_DEFAULT_SUFFIX = '｜Web eclat（ウェブエクラ）';

    /** @todo 要確認(確信度高): OGP (Open Graph Protocol) 用プロパティ識別子 */
    public const PROPERTY_OGP = 'ogp';

    /** @todo 要確認(確信度低): 集計閾値？用途要調査 */
    public const THRESHOLD = 100;
}
```

#### @todoマーカーなし

```php
/**
 * HTMLメタタグ用の定数
 *
 * SEO/OGP用のtitle、descriptionテンプレートを定義。
 */
final class MetaTag
{
    /** 全ページ共通のタイトル接尾辞 */
    public const TITLE_DEFAULT_SUFFIX = '｜Web eclat（ウェブエクラ）';
}
```

### 確信度の判定基準（定数）

#### 確信度: 高

- 定数名が明確で意図が読み取れる
  - `TITLE_PREFIX_*`, `DESCRIPTION_*`, `MAX_*_COUNT`
- 値が自己説明的
  - `'ogp'`, `'twitter'`, `'draft'`, `'published'`
- 定数名と値が一致または対応
  - `STATUS_DRAFT = 'draft'`
- 業界標準の用語
  - `OGP`, `TTL`, `HTTP_*`

#### 確信度: 中

- ドメイン固有語だが文脈から推測可能
  - `HANAGUMI`, `JMADAM` (サイト固有だが用途は明確)
- 略語だが一般的
  - `API_URL`, `DB_HOST`
- 配列構造から意図が読み取れる

#### 確信度: 低

- マジックナンバーで意図不明
  - `100`, `3600`, `256`
- 略語のみで文脈なし
  - `TH`, `CT`, `FLG`
- 複数の解釈が可能
  - `LIMIT` (件数? サイズ? 時間?)
- 使用箇所を確認しないと判断できない

### 定数名の推測パターン

| パターン | 推測 |
|----------|------|
| `*_URL`, `*_ENDPOINT` | URLエンドポイント |
| `*_TIMEOUT`, `*_TTL` | 時間設定（秒/ミリ秒を確認） |
| `*_LIMIT`, `*_MAX`, `*_MIN` | 制限値 |
| `*_PREFIX`, `*_SUFFIX` | 文字列の接頭辞/接尾辞 |
| `STATUS_*`, `STATE_*` | ステータス値 |
| `TYPE_*`, `KIND_*` | 種別識別子 |
| `DEFAULT_*` | デフォルト値 |
| `ENABLE_*`, `DISABLE_*` | フラグ |

### 値の推測パターン

| パターン | 推測 |
|----------|------|
| `3600`, `86400` | 秒単位の時間（1時間、1日） |
| `1024`, `2048` | バイトサイズ (KB, MB) |
| `200`, `404`, `500` | HTTPステータスコード |
| 日本語文字列 | UI表示用ラベル、SEOテキスト |
| URL形式 | 外部サービスエンドポイント |

### レビュー後の検索

```bash
# 未レビューの定数を検索
grep -r "@todo 要確認" src/Constants/

# 確信度低のみ検索
grep -r "確信度低" src/Constants/
```

## 2. リソースドキュメント

リソースクラスのPHPDocコメントを自動生成する。クラス名・HTTPメソッド・パラメータから意図を推測。

### 生成例

#### @todoマーカーあり

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

### 確信度の判定基準（リソース）

#### 確信度: 高

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

#### 確信度: 中

- クラス名がドメイン固有語
  - `Hanagumi`, `Flagshop`
- 複合的な操作
  - `onPost` で更新も行う
- パラメータが多い（5個以上）

#### 確信度: 低

- クラス名が略語
  - `Art`, `Usr`, `Ord`
- 非標準のメソッドパターン
  - `onGet` で副作用がある
- パラメータの意図が不明
  - `$data`, `$params`, `$options`
- 複雑なビジネスロジック

### クラス名からの推測

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

### メソッドからの推測

| メソッド | パラメータ | 推測 |
|----------|------------|------|
| `onGet` | `int $id` | 1件取得 |
| `onGet` | なし or ページング | 一覧取得 |
| `onGet` | 検索条件 | 検索/フィルタ |
| `onPost` | 作成データ | 新規作成 (201) |
| `onPut` | `$id` + データ | 全体更新 |
| `onPatch` | `$id` + 部分データ | 部分更新 |
| `onDelete` | `int $id` | 削除 (204) |

### パラメータ名からの推測

| パラメータ | 推測 |
|------------|------|
| `$id`, `$articleId` | リソース識別子 |
| `$title`, `$name` | 名称 |
| `$body`, `$content` | 本文 |
| `$email`, `$phone` | 連絡先 |
| `$page`, `$limit`, `$offset` | ページネーション |
| `$sort`, `$order` | ソート |
| `$q`, `$keyword`, `$search` | 検索キーワード |

### 属性からの推測

| 属性 | 追加情報 |
|------|----------|
| `#[Embed]` | 埋め込みリソースあり |
| `#[Link]` | 関連リソースへのリンク |
| `#[Cacheable]` | キャッシュ可能 |
| `#[JsonSchema]` | 入力バリデーションあり |

### レビュー後の検索

```bash
# 未レビューのリソースを検索
grep -r "@todo 要確認" src/Resource/

# 確信度低のみ検索
grep -r "確信度低" src/Resource/
```

## ワークフロー

1. 対象ファイルを指定
2. @todoマーカーの有無を選択（上記説明を提示）
3. コードを解析し、コメントを生成
4. 確信度を判定して付与
5. ファイルに適用
6. `composer cs-fix` で整形
