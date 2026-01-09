---
name: bear-to-alps
description: 既存のBEAR.Sundayプロジェクトからalpsプロファイルを生成。#[Alps]属性を読み取り、またはリソース構造から推測してALPSプロファイルを作成。オプションで#[Alps]属性をリソースに追加。
---

# BEAR.Sunday to ALPS Profile Generator

既存のBEAR.Sundayプロジェクトを走査してALPSプロファイルを生成するスキル。

## When to Use This Skill

- 既存のBEAR.Sundayプロジェクトからapi設計書(ALPSプロファイル)を作りたい
- #[Alps]属性を既存リソースに追加したい
- リソース構造をALPSで可視化したい
- API設計のドキュメント化を自動化したい

## Prerequisites

- PHP 8.1以上
- 既存のBEAR.Sundayプロジェクト
- asd (app-state-diagram) コマンド - ALPSの検証・HTML生成に使用

## ALPS Structure Reference

### Three Layers of ALPS

1. **Ontology** - Semantic descriptors (データ要素)
   - Atomic data fields with `type="semantic"` (default)
   - Should have `id`, `title`, and optionally `doc` and `def` (schema.org link)
   - Example: `userId`, `userName`, `createdAt`

2. **Taxonomy** - State descriptors (状態/画面)
   - Composite descriptors containing semantic fields and transitions
   - Represents application states (e.g., UserList, User, Cart)
   - BEAR.Sunday: 1 Resource class = 1 Taxonomy

3. **Choreography** - Transition descriptors (遷移/アクション)
   - `type="safe"` - Read operations (GET)
   - `type="unsafe"` - Create operations (POST) - not idempotent
   - `type="idempotent"` - Update/Delete operations (PUT/DELETE)
   - Must have `rt` (return type) pointing to target state

### Naming Conventions

| Type | Prefix | Example |
|------|--------|---------|
| Safe transition | `go` | `goHome`, `goUserList`, `goUser` |
| Unsafe transition | `do` | `doCreateUser`, `doAddToCart` |
| Idempotent transition | `do` | `doUpdateUser`, `doDeleteItem` |
| State/Page | PascalCase | `Home`, `UserList`, `User` |
| Semantic field | camelCase | `userId`, `userName`, `createdAt` |

### Determining idempotent: PUT vs DELETE

**PUT (Update) indicators:**
- `update`, `edit`, `modify`, `change`, `set`, `replace`

**DELETE indicators:**
- `delete`, `remove`, `cancel`, `clear`, `destroy`

## Step-by-Step Process

### Step 1: プロジェクトの確認

**最初に確認する:**

1. プロジェクトディレクトリのパスを確認
2. composer.jsonからnamespaceを特定
3. src/Resource/配下のリソースクラスを列挙

```bash
# ディレクトリ構造確認
ls -la src/Resource/App/
ls -la src/Resource/Page/

# composer.jsonからnamespace確認
cat composer.json | grep -A5 '"autoload"'
```

### Step 2: モード選択

Use AskUserQuestion tool to ask:

- **Extract & Generate (Recommended)** - #[Alps]属性があればそれを使用、なければリソース構造から推測してALPSプロファイルを生成
- **Add Attributes** - リソース構造からALPSを推測し、#[Alps]属性をリソースクラスに追加、ALPSプロファイルも生成

### Step 3: 情報の軽量抽出

**重要:** ソースファイル全体を読まず、必要な情報のみを抽出する。
これによりトークン消費を大幅に削減できる（autoload不要）。

#### 3.1 JsonSchemaからOntology/Taxonomy抽出（主要情報源）

`var/schema/`だけでOntology（プロパティ）とTaxonomy（スキーマ名）が揃う。

```bash
# Taxonomy: スキーマファイル名から状態名を取得
echo "=== Taxonomy (from schema files) ==="
ls var/schema/response/*.json 2>/dev/null | xargs -I{} basename {} .json

# Ontology: 全スキーマからプロパティ名を抽出
echo ""
echo "=== Ontology (from schema properties) ==="
for f in var/schema/response/*.json var/schema/request/*.json 2>/dev/null; do
  [ -f "$f" ] || continue
  echo "--- $(basename "$f") ---"
  grep -o '"[a-zA-Z][a-zA-Z0-9]*":' "$f" | tr -d '":' | sort -u
done
```

**出力例:**
```
=== Taxonomy (from schema files) ===
user
users
article
articles

=== Ontology (from schema properties) ===
--- user.json ---
id
name
email
createdAt
```

**活用:**
- スキーマファイル名 → Taxonomy ID（`user.json` → `User`, `users.json` → `UserList`）
- プロパティ名 → Ontology要素
- プロパティの`title`/`description`があればALPSの`title`/`doc`に活用

#### 3.2 PHPからChoreography抽出（遷移情報）

PHPソースからは遷移（メソッド + #[Link]）の情報を抽出:

```bash
echo "=== Choreography (from PHP methods) ==="
for f in src/Resource/App/*.php; do
  name=$(basename "$f" .php)
  echo "--- $name ---"
  perl -0777 -ne '
while(/((?:#\[[^\]]+\]\s*)+)?public function (on\w+)\(([^)]*)\)/gs){
  $attrs = $1 // "";
  $method = $2;
  $params = $3;
  $attrs =~ s/\s+/ /g;
  $attrs =~ s/^\s+|\s+$//g;
  print "$method($params)";
  print "\n  [$attrs]" if $attrs;
  print "\n";
}' "$f"
done
```

**出力例:**
```
--- Article ---
onGet(int $id)
  [#[Link(rel: 'tags', href: '...')] #[ReturnNotFound]]
onPost(ArticleDomain $article, int $userId)
onPut(ArticleDomain $article, int $userId)
onDelete(int $id)

--- Articles ---
onGet(string $contentType, int|null $limit = null)
  [#[Link(rel: 'article', href: '/article{?id}')]]
```

#### 3.3 テンプレートからChoreography抽出

テンプレートの`<a>`タグも遷移情報の重要な情報源:

```bash
echo "=== Choreography (from templates) ==="
# Twig templates
grep -rh 'href=' src/Resource/App/*.html.twig 2>/dev/null | \
  grep -oE 'href="[^"]*"' | sort -u

# PHP templates
grep -rh 'href=' src/Resource/App/*.php 2>/dev/null | \
  grep -oE "href=['\"][^'\"]*['\"]" | sort -u
```

**出力例:**
```
href="/users"
href="/user/{{ id }}"
href="/article/{{ article.id }}"
```

#### 3.4 抽出情報のまとめ

| 情報源 | 抽出内容 | ALPS Layer |
|--------|----------|------------|
| var/schema/*.json ファイル名 | Taxonomy ID | Taxonomy |
| var/schema/*.json プロパティ | データ要素 | Ontology |
| PHP onメソッド | 遷移タイプ（safe/unsafe/idempotent） | Choreography |
| PHP #[Link] | 遷移先（rt決定） | Choreography |
| テンプレート `<a>` タグ | 遷移先リンク | Choreography |
| PHP #[Alps] | 明示的ID（あれば優先） | All |

#### 3.5 #[Alps]属性がある場合

```php
#[Alps('UserList')]
class Users extends ResourceObject { }

#[Alps('goUserList')]
public function onGet(): static { }
```

- クラスレベルの#[Alps]はTaxonomy IDとして使用
- メソッドレベルの#[Alps]はChoreography IDとして使用

### Step 4: ALPS構造の構築

#### 4.1 命名規則からの推測（#[Alps]がない場合）

| リソースクラス | ALPS Taxonomy ID |
|--------------|-----------------|
| Users.php | UserList |
| User.php | UserDetail または User |
| Products.php | ProductList |
| Product.php | Product |

| メソッド | ALPS Choreography ID | Type |
|---------|---------------------|------|
| onGet() | go{TaxonomyId} | safe |
| onPost() | doCreate{Entity} | unsafe |
| onPut() | doUpdate{Entity} | idempotent |
| onPatch() | doModify{Entity} | idempotent |
| onDelete() | doDelete{Entity} または doRemove{Entity} | idempotent |

#### 4.2 #[Link]からの遷移情報とrt決定

```php
#[Link(rel: 'goUser', href: '/user{?id}')]
// → ALPS: {"id": "goUser", "type": "safe", "rt": "#UserDetail"}
```

**rt（return type）の決定ロジック:**

1. **hrefからリソースクラスを特定:**
   ```
   /user{?id} → User.php → User (Taxonomy ID)
   /users → Users.php → UserList (Taxonomy ID)
   ```

2. **URIパターンからクラス名へのマッピング:**
   | href | Resource Class | Taxonomy ID |
   |------|---------------|-------------|
   | /user, /user{?id} | User.php | User |
   | /users | Users.php | UserList |
   | /product/{id} | Product.php | Product |
   | /products | Products.php | ProductList |

3. **遷移タイプによるrt決定:**
   - `go*` (safe): 遷移先のTaxonomy
   - `doCreate*` (unsafe): 作成されたリソースのTaxonomy
   - `doUpdate*` (idempotent): 更新されたリソースのTaxonomy（通常は同じ）
   - `doDelete*` (idempotent): 削除後の遷移先（通常は一覧）

4. **#[Alps]属性がクラスにある場合:**
   そのクラスの#[Alps]値をTaxonomy IDとして使用

### Step 5: ALPSプロファイルの生成

```json
{
  "$schema": "https://alps-io.github.io/schemas/alps.json",
  "alps": {
    "title": "{Package} API",
    "doc": {"value": "Generated from BEAR.Sunday resources"},
    "descriptor": [
      // Ontology: メソッドパラメータとJsonSchemaプロパティから
      {"id": "userId", "title": "User ID", "def": "https://schema.org/identifier"},
      {"id": "userName", "title": "User Name", "def": "https://schema.org/name"},

      // Taxonomy: リソースクラスから
      {"id": "UserList", "title": "User List", "descriptor": [
        {"href": "#userId"},
        {"href": "#userName"},
        {"href": "#goUser"},
        {"href": "#doCreateUser"}
      ]},
      {"id": "User", "title": "User", "descriptor": [
        {"href": "#userId"},
        {"href": "#userName"},
        {"href": "#goUserList"},
        {"href": "#doUpdateUser"},
        {"href": "#doDeleteUser"}
      ]},

      // Choreography: メソッドと#[Link]から
      {"id": "goUserList", "type": "safe", "rt": "#UserList", "title": "View User List"},
      {"id": "goUser", "type": "safe", "rt": "#User", "title": "View User",
        "descriptor": [{"href": "#userId"}]},
      {"id": "doCreateUser", "type": "unsafe", "rt": "#User", "title": "Create User",
        "descriptor": [{"href": "#userName"}]},
      {"id": "doUpdateUser", "type": "idempotent", "rt": "#User", "title": "Update User",
        "descriptor": [{"href": "#userId"}, {"href": "#userName"}]},
      {"id": "doDeleteUser", "type": "idempotent", "rt": "#UserList", "title": "Delete User",
        "descriptor": [{"href": "#userId"}]}
    ]
  }
}
```

### Step 6: 出力と検証

```bash
# ALPSプロファイルを保存
# docs/alps.json

# 検証
asd --validate docs/alps.json

# HTML生成
asd docs/alps.json -o docs/alps.html

# 状態遷移図の確認
open docs/alps.html
```

**検証でエラーが出た場合:** alpsスキルを使用してプロファイルを修正してください。
詳細: https://www.app-state-diagram.com/manuals/1.0/ja/ai-assistant.html#skill-claude-code

### Step 7: #[Alps]属性の追加（Add Attributesモード）

ユーザーが「Add Attributes」モードを選択した場合、
推測したALPS IDをリソースクラスに属性として追加。

**Before:**
```php
<?php
declare(strict_types=1);

namespace MyVendor\MyProject\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Users extends ResourceObject
{
    #[Link(rel: 'user', href: '/user{?id}')]
    public function onGet(): static
    {
        // ...
    }

    public function onPost(string $userName, string $email): static
    {
        // ...
    }
}
```

**After:**
```php
<?php
declare(strict_types=1);

namespace MyVendor\MyProject\Resource\App;

use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

#[Alps('UserList')]
class Users extends ResourceObject
{
    #[Alps('goUserList')]
    #[Link(rel: 'goUser', href: '/user{?id}')]
    #[Link(rel: 'doCreateUser', href: '/users')]
    public function onGet(): static
    {
        // ...
    }

    #[Alps('doCreateUser')]
    public function onPost(string $userName, string $email): static
    {
        // ...
    }
}
```

**追加時の注意:**

1. use文の追加: `use BEAR\ApiDoc\Annotation\Alps;`
2. クラスに#[Alps]属性を追加（Taxonomy）
3. 各onメソッドに#[Alps]属性を追加（Choreography）
4. #[Link]のrelをALPS IDに更新（一貫性のため）

### Step 8: #[Link] rel の更新

既存の#[Link]属性のrelをALPS IDに更新して一貫性を保つ:

**Before:**
```php
#[Link(rel: 'user', href: '/user{?id}')]
#[Link(rel: 'create', href: '/users')]
```

**After:**
```php
#[Link(rel: 'goUser', href: '/user{?id}')]
#[Link(rel: 'doCreateUser', href: '/users')]
```

## マッピングルール詳細

### HTTPメソッド → ALPS Type

| HTTP Method | ALPS Type | ID Prefix | 説明 |
|-------------|-----------|-----------|------|
| GET | safe | go | 安全な読み取り |
| POST | unsafe | do | 新規作成（非冪等） |
| PUT | idempotent | do | 完全更新（冪等） |
| PATCH | idempotent | do | 部分更新（冪等） |
| DELETE | idempotent | do | 削除（冪等） |

### リソースクラス → Taxonomy

| Class Pattern | Taxonomy ID | 説明 |
|--------------|-------------|------|
| {Entity}s.php | {Entity}List | 複数形 = 一覧 |
| {Entity}.php | {Entity} | 単数形 = 詳細（シンプル版） |
| Index.php | Home | トップページ |

**単数形リソースのTaxonomy ID規則:**
- 基本: `User.php` → `User`（シンプル）
- 明示的に区別が必要な場合のみ `UserDetail` を使用
- 一覧と詳細のペアは `UserList` / `User` を推奨
- `{Entity}Detail` は冗長なので避ける

### JsonSchema → Ontology

| JsonSchema Type | ALPS | schema.org |
|----------------|------|------------|
| "type": "string", "format": "email" | email | schema.org/email |
| "type": "string", "format": "date-time" | dateCreated | schema.org/dateCreated |
| "type": "integer" | count, quantity | schema.org/Integer |
| "type": "boolean" | isActive | schema.org/Boolean |

## エラーハンドリング

### リソースクラスが見つからない場合

```
警告: src/Resource/App/ にリソースクラスが見つかりません

対処:
1. プロジェクトディレクトリが正しいか確認
2. composer autoloadが設定されているか確認
3. リソースクラスがResourceObjectを継承しているか確認
```

### 循環参照の検出

```
警告: 循環参照が検出されました
  UserList → goUser → UserDetail → goUserList → UserList

対処:
これはALPSでは許容されます。状態遷移図に循環があることを確認してください。
```

### 孤立したTaxonomy

```
警告: 以下のTaxonomyへの遷移が定義されていません
  - OrphanPage

対処:
1. 他のリソースから#[Link]で参照を追加
2. または、このTaxonomyを削除
```

## Output Summary

### Extract & Generate モード

```markdown
## ALPS Profile 生成完了

### 抽出情報

#### Ontology (データ要素): {n}個
- userId, userName, email, dateCreated, ...

#### Taxonomy (状態): {n}個
- UserList (from Users.php)
- User (from User.php)
- ...

#### Choreography (遷移): {n}個
- goUserList (safe) → UserList
- goUser (safe) → User
- doCreateUser (unsafe) → User
- ...

### 生成ファイル
✓ docs/alps.json
✓ docs/alps.html

### 次のステップ
1. 状態遷移図を確認:
   open docs/alps.html

2. 必要に応じてプロファイルを編集

3. #[Alps]属性を追加したい場合は再度このスキルを実行し
   「Add Attributes」モードを選択
```

### Add Attributes モード

```markdown
## #[Alps]属性追加完了

### 更新ファイル ({n}ファイル)

✓ src/Resource/App/Users.php
  - Class: #[Alps('UserList')]
  - onGet: #[Alps('goUserList')]
  - onPost: #[Alps('doCreateUser')]
  - #[Link] rel更新: user → goUser, create → doCreateUser

✓ src/Resource/App/User.php
  - Class: #[Alps('User')]
  - onGet: #[Alps('goUser')]
  - onPut: #[Alps('doUpdateUser')]
  - onDelete: #[Alps('doDeleteUser')]
  - #[Link] rel更新: users → goUserList, edit → doUpdateUser

### 追加パッケージ
composer require bear/api-doc (追加済み or 要追加)

### 生成ファイル
✓ docs/alps.json
✓ docs/alps.html

### 次のステップ
1. 更新されたリソースを確認
2. テスト実行: composer test
3. 状態遷移図を確認: open docs/alps.html
```

## Advanced: 既存#[Alps]との整合性チェック

すでに#[Alps]属性がある場合、生成されるALPSとの整合性をチェック:

```
整合性チェック結果:

✓ Users.php #[Alps('UserList')] - OK
✓ User.php #[Alps('User')] - OK
✗ Product.php #[Alps('ProductDetail')] - 推奨: 'Product'
  理由: 単数形リソースはシンプルに '{Entity}' を推奨

確認: このまま続行しますか？
  - はい、そのまま続行
  - いいえ、推奨値に更新
```

## References

- ALPS Specification: https://alps-io.github.io/spec/
- BEAR.Sunday Resource: https://bearsunday.github.io/manuals/1.0/ja/resource.html
- BEAR.ApiDoc: https://github.com/bearsunday/BEAR.ApiDoc
- app-state-diagram: https://github.com/alps-asd/app-state-diagram
