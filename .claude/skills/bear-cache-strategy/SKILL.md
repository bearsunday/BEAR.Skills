---
name: bear-cache-strategy
description: リソースクラスを走査し、キャッシュ属性を追加する。キャッシュ宣言がないリソースを検出して適切な属性を適用。
---

# BEAR.Sunday キャッシュ属性追加スキル

## 目的

キャッシュ宣言がないリソースを検出し、適切なキャッシュ属性を追加する。

## 実行手順

### 1. キャッシュ未宣言リソースを検出

```bash
# onGetがあるがCacheableがないファイルを検出
grep -rl "function onGet" src/Resource | xargs grep -L "Cacheable"
```

### 2. 各リソースを分類

リソースを読み、以下を判定:
- コンテンツAPI → `#[Cacheable]`
- 計算API → `#[Cacheable(expirySecond: N)]`
- キャッシュ不可 → コメントで理由明示

### 3. キャッシュ属性を追加

```php
use BEAR\RepositoryModule\Annotation\Cacheable;

#[Cacheable]
public function onGet(int $id): static
```

## リソースの分類

### コンテンツAPI（キャッシュ可能）

データの取得・表示が主目的。同じ入力には同じ出力。

| 特徴 | 例 |
|------|-----|
| 記事、ページ | Article, Page, Post |
| 一覧表示 | Articles, List, Index |
| マスタデータ | Category, Tag, User |
| 静的コンテンツ | About, Help, Guide |

**適用属性:**
```php
#[Cacheable]
#[CacheableResponse(maxAge: 3600)]
#[DonutCache]  // 部分キャッシュ
```

### 計算API（キャッシュ不可/短時間）

リアルタイム性が必要、または副作用がある。

| 特徴 | 例 |
|------|-----|
| リアルタイムデータ | Stock, Rate, Weather |
| ユーザー固有 | Cart, Session, Preference |
| 集計・計算 | Analytics, Report, Stats |
| 書き込み操作 | POST/PUT/DELETE |

**適用属性:**
```php
#[Cacheable(expirySecond: 60)]  // 短時間キャッシュ
// または属性なし（キャッシュしない）
```

## 判定フロー

```text
リソースクラスを読む
    ↓
onGet のみ？ ─No→ キャッシュしない（書き込み操作）
    ↓ Yes
外部API依存？ ─Yes→ 短時間キャッシュ or キャッシュしない
    ↓ No
ユーザー固有？ ─Yes→ キャッシュしない or Vary: Cookie
    ↓ No
時間依存？ ─Yes→ 短時間キャッシュ
    ↓ No
コンテンツAPI → #[Cacheable] 適用
```

## 適用手順

### 1. リソースを走査して分類

```php
// 分類結果
$contentApis = [
    'App\Resource\App\Article',      // 記事
    'App\Resource\App\Category',     // カテゴリ
    'App\Resource\Page\Index',       // トップページ
];

$computationApis = [
    'App\Resource\App\Cart',         // カート（ユーザー固有）
    'App\Resource\App\Search',       // 検索（パラメータ多様）
    'App\Resource\App\Analytics',    // 集計（リアルタイム）
];
```

### 2. コンテンツAPIにキャッシュ属性を追加

```php
use BEAR\RepositoryModule\Annotation\Cacheable;

#[Cacheable]
class Article extends ResourceObject
{
    public function onGet(int $id): static
}
```

### 3. 計算APIは短時間または無効

```php
// 短時間キャッシュ（60秒）
#[Cacheable(expirySecond: 60)]
class Ranking extends ResourceObject

// キャッシュなし（属性なし）
class Cart extends ResourceObject
```

## キャッシュ戦略の選択

### #[Cacheable] - 依存が明確なリソース

予測可能性が高く、依存関係が明らかなリソースに使用。

```php
// ✅ 適用: 依存が明確（記事IDのみに依存）
#[Cacheable]
public function onGet(int $id): static

// ✅ 適用: ETagで自動無効化
#[Cacheable]
#[Embed(rel: 'author', src: 'app://self/user{?id}')]
public function onGet(int $id): static
```

**適用条件:**
- 入力パラメータのみに依存
- 時間に依存しない
- 外部状態に依存しない
- Embedの依存も自動追跡される

### #[DonutCache] - 部分的に動的なページ

ページの大部分はキャッシュ可能だが、一部が動的（ユーザー情報等）。

```php
// Pageリソース: 全体をドーナッツキャッシュ
#[DonutCache]
#[Embed(rel: 'article', src: 'app://self/article{?id}')]      // キャッシュされる
#[Embed(rel: 'sidebar', src: 'app://self/sidebar')]           // キャッシュされる
#[Embed(rel: 'user_menu', src: 'app://self/user/menu')]       // 動的（穴）
public function onGet(int $id): static
```

```text
┌─────────────────────────────┐
│  ヘッダー（キャッシュ）      │
├─────────────────────────────┤
│  記事本文（キャッシュ）      │
│                             │
│  ┌─────────────────────┐   │
│  │ ユーザーメニュー     │   │  ← ドーナッツの穴（動的）
│  │ （毎回取得）         │   │
│  └─────────────────────┘   │
│                             │
│  サイドバー（キャッシュ）    │
└─────────────────────────────┘
```

### #[CacheableResponse] - CDN/ブラウザキャッシュ

HTTPレスポンスレベルでキャッシュ。CDNやブラウザに指示。

```php
#[CacheableResponse(maxAge: 3600, sMaxAge: 86400)]
public function onGet(int $id): static
// Cache-Control: max-age=3600, s-maxage=86400
```

### #[Cacheable(expirySecond: N)] - TTLが明確な計算API

計算APIでも更新頻度がわかればキャッシュ可能。

```php
// ランキング: 5分ごとに更新で十分
#[Cacheable(expirySecond: 300)]
public function onGet(): static

// 為替レート: 1分で十分
#[Cacheable(expirySecond: 60)]
public function onGet(string $currency): static

// 天気: 10分で十分
#[Cacheable(expirySecond: 600)]
public function onGet(string $city): static
```

**TTL判定の質問:**
- このデータは何秒古くても許容される？
- 更新頻度はどのくらい？
- ユーザーは古いデータに気づく？

| リソース | 許容遅延 | TTL例 |
|---------|---------|-------|
| ランキング | 5分 | 300 |
| 為替レート | 1分 | 60 |
| 天気 | 10分 | 600 |
| 在庫数 | 30秒 | 30 |
| ニュース一覧 | 1分 | 60 |

### キャッシュなし - 本当に予測不能なリソース

```php
// キャッシュしない: ユーザー固有、セッション依存
public function onGet(): static  // 属性なし
```

**キャッシュ不可の条件:**
- ユーザーセッションに依存
- リアルタイム性が絶対必要（チャット等）
- 書き込み操作（POST/PUT/DELETE）

### キャッシュ宣言がないリソース = 問題

キャッシュ属性が何もないのは「考慮漏れ」。すべてのGETリソースはキャッシュ戦略を明示すべき。

```php
// ❌ 問題: キャッシュ宣言なし（考慮漏れ）
public function onGet(int $id): static

// ✅ 推奨: 明示的にキャッシュ
#[Cacheable]
public function onGet(int $id): static

// ✅ 推奨: 明示的にTTL指定
#[Cacheable(expirySecond: 300)]
public function onGet(): static

// ✅ 推奨: キャッシュ不可なら #[NoCache] や コメントで明示
/** @note キャッシュ不可: ユーザーセッションに依存 */
public function onGet(): static
```

**レビュー時のチェック:**
- onGetメソッドにキャッシュ属性があるか？
- なければ理由が明示されているか？

## 判定マトリクス

| 条件 | キャッシュ戦略 |
|------|---------------|
| 依存が明確 + 時間非依存 | `#[Cacheable]` |
| ページ全体は静的、一部動的 | `#[DonutCache]` |
| CDN/ブラウザでキャッシュ | `#[CacheableResponse]` |
| 許容遅延が明確 | `#[Cacheable(expirySecond: N)]` |
| ユーザー固有/セッション依存 | キャッシュなし |

## 戦略選択フロー

```text
リソースを分析
    ↓
書き込み操作？ ─Yes→ キャッシュ不可
    ↓ No
ユーザー固有？ ─Yes→ キャッシュ不可
    ↓ No
依存が明確？ ─Yes→ #[Cacheable]
    ↓ No
許容遅延がある？ ─Yes→ #[Cacheable(expirySecond: N)]
    ↓ No
キャッシュ不可
```

## キャッシュ無効化

| 属性 | タイミング | 動作 |
|------|-----------|------|
| `#[Purge]` | PUT/DELETE時 | キャッシュ削除 |
| `#[Refresh]` | PUT時 | 再生成して更新 |

## 出力例

```markdown
## キャッシュ戦略レポート

### コンテンツAPI（#[Cacheable]適用推奨）
- src/Resource/App/Article.php
- src/Resource/App/Category.php
- src/Resource/Page/Index.php
- src/Resource/Page/Article.php

### 計算API（キャッシュなしまたは短時間）
- src/Resource/App/Cart.php - ユーザー固有
- src/Resource/App/Search.php - パラメータ多様
- src/Resource/App/Ranking.php - #[Cacheable(expirySecond: 300)]推奨

### 書き込みAPI（キャッシュ不可）
- src/Resource/App/Article.php (onPost, onPut, onDelete)
```

## 参考資料

- [BEAR.Sunday キャッシュ](https://bearsunday.github.io/manuals/1.0/ja/cache.html)
