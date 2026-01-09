---
name: bear-review
description: BEAR.SundayプロジェクトのPHPコード品質を評価する。PHPMDメトリクス（CC, NPath, パラメータ数, フィールド数）とBEAR.Sunday固有の基準（リソース設計, DI, 型安全性）で評価。コードレビュー、品質チェック、リファクタリング検討時に使用。
---

# BEAR.Sunday コードレビュースキル

## 評価手順

### 1. PHPMDによる定量評価

以下のコマンドでメトリクスを取得:

```bash
./vendor-bin/tools/vendor/bin/phpmd [ファイルパス] text codesize,design 2>/dev/null | grep -v "^Deprecated"
```

### 1.1 統計レポートの自動生成（推奨）

プロジェクト全体の品質状況を把握するため、PHPMD違反の統計レポートを自動生成することを強く推奨します。

#### baselineなしでの実行

`phpmd.baseline.xml`が存在する場合、真の品質状態を把握するために一時的に無効化します：

```bash
# 1. baselineを一時的にリネーム
mv phpmd.baseline.xml phpmd.baseline.xml.bak

# 2. 全リソースディレクトリに対してPHPMD実行
./vendor-bin/tools/vendor/bin/phpmd src/Resource text phpmd.xml 2>&1 > phpmd_output.txt

# 3. 復元
mv phpmd.baseline.xml.bak phpmd.baseline.xml
```

#### 統計集計コマンド

PHPMDの出力から自動的に統計を生成：

```bash
# 総違反数
cat phpmd_output.txt | wc -l

# カテゴリ別集計
echo "=== カテゴリ別統計 ==="
echo "LongVariable:           $(grep -c 'LongVariable' phpmd_output.txt) 件"
echo "CouplingBetweenObjects: $(grep -c 'CouplingBetweenObjects' phpmd_output.txt) 件"
echo "StaticAccess:           $(grep -c 'StaticAccess' phpmd_output.txt) 件"
echo "ElseExpression:         $(grep -c 'ElseExpression' phpmd_output.txt) 件"
echo "UnusedFormalParameter:  $(grep -c 'UnusedFormalParameter' phpmd_output.txt) 件"

# 複雑度違反（重要度高）
echo ""
echo "=== 複雑度違反（高優先度） ==="
echo "CyclomaticComplexity:   $(grep -c 'CyclomaticComplexity' phpmd_output.txt) 件"
echo "NPathComplexity:        $(grep -c 'NPathComplexity' phpmd_output.txt) 件"
echo "ExcessiveMethodLength:  $(grep -c 'ExcessiveMethodLength' phpmd_output.txt) 件"
echo "ExcessiveClassLength:   $(grep -c 'ExcessiveClassLength' phpmd_output.txt) 件"
echo "TooManyFields:          $(grep -c 'TooManyFields' phpmd_output.txt) 件"
```

#### 統計レポート例

実行結果の例：

```
=== PHPMD統計レポート ===
総違反数: 259件

【カテゴリ別】
- LongVariable:            92件 (35.5%)
- CouplingBetweenObjects:  28件 (10.8%)
- StaticAccess:            24件 (9.3%)
- ElseExpression:          20件 (7.7%)
- UnusedFormalParameter:   18件 (7.0%)
...

【複雑度違反（高優先度）】
- CyclomaticComplexity:     5件
- NPathComplexity:          5件
- ExcessiveMethodLength:    7件
- ExcessiveClassLength:     1件
- TooManyFields:            0件
```

#### 最も問題のあるファイルの特定

```bash
# 違反数が多いファイル TOP 10
cat phpmd_output.txt | awk -F: '{print $1}' | sort | uniq -c | sort -rn | head -10

# 例:
#   5 src/Resource/Page/Content/SpecialContent.php
#   4 src/Resource/App/GlobalNav.php
#   3 src/Resource/App/Contents/Ranking.php
```

#### baselineありなしの比較

baselineによって隠蔽されている問題数を可視化：

```bash
# baseline なしの違反数
baseline_off=$(cat phpmd_output.txt | wc -l)

# baseline ありの違反数（通常実行）
baseline_on=$(./vendor-bin/tools/vendor/bin/phpmd src/Resource text phpmd.xml 2>&1 | wc -l)

echo "=== Baseline比較 ==="
echo "baselineあり:   ${baseline_on}件"
echo "baselineなし:   ${baseline_off}件"
echo "抑制されている: $((baseline_off - baseline_on))件"
```

#### 深刻度別の分類

複雑度の値に基づいて深刻度を判定：

```bash
# Critical: CC>20 または NPath>10000
critical_cc=$(grep 'CyclomaticComplexity' phpmd_output.txt | awk '{print $NF}' | awk -F. '{if($1>20)print}' | wc -l)
critical_npath=$(grep 'NPathComplexity' phpmd_output.txt | awk '{print $NF}' | awk -F. '{if($1>10000)print}' | wc -l)

echo "=== 深刻度別 ==="
echo "Critical (CC>20 or NPath>10000): $((critical_cc + critical_npath)) 件"
```

#### 統計の活用方法

1. **技術的負債の可視化**: baselineで隠蔽された問題の総数を把握
2. **優先順位付け**: 複雑度違反を優先的に対応
3. **改善計画**: カテゴリ別の件数から段階的な改善計画を策定
4. **トレンド分析**: 定期的に実行して改善状況をモニタリング

### 2. 評価基準

#### Cyclomatic Complexity (CC)

| 評価 | CC値 | 状態 | アクション |
|------|------|------|-----------|
| **A** | 1-10 | 非常に良好。シンプルでテストが容易 | 維持すべき状態 |
| **B** | 11-20 | 許容範囲。少し複雑だがメンテナンス可能 | 複雑なロジックなら許容 |
| **C** | 21-30 | 警告。テストが書きづらくバグが混入しやすい | リファクタリング推奨 |
| **D** | 31+ | 失格。保守不能 | 即時対応必須 |

#### NPath Complexity

| 評価 | NPath | 状態 |
|------|-------|------|
| **A** | 1-200 | 良好 |
| **B** | 201-500 | 許容範囲 |
| **C** | 501-1000 | 警告 |
| **D** | 1001+ | 失格 |

#### ExcessiveParameterList

| 評価 | パラメータ数 | 状態 |
|------|-------------|------|
| **A** | 1-10 | 良好 |
| **B** | 11-15 | 許容範囲（DTOを検討） |
| **C** | 16-20 | 警告（オブジェクトでラップ推奨） |
| **D** | 21+ | 失格 |

#### TooManyFields

| 評価 | フィールド数 | 状態 |
|------|-------------|------|
| **A** | 1-15 | 良好 |
| **B** | 16-22 | 許容範囲（分割を検討） |
| **C** | 23-30 | 警告 |
| **D** | 31+ | 失格 |

### 3. 命名規則

#### 長すぎる変数名（LongVariable）

20文字を超える変数名は冗長。意図は伝わるが、コードが読みにくくなる。

```php
// ❌ 問題: 長すぎる（情報過多）
$articlePublishedDateTimeString = $article->getPublishedAt();
$userAuthenticationTokenValue = $auth->getToken();
$categoryIdListForFilteringArticles = [1, 2, 3];

// ✅ 推奨: 簡潔で意図が伝わる
$publishedAt = $article->getPublishedAt();
$authToken = $auth->getToken();
$filterCategoryIds = [1, 2, 3];
```

**なぜ問題か:**
- 1行が長くなり、横スクロールが必要になる
- 変数名を読むだけで疲れる
- タイプミスが増える
- 短くても文脈から意味は伝わる

| 長さ | 評価 |
|------|------|
| 1-20文字 | ✅ OK |
| 21-25文字 | ⚠️ 長い（短縮を検討） |
| 26文字以上 | ❌ 問題 |

#### 短すぎる変数名（ShortVariable）

3文字未満の変数名は意図が不明。ループカウンタ以外では避ける。

```php
// ❌ 問題: 意味不明
$a = $this->articleQuery->item($id);
$u = $this->userQuery->item($userId);
$d = new DateTimeImmutable();

// ✅ 許容: ループカウンタ
for ($i = 0; $i < $count; $i++) { }
foreach ($items as $k => $v) { }  // ただし $key => $value が望ましい

// ✅ 推奨: 意図が明確
$article = $this->articleQuery->item($id);
$user = $this->userQuery->item($userId);
$now = new DateTimeImmutable();
```

**なぜ問題か:**
- コードを読む人が意味を推測しなければならない
- 数ヶ月後の自分でも理解できない
- バグの原因になりやすい（$a と $u を取り違える等）

| 変数名 | 評価 |
|--------|------|
| `$i`, `$j`, `$k`（ループ内） | ✅ 許容 |
| `$e`（catch内のException） | ✅ 許容 |
| `$id`（引数） | ✅ OK |
| `$a`, `$b`, `$x`（一般変数） | ❌ 問題 |

#### 曖昧な命名

`$data`, `$result`, `$info`, `$tmp` などは何を表すか不明。

```php
// ❌ 問題: 曖昧
$data = $this->articleQuery->item($id);
$result = $this->validator->validate($input);
$info = $user->getProfile();
$tmp = $this->transform($article);

// ✅ 推奨: 具体的
$article = $this->articleQuery->item($id);
$validationResult = $this->validator->validate($input);
$userProfile = $user->getProfile();
$transformedArticle = $this->transform($article);
```

**避けるべき曖昧な名前:**
- `$data` → 何のデータ？
- `$result` → 何の結果？
- `$info` → 何の情報？
- `$tmp`, `$temp` → 一時的とは？
- `$value` → 何の値？
- `$item` → 何のアイテム？
- `$obj` → 何のオブジェクト？
- `$arr` → 何の配列？
- `$str` → 何の文字列？
- `$flag` → 何のフラグ？

**例外（許容されるケース）:**
- ジェネリックな処理（配列操作ユーティリティ等）
- 短いスコープでの一時変数

#### ブール変数の命名

ブール変数は `is`, `has`, `can`, `should`, `was`, `will` などで始める。

```php
// ❌ 問題: ブールかどうかわからない
$published = $article->isPublished();
$admin = $user->isAdmin();
$permission = $user->canEdit($article);
$deleted = $this->softDelete($id);

// ✅ 推奨: ブールと明確
$isPublished = $article->isPublished();
$isAdmin = $user->isAdmin();
$canEdit = $user->canEdit($article);
$wasDeleted = $this->softDelete($id);
```

| 接頭辞 | 用途 | 例 |
|--------|------|-----|
| `is` | 状態 | `$isActive`, `$isValid` |
| `has` | 所有 | `$hasChildren`, `$hasPermission` |
| `can` | 能力 | `$canEdit`, `$canDelete` |
| `should` | 推奨 | `$shouldNotify`, `$shouldCache` |
| `was` | 過去 | `$wasDeleted`, `$wasSuccessful` |
| `will` | 未来 | `$willExpire` |

#### 否定形の命名を避ける

否定形の変数名は二重否定を生みやすい。

```php
// ❌ 問題: 否定形（二重否定が発生しやすい）
$isNotValid = !$validator->validate($input);
if (!$isNotValid) { }  // 二重否定で混乱

$isDisabled = $feature->isDisabled();
if (!$isDisabled) { }  // 結局有効かどうかわかりにくい

// ✅ 推奨: 肯定形
$isValid = $validator->validate($input);
if ($isValid) { }

$isEnabled = $feature->isEnabled();
if ($isEnabled) { }
```

**なぜ問題か:**
- `!$isNotValid` は「無効でない」→「有効」と脳内変換が必要
- 条件分岐が直感的でなくなる
- バグの温床になる

#### 定数の命名

定数は SCREAMING_SNAKE_CASE で、意図が明確に。

```php
// ❌ 問題: 何を表すかわからない
private const VALUE = 100;
private const NUM = 5;
private const STR = 'article';

// ✅ 推奨: 意図が明確
private const MAX_RETRY_COUNT = 5;
private const DEFAULT_PAGE_SIZE = 100;
private const RESOURCE_TYPE_ARTICLE = 'article';
```

#### メソッド名は動詞で始める

メソッドは「何をするか」を表す。動詞で始める。

```php
// ❌ 問題: 動詞でない
public function article(int $id): Article { }
public function validation(array $data): bool { }
public function userList(): array { }

// ✅ 推奨: 動詞で始まる
public function getArticle(int $id): Article { }  // または findArticle
public function validate(array $data): bool { }
public function listUsers(): array { }  // または getUsers, fetchUsers
```

| 接頭辞 | 用途 | 例 |
|--------|------|-----|
| `get` | 取得（単一） | `getUser()`, `getArticle()` |
| `list` / `getAll` | 取得（複数） | `listUsers()`, `getAllArticles()` |
| `find` | 検索（見つからない可能性） | `findByEmail()` |
| `create` / `add` | 作成 | `createUser()`, `addComment()` |
| `update` | 更新 | `updateProfile()` |
| `delete` / `remove` | 削除 | `deleteArticle()`, `removeTag()` |
| `is` / `has` / `can` | 判定 | `isValid()`, `hasPermission()` |
| `validate` | 検証 | `validateInput()` |
| `calculate` | 計算 | `calculateTotal()` |
| `convert` / `transform` | 変換 | `convertToArray()` |

#### 🎲 命名個性

同じ概念に違う名前。各自が「個性」を発揮してチーム内で統一されていない。

```php
// ❌ 問題: 同じ意味なのに名前がバラバラ
class Order
{
    public DateTime $createdAt;      // At 付き
}

class User
{
    public DateTime $created;         // At なし
}

class Article
{
    public DateTime $createdDatetime; // Datetime 付き
}

class Comment
{
    public DateTime $createDate;      // Date で create（過去形じゃない）
}

// 他にもバラバラになりがちなもの:
// - updatedAt / updated / modifiedAt / lastModified
// - deletedAt / deleted / removedAt
// - userId / user_id / uid / userID
// - isActive / active / enabled / isEnabled

// ✅ 推奨: プロジェクト全体で統一
// 命名規約を決めて徹底する
class Order
{
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;
    public ?DateTimeImmutable $deletedAt;
}

class User
{
    public DateTimeImmutable $createdAt;  // 同じ規約
    public DateTimeImmutable $updatedAt;
}
```

**よくある不統一:**
| 概念 | バラバラ例 | 統一例 |
|------|-----------|--------|
| 作成日時 | created, createdAt, createdDatetime, createDate | `createdAt` |
| 更新日時 | updated, updatedAt, modifiedAt, lastModified | `updatedAt` |
| ID | userId, user_id, uid, userID | `userId` |
| フラグ | isActive, active, enabled | `isActive` |
| 件数 | count, total, num, cnt | `count` |

### 4. コード構造

#### 深すぎるネスト

ネストが3段階以上は読みにくい。早期リターンやメソッド抽出で解消。

```php
// ❌ 問題: 深いネスト（矢印型コード）
public function onGet(int $id): static
{
    $article = $this->articleQuery->item($id);
    if ($article !== null) {
        if ($article->isPublished()) {
            $author = $this->userQuery->item($article->authorId);
            if ($author !== null) {
                if ($author->isActive()) {
                    $this->body = [
                        'article' => $article,
                        'author' => $author,
                    ];
                    return $this;
                }
            }
        }
    }
    throw new NotFoundException();
}

// ✅ 推奨: 早期リターン（ガード節）
public function onGet(int $id): static
{
    $article = $this->articleQuery->item($id);
    if ($article === null) {
        throw new ArticleNotFoundException($id);
    }

    if (! $article->isPublished()) {
        throw new ArticleNotPublishedException($id);
    }

    $author = $this->userQuery->item($article->authorId);
    if ($author === null || ! $author->isActive()) {
        throw new AuthorNotFoundException($article->authorId);
    }

    $this->body = [
        'article' => $article,
        'author' => $author,
    ];

    return $this;
}
```

**なぜ問題か:**
- インデントが深くなると視認性が下がる
- 条件の組み合わせが把握しにくい
- 「この時点で何が保証されているか」がわからない

| ネスト深度 | 評価 |
|-----------|------|
| 1-2 | ✅ 良好 |
| 3 | ⚠️ 許容（簡潔なら） |
| 4以上 | ❌ リファクタリング必須 |

#### 早期リターン（ガード節）

異常系を先に処理して、正常系のネストを浅くする。

```php
// ❌ 問題: 正常系がネストの中
public function process(array $input): Result
{
    if (isset($input['id'])) {
        if ($input['id'] > 0) {
            $item = $this->find($input['id']);
            if ($item !== null) {
                // 正常処理（深いネスト）
                return new Result($item);
            } else {
                throw new NotFoundException();
            }
        } else {
            throw new InvalidIdException();
        }
    } else {
        throw new MissingIdException();
    }
}

// ✅ 推奨: ガード節で異常系を先に排除
public function process(array $input): Result
{
    if (! isset($input['id'])) {
        throw new MissingIdException();
    }

    if ($input['id'] <= 0) {
        throw new InvalidIdException();
    }

    $item = $this->find($input['id']);
    if ($item === null) {
        throw new NotFoundException();
    }

    // 正常処理（ネストなし）
    return new Result($item);
}
```

**ガード節のメリット:**
- 正常系が目立つ
- 「この行に到達した時点で何が保証されているか」が明確
- テストケースが書きやすい

#### 長すぎるメソッド

1メソッド50行を超えたら分割を検討。1つのメソッドは1つのことをする。

```php
// ❌ 問題: 長いメソッド（複数の責務）
public function onPost(ArticleInput $input): static
{
    // バリデーション（10行）
    // ...

    // 記事作成（15行）
    // ...

    // 著者通知（10行）
    // ...

    // 関連記事更新（10行）
    // ...

    // レスポンス生成（10行）
    // ...
}

// ✅ 推奨: 責務ごとにメソッド分割、またはサービスに委譲
public function onPost(ArticleInput $input): static
{
    $article = $this->articleService->create($input);

    $this->code = 201;
    $this->headers['Location'] = "/article?id={$article->id}";
    $this->body = ['id' => $article->id];

    return $this;
}
```

| 行数 | 評価 |
|------|------|
| 1-20行 | ✅ 良好 |
| 21-50行 | ⚠️ 許容（複雑なロジックなら） |
| 51行以上 | ❌ 分割を検討 |

#### 1行に複数の処理を書かない

```php
// ❌ 問題: 1行に詰め込みすぎ
$result = $this->validate($input) ? $this->process($input) : throw new ValidationException();
$user = $this->userQuery->item($id) ?? throw new UserNotFoundException($id);

// ✅ 推奨: 明確に分ける
$isValid = $this->validate($input);
if (! $isValid) {
    throw new ValidationException();
}
$result = $this->process($input);

$user = $this->userQuery->item($id);
if ($user === null) {
    throw new UserNotFoundException($id);
}
```

**例外（許容されるケース）:**
- null合体演算子でのデフォルト値: `$name = $input['name'] ?? 'default';`
- 短い三項演算子: `$status = $isActive ? 'active' : 'inactive';`

#### elseの削減

`else` は早期リターンで減らせることが多い。

```php
// ❌ 問題: 不要なelse
public function getStatus(Article $article): string
{
    if ($article->isPublished()) {
        return 'published';
    } else {
        if ($article->isDraft()) {
            return 'draft';
        } else {
            return 'archived';
        }
    }
}

// ✅ 推奨: elseなし
public function getStatus(Article $article): string
{
    if ($article->isPublished()) {
        return 'published';
    }

    if ($article->isDraft()) {
        return 'draft';
    }

    return 'archived';
}
```

#### 複雑な条件式の分解

複雑な条件は変数に抽出するか、メソッドに分離。

```php
// ❌ 問題: 複雑な条件式
if ($user->isActive() && $user->hasPermission('edit') && $article->authorId === $user->id && !$article->isLocked() && $article->status !== 'archived') {
    // ...
}

// ✅ 推奨: 意図を変数名で説明
$isAuthor = $article->authorId === $user->id;
$canEdit = $user->isActive() && $user->hasPermission('edit');
$isEditable = ! $article->isLocked() && $article->status !== 'archived';

if ($isAuthor && $canEdit && $isEditable) {
    // ...
}

// ✅ または: メソッドに抽出
if ($this->canUserEditArticle($user, $article)) {
    // ...
}
```

### 5. BEAR.Sunday固有の評価

#### リソース設計 (Resourceクラスのみ)

| 評価 | 基準 |
|------|------|
| **A** | `#[Embed]`を適切に使用、単一責任、適切なHTTPメソッド |
| **B** | 基本的なリソースパターンに従っている |
| **C** | ロジックが肥大化、責務が曖昧 |
| **D** | リソースでないコード（コントローラー的実装） |

#### bodyへの代入パターン

逐次代入ではなく、最後にまとめて構造を明示すべき。

```php
// ❌ 問題: 逐次代入（構造が見えにくい）
$this['contentTags'] = $tags;
$this['article'] = $article;
$this['blogger'] = $blogger;
$this['meta'] = $meta;

// ✅ 推奨: 最後にまとめて構造を明示
$this->body = [
    'article' => $article,
    'blogger' => $blogger,
    'contentTags' => $tags,
    'meta' => $meta,
];
```

**利点:**
- レスポンス構造が一目でわかる
- プロパティの追加・削除が容易
- コードレビューしやすい

#### privateメソッドへの引数渡しパターン

同じ引数を複数のprivateメソッドに渡すパターンは、リソースの責務過多を示す。

```php
// ❌ 問題: 同じ引数を何度も渡す、リソースが肥大化
$this->setTdParams($article, $blogger->displayName ?? '', $tags);
$this->setStructuredData($article, $meta, $blogger, $tags);
$this->setSurrogateKey($article, $blogger);

// ✅ 推奨: サービスに委譲、リソースは「何を返すか」のみ
$this->body = [
    'article' => $article,
    'blogger' => $blogger,
    'meta' => $meta,
    'tdParams' => $this->tdParamsFactory->create($article, $blogger, $tags),
    'structuredData' => $this->structuredDataFactory->create($article, $meta, $blogger, $tags),
];
$this->headers[Header::SURROGATE_KEY] = $this->surrogateKeyBuilder->build($article, $blogger);
```

**原則**: リソースは「何を返すか」を決める。「どう作るか」はサービスに任せる。

#### リソース内のループ

リソース内に複雑なループを書かない。ドメイン層に委譲する。

```php
// ❌ 問題: リソース内に複雑なループ
foreach (Ranking::CATEGORIES as $key => $categorySlugArray) {
    if ($key === self::ALL) {
        $result = $this->article->rankingAllArticleList(...);
    } else {
        $result = $this->article->rankingCategoryArticleList(...);
    }
    foreach ($result as $item) {
        $articleIds[] = $item['id'];
        // ...
    }
    $list[$key] = new ValidRankingArticleList($result, ...);
}

// ✅ 推奨: ドメインに委譲
$rankingCollection = $this->rankingAggregator->aggregate($limit);
$this->body = [
    'rankings' => $rankingCollection->lists,
    'articleIds' => $rankingCollection->articleIds,
];
```

#### Domain vs Service の区別

| 層 | 責務 | 委譲すべきロジック |
|---|------|-------------------|
| **Domain** | ビジネスロジック、ルール | 集計、計算、変換、バリデーション |
| **Service** | 外部連携、ユースケース調整 | API呼び出し、メール送信、ファイル操作 |
| **Query** | データ取得 | SQLによるデータアクセス |

```php
// Domain: ビジネスロジック
class RankingAggregator
{
    public function aggregate(array $results): RankingCollection
}

// Service: 外部連携
class MailNotificationService
{
    public function notify(User $user, Article $article): void
}

// Query: データ取得
interface RankingQueryInterface
{
    public function getCategoryRankings(int $limit): array;
}
```

#### Embed未使用の検出

`$this->resource->get()` で他リソースを取得して `$this->body` にセットしている場合、
正当な理由がなければ `#[Embed]` を使用すべき。

```php
// ❌ 問題: 手続き的なリソース取得
$user = $this->resource->get('app://self/user', ['id' => $id]);
$this->body['user'] = $user->body;

// ✅ 推奨: 宣言的なEmbed
#[Embed(src: 'app://self/user{?id}', rel: 'user')]
public function onGet(int $id): static
```

**例外（許容されるケース）:**
- 条件付きで取得する場合（if文内でのget）
- 取得結果を加工・変換する場合
- PUT/POST/DELETE内での参照

#### 戻り値の型

リソースメソッド（onGet, onPost, onPut, onPatch, onDelete）は `static` を返すべき。

```php
// ✅ 正しい
public function onGet(int $id): static

// ⚠️ 動作するが推奨されない
public function onGet(int $id): ResourceObject
public function onGet(int $id): self
```

| 戻り値型 | 評価 |
|----------|------|
| `static` | OK |
| `ResourceObject` / `self` | 推奨（staticへの変更を推奨） |

#### 依存性注入

| 評価 | 基準 |
|------|------|
| **A** | コンストラクタ注入のみ、インターフェース依存 |
| **B** | 具象クラス依存が一部 |
| **C** | トレイトによるセッターインジェクション、サービスロケーター混在 |
| **D** | グローバル状態、静的メソッド依存 |

#### セッターインジェクションの判定

**原則**: コンストラクタインジェクションを推奨。PHP 8のコンストラクタプロモーションにより、従来のインジェクショントレイトは不要。

**Ray.Diの使い分け:**
- **必須の依存関係** → コンストラクタインジェクション
- **オプショナルな依存関係** → セッターインジェクション（`optional: true`）も許容

```php
// ⚠️ 非推奨: トレイトでセッターインジェクション（必須依存）
trait MetaTag
{
    protected Article $articleMeta;

    #[Inject]
    public function setArticleMeta(Article $articleMeta): void
    {
        $this->articleMeta = $articleMeta;
    }
}

// ✅ 推奨: コンストラクタインジェクション
public function __construct(
    private readonly Article $articleMeta,
)

// ✅ OK: オプショナルな依存（存在しない場合は無視される）
#[Inject(optional: true)]
public function setDebugger(?DebuggerInterface $debugger): void
{
    $this->debugger = $debugger;
}
```

| パターン | 評価 |
|----------|------|
| コンストラクタインジェクション | ✅ 推奨 |
| トレイトによる `#[Inject]` セッター（必須依存） | ⚠️ 非推奨 |
| `#[Inject(optional: true)]` セッター | ✅ OK（オプショナル依存） |
| `use ResourceInject` | ⚠️ 非推奨（コンストラクタ注入を推奨） |
| `use AInject` 系トレイト | ⚠️ 非推奨 |
| ResourceObject固有のセッター（`setRenderer`等） | ✅ OK（フレームワーク用） |

```php
// ⚠️ 非推奨: トレイトでリソース注入
use ResourceInject;

// ✅ 推奨: コンストラクタで注入
public function __construct(
    private readonly ResourceInterface $resource,
)
```

**トレイトでのセッターインジェクションの問題点:**
- 依存関係が隠蔽される（コンストラクタを見ても分からない）
- テストが困難（セッターを呼ぶかリフレクションが必要）
- 依存がミュータブル（後から変更可能）

**注**: 既存コードでResourceInjectを使用している場合、即座にエラーではないが、新規コードではコンストラクタインジェクションを使用すべき。

#### `new` の使用判定

**重要**: `new` の使用が問題かどうかは、生成対象の種類で判断する。

| 種類 | `new` 使用 | 判定基準 |
|------|-----------|----------|
| ドメインオブジェクト | ✅ OK | データを保持、状態を表現（Entity, ValueObject） |
| 値オブジェクト | ✅ OK | イミュータブル、データ表現（DateTime, Money等） |
| DTO | ✅ OK | データ転送用オブジェクト |
| サービス | ❌ NG → DI | 振る舞いを持つ、外部依存がある |
| リポジトリ | ❌ NG → DI | データアクセス層 |
| HTTPクライアント | ❌ NG → DI | 外部通信 |

```php
// ✅ OK: ドメイン/値オブジェクト
$article = new ArticleDomain($data);
$dateTime = new DateTimeImmutable();  // イミュータブル推奨
$thumbnail = new Thumbnail($data);

// ⚠️ 警告: ミュータブルなDateTime
$date = new DateTime();  // → DateTimeImmutableを使用すべき

// ❌ NG: サービスはDIすべき
$client = new HttpClient();        // → HttpClientInterface を注入
$logger = new FileLogger();        // → LoggerInterface を注入
$mailer = new SmtpMailer();        // → MailerInterface を注入
```

**文脈から判断すること**: クラス名、名前空間、コンストラクタ引数から種類を判定する。

#### 例外の設計

`@throws Exception` は問題。具体的なドメイン例外を使用すべき。

| 記述 | 評価 |
|------|------|
| `@throws Exception` | ❌ 問題（何の例外かわからない） |
| `@throws \Exception` | ❌ 問題 |
| `@throws RuntimeException` | ⚠️ 広すぎる |
| `@throws ArticleNotFoundException` | ✅ 具体的で良い |

**推奨**: すべての例外は `RuntimeException` か `LogicException` を継承したドメイン例外とする。

```php
// ✅ 推奨: ドメイン例外
class ArticleNotFoundException extends RuntimeException {}
class InvalidArticleStateException extends LogicException {}

// 使用例
/**
 * @throws ArticleNotFoundException 記事が見つからない場合
 */
public function onGet(int $id): static
```

| 基底クラス | 用途 |
|-----------|------|
| `RuntimeException` | 実行時に発生する回復可能なエラー（リソース不在、外部API失敗等） |
| `LogicException` | プログラムのロジックエラー（不正な引数、不正な状態遷移等） |

#### リソース内のtry-catch（ポケモンキャッチ問題）

リソース内に巨大なtry-catchブロックを書かない。

```php
// ❌ 問題: 巨大なtry-catch、Throwableキャッチ
public function onGet(int $id): static
{
    try {
        // 100行以上のロジック...
        $article = $this->article->item($id);
        $blogger = $this->blogger->item($article['bloggerId']);
        $meta = $this->meta->generate($article);
        // さらに続く...
    } catch (Throwable $e) {
        $this->logger->error('エラー', ['exception' => $e]);
        throw $e;
    }
}

// ✅ 推奨: フレームワークに任せる、ロジックは委譲
public function onGet(int $id): static
{
    $articleView = $this->articleViewFactory->create($id);

    $this->body = [
        'article' => $articleView->article,
        'blogger' => $articleView->blogger,
        'meta' => $articleView->meta,
    ];

    return $this;
}
```

**問題点:**
- `Throwable` や `Exception` の広範なキャッチ（何でも捕まえる「ポケモンキャッチ」）
- tryブロックが巨大（どこでエラーが起きるか不明）
- ログして再throwは冗長（フレームワークが処理する）
- リソースの責務過多を示す

**推奨:**
- 例外処理はフレームワークに任せる
- 特定の例外のみ必要な場合は小さなtry-catchで
- ロジックはDomain/Serviceに委譲してリソースをシンプルに

| パターン | 評価 |
|----------|------|
| try-catchなし（フレームワーク任せ） | ✅ 推奨 |
| 特定例外の小さなcatch | ✅ OK |
| 巨大try + `catch (Throwable)` | ❌ 問題 |
| 巨大try + `catch (Exception)` | ❌ 問題 |

#### 型安全性

| 評価 | 基準 |
|------|------|
| **A** | 完全な型指定、ジェネリクス使用、`mixed`なし |
| **B** | 基本的な型指定あり、一部`mixed` |
| **C** | `array<string, mixed>`多用、`@psalm-suppress`多数 |
| **D** | 型指定なし、`array<object>`使用 |

#### DoctrineアノテーションとPHP 8属性

PHP 8ではDoctrineアノテーション `/** @Embed */` ではなくネイティブ属性 `#[Embed]` を使用。

| パターン | 評価 |
|----------|------|
| `#[Embed]`, `#[Inject]`, `#[Named]` | ✅ 推奨 |
| `/** @Embed */`, `/** @Inject */` | ❌ レガシー |

#### 定数と設定値

**環境依存の設定値**はクラス定数ではなく注入すべき。**アプリケーション構造の定義**はクラス定数でOK。ドメイン不変値はEnumを使用。

```php
// ❌ 問題: 環境依存の設定値をクラス定数に
private const API_URL = 'https://api.example.com';
private const TIMEOUT = 30;
private const API_KEY = 'xxx';

// ✅ 推奨: NamedModuleでバインド、#[Named]で注入
// Module:
$this->bind()->annotatedWith('API_URL')->toInstance($apiUrl);

// Resource:
public function __construct(
    #[Named('API_URL')] private readonly string $apiUrl,
    #[Named('TIMEOUT')] private readonly int $timeout,
)

// ✅ OK: アプリケーション構造の定義（環境非依存）
private const RESOURCE_URI_LIST = [
    ['list' => 'app://self/article/publishable', 'update' => 'app://self/article/publish'],
    // ...
];
private const SUPPORTED_CONTENT_TYPES = ['article', 'blog', 'news'];

// ✅ ドメイン不変値はEnum
enum ContentStatus: string {
    case Draft = 'draft';
    case Published = 'published';
}
```

| 種類 | クラス定数 | 注入 |
|------|-----------|------|
| URL、パス、APIキー | ❌ | ✅ |
| タイムアウト、認証情報 | ❌ | ✅ |
| 環境依存のID | ❌ | ✅ |
| **アプリ構造の定義（URIリスト等）** | **✅** | - |
| ステータス、型識別子 | △ Enum推奨 | - |

#### リソース内のDB直接アクセス

リソースでトランザクションやSQL実行は禁止。Query層に委譲する。

```php
// ❌ 問題: リソース内でDB操作
$this->pdo->beginTransaction();
$this->pdo->exec($sql);
$this->pdo->commit();

// ✅ 推奨: Query層に委譲
$this->articleQuery->createWithTransaction($data);
```

#### デバッグコード

`error_log()`, `var_dump()`, `print_r()` は禁止。LoggerInterfaceを使用。

```php
// ❌ 問題
error_log('Error: ' . $e->getMessage());

// ✅ 推奨
$this->logger->error('Error', ['exception' => $e]);
```

#### ファイルサイズ

| 行数 | 評価 |
|------|------|
| 1-200 | ✅ 良好 |
| 201-400 | ⚠️ 分割を検討 |
| 401+ | ❌ 責務過多 |

#### リソースメソッドの引数

`array<string, mixed>` ではなく、明示的な引数またはInputクラスを使用。

```php
// ❌ 問題: マジックバッグ
public function onGet(array $conditions): static

// ✅ 推奨: 明示的なスカラー引数
public function onGet(
    ?int $categoryId = null,
    ?string $keyword = null,
): static

// ✅ 推奨: Inputクラス（複雑なデータ）
public function onPost(UserInput $user): static
```

| パラメータ数 | 推奨 |
|-------------|------|
| 1-10 | スカラー引数（明示的で良い） |
| 11+ | `#[Input]` + DTOクラスを検討 |

**Inputを使うべき時:**
- 関連パラメータが概念として一体（住所、ユーザー情報等）
- ネスト構造や配列を含む
- 複数リソースで同じパラメータセットを使う

#### Webコンテキストの取得

スーパーグローバル直接アクセスは禁止。属性で取得する。

```php
// ❌ 問題: スーパーグローバル直接アクセス
$id = $_GET['id'];
$token = $_COOKIE['token'];

// ✅ 推奨: 属性で取得（テスト容易）
public function onGet(
    #[QueryParam('id')] string $userId,
    #[CookieParam('token')] string $token = '',
): static
```

#### ResourceParam（リソース間依存）

他リソースの結果を引数として注入。手続き的な取得より宣言的で推奨。

```php
// ❌ 問題: 手続き的に取得
public function onPut(array $data): static
{
    $userId = $this->resource->get('app://self/user/me')['id'];
    // ...
}

// ✅ 推奨: 宣言的に注入
#[ResourceParam(uri: 'app://self/user/me#id', param: 'userId')]
public function onPut(int $userId, array $data): static
```

#### ファイルアップロード

`$_FILES` 直接アクセスではなく `#[UploadFiles]` を使用。

```php
// ❌ 問題
$file = $_FILES['image'];

// ✅ 推奨
public function onPost(#[UploadFiles] array $files): static
```

#### HTTPステータスコード

適切なステータスコードを返す。

| 操作 | コード |
|------|--------|
| GET成功 | 200 OK |
| POST成功（作成） | 201 Created |
| 削除成功 | 204 No Content |
| 見つからない | 404 Not Found |
| バリデーションエラー | 400 Bad Request |

#### 201 Created と Location ヘッダー

リソースを作成する `onPost` では、201ステータスと `Location` ヘッダーをセットで返す。

```php
// ❌ 問題: 作成しているのに200のまま、Locationもない
public function onPost(string $title): static
{
    $id = $this->command->create($title);
    $this->body = ['id' => $id];
    return $this;
}

// ✅ 推奨: 201 + Location ヘッダー
public function onPost(string $title): static
{
    $id = $this->command->create($title);

    $this->code = 201;
    $this->headers['Location'] = "/article?id={$id}";
    $this->body = ['id' => $id];

    return $this;
}
```

**検出パターン:**
- `onPost` で `$this->command->create` や `$this->command->add` を呼んでいる
- しかし `$this->code = 201` がない
- または `$this->headers['Location']` がない

| パターン | 評価 |
|----------|------|
| 201 + Location あり | ✅ 推奨 |
| 201 あり、Location なし | ⚠️ 警告（Locationも追加推奨） |
| 200のまま（作成処理あり） | ❌ 問題 |

#### Pageリソースの制限

Pageリソースは `onGet` と `onPost` のみ使用。

```php
// ❌ 問題: PageでonPut/onDelete
class UserPage extends ResourceObject {
    public function onDelete(int $id): static  // NG
}

// ✅ 推奨: AppリソースでCRUD、PageはGET/POSTのみ
```

#### 継承より合成

トレイトや親クラスメソッドより依存性注入で組み合わせる。

```php
// ❌ 問題: トレイトで機能追加
use MetaTagTrait;
use SurrogateKeyTrait;

// ✅ 推奨: 依存性注入
public function __construct(
    private readonly MetaTagService $metaTag,
    private readonly SurrogateKeyService $surrogateKey,
)
```

#### Providerの過剰使用

`Provider` は複雑な生成ロジックが必要な場合のみ使用。単純な `new` だけなら `toConstructor` を使用すべき。

```php
// ❌ 問題: Providerで単純にnewしているだけ
class FooProvider implements ProviderInterface
{
    public function __construct(
        private readonly BarInterface $bar,
        #[Named('config')] private readonly array $config,
    ) {}

    public function get(): Foo
    {
        return new Foo($this->bar, $this->config['timeout']);
    }
}

// Module
$this->bind(Foo::class)->toProvider(FooProvider::class);

// ✅ 推奨: toConstructor束縛（Providerクラス不要）
$this->bind(Foo::class)->toConstructor(
    Foo::class,
    ['timeout' => 'foo_timeout']
);
$this->bind()->annotatedWith('foo_timeout')->toInstance($config['timeout']);
```

**Providerが必要なケース（許容）:**
- 条件分岐による生成（環境によって異なるインスタンス）
- ファクトリパターン（引数に基づく動的生成）
- 遅延初期化が必要な場合
- 外部リソースの接続確立

**Providerが不要なケース（問題）:**
- `get()` 内で単に `new` して返すだけ
- 依存を受け取って渡すだけの中継

| パターン | 評価 |
|----------|------|
| `toConstructor` で済む | ✅ 推奨 |
| 単純な `new` だけの Provider | ❌ 過剰（toConstructorを使用） |
| 条件分岐のある Provider | ✅ 許容 |
| ファクトリ的な Provider | ✅ 許容 |

#### グローバル参照禁止

`define`定数、staticメソッド直接呼び出しは禁止。

```php
// ❌ 問題
$value = SOME_CONSTANT;
$result = SomeClass::staticMethod();

// ✅ 推奨: 注入
public function __construct(
    #[Named('SOME_VALUE')] private readonly string $value,
    private readonly SomeService $service,
)
```

#### バリデーション（JsonSchema）

入力バリデーションはJsonSchemaで宣言的に行う。

```php
// ❌ 問題: 手動バリデーション
public function onPost(array $data): static
{
    if (empty($data['title'])) {
        throw new InvalidArgumentException();
    }
    // ...
}

// ✅ 推奨: JsonSchemaで宣言
#[JsonSchema(schema: 'article.post.json')]
public function onPost(string $title, string $body): static
```

```json
// var/json_schema/article.post.json
{
  "type": "object",
  "required": ["title", "body"],
  "properties": {
    "title": {"type": "string", "minLength": 1, "maxLength": 255},
    "body": {"type": "string", "minLength": 1}
  }
}
```

#### AOP（インターセプター）

横断的関心事はインターセプターで分離。リソースに直接書かない。

```php
// ❌ 問題: リソースに横断的関心事
public function onPost(array $data): static
{
    $this->logger->info('Creating article');
    $start = microtime(true);
    // ビジネスロジック
    $this->logger->info('Created', ['time' => microtime(true) - $start]);
}

// ✅ 推奨: インターセプターで分離
// Module:
$this->bindInterceptor(
    $this->matcher->subclassesOf(ResourceObject::class),
    $this->matcher->startsWith('on'),
    [LogInterceptor::class]
);
```

| 用途 | 実装場所 |
|------|---------|
| ロギング | インターセプター |
| トランザクション | インターセプター |
| 認証チェック | インターセプター |
| キャッシュ | `#[Cacheable]` |
| バリデーション | `#[JsonSchema]` |

#### 認証・認可

認証はインターセプターまたはミドルウェアで。リソース内に認証ロジックを書かない。

```php
// ❌ 問題: リソース内で認証チェック
public function onGet(int $id): static
{
    if (!$this->auth->isLoggedIn()) {
        $this->code = 401;
        return $this;
    }
    // ...
}

// ✅ 推奨: アトリビュート + インターセプター
#[RequireLogin]
public function onGet(int $id): static

// ✅ 推奨: ロールベース
#[RequireRole('admin')]
public function onDelete(int $id): static
```

| パターン | 評価 |
|----------|------|
| カスタム属性 + インターセプター | ✅ 推奨 |
| ミドルウェア | ✅ 推奨 |
| リソース内で直接チェック | ❌ 問題 |

### 6. エラーハンドリング（追加観点）

#### 空のcatchブロック

例外を捕捉して何もしないのは問題。エラーが握りつぶされる。

```php
// ❌ 問題: 空のcatch（エラー握りつぶし）
try {
    $this->externalApi->call();
} catch (ApiException $e) {
    // 何もしない
}

// ❌ 問題: ログだけして処理続行（意図が不明）
try {
    $result = $this->riskyOperation();
} catch (Exception $e) {
    $this->logger->error($e->getMessage());
}
// $result は未定義のまま続行...

// ✅ 推奨: 意図を明確に
try {
    $this->externalApi->call();
} catch (ApiException $e) {
    // 外部API失敗時はフォールバック値を使用（意図をコメント）
    return $this->getFallbackData();
}

// ✅ 推奨: 再throw
try {
    $result = $this->riskyOperation();
} catch (OperationException $e) {
    $this->logger->error('Operation failed', ['exception' => $e]);
    throw new ServiceUnavailableException('Service temporarily unavailable', 0, $e);
}
```

**なぜ問題か:**
- エラーが発生しても気づけない
- デバッグが非常に困難になる
- システムが不正な状態で動き続ける

#### 例外の情報損失

例外を再throwする際に、元の例外情報を失わない。

```php
// ❌ 問題: 元の例外情報が失われる
try {
    $this->repository->save($entity);
} catch (DatabaseException $e) {
    throw new SaveFailedException('保存に失敗しました');  // 元の情報なし
}

// ❌ 問題: メッセージだけ引き継ぐ
catch (DatabaseException $e) {
    throw new SaveFailedException($e->getMessage());  // スタックトレースなし
}

// ✅ 推奨: 元の例外をチェーン
try {
    $this->repository->save($entity);
} catch (DatabaseException $e) {
    throw new SaveFailedException('保存に失敗しました', 0, $e);  // 第3引数で元例外を保持
}
```

**なぜ問題か:**
- 本当の原因がわからなくなる
- スタックトレースが切れてデバッグ困難
- 本番障害時に原因特定ができない

#### エラーメッセージに文脈情報を含める

```php
// ❌ 問題: 情報が少なすぎる
throw new NotFoundException('見つかりません');
throw new ValidationException('無効な値です');

// ✅ 推奨: 文脈情報を含める
throw new ArticleNotFoundException("Article not found: id={$id}");
throw new ValidationException("Invalid email format: {$email}");
```

### 7. コメントとドキュメント

#### 不要なコメント

コードを読めばわかることをコメントしない。

```php
// ❌ 問題: コードを読めばわかる
// 記事を取得する
$article = $this->articleQuery->item($id);

// IDをチェックする
if ($id <= 0) {
    throw new InvalidIdException();
}

// 1を足す
$count++;

// ✅ 推奨: 「なぜ」を説明する（必要な場合のみ）
// 下位互換性のため、削除フラグではなく物理削除
$this->repository->hardDelete($id);

// レガシーAPIの仕様により、日付フォーマットはY/m/d固定
$formattedDate = $date->format('Y/m/d');
```

**不要なコメントの例:**
- 変数宣言の説明
- 自明なメソッド呼び出しの説明
- インクリメント/デクリメントの説明
- ループの説明（「配列をループ」等）

**必要なコメントの例:**
- なぜそうしているかの理由
- ビジネスルールの説明
- 外部システムとの制約
- 一時的な対処（TODO付き）

#### 古いコメント（コードと不一致）

コードを変更したらコメントも更新する。古いコメントは嘘になる。

```php
// ❌ 問題: コメントとコードが不一致
// ユーザーIDでフィルタリング
$articles = $this->query->findByCategoryId($categoryId);  // 実際はカテゴリID

// 最大10件取得
$results = $this->query->list(50);  // 実際は50件

// ✅ 推奨: コメントを更新するか、削除する
$articles = $this->query->findByCategoryId($categoryId);
$results = $this->query->list(50);
```

**なぜ問題か:**
- 読む人が混乱する
- 古いコメントを信じてバグを作り込む
- 「コメントは信用できない」という文化ができる

#### コメントアウトされたコード

コメントアウトしたコードは削除する。Gitに履歴がある。

```php
// ❌ 問題: コメントアウトされたコード
public function onGet(int $id): static
{
    $article = $this->articleQuery->item($id);
    // $oldData = $this->legacyQuery->getData($id);
    // if ($oldData !== null) {
    //     $article = $this->merge($article, $oldData);
    // }
    $this->body = ['article' => $article];
    return $this;
}

// ✅ 推奨: 削除する（必要ならGitから復元）
public function onGet(int $id): static
{
    $article = $this->articleQuery->item($id);
    $this->body = ['article' => $article];
    return $this;
}
```

**なぜ問題か:**
- 読む人が「いつか使うのか？」と混乱
- コードが汚れて可読性低下
- 「このコードは何のため？」と考える時間の無駄
- Gitに履歴があるので削除しても問題ない

#### TODO/FIXMEの放置

TODO/FIXMEは期限と担当を明記。放置しない。

```php
// ❌ 問題: 放置されたTODO
// TODO: あとで直す
// FIXME: なんかおかしい
// HACK: 一時的な対応

// ✅ 推奨: 具体的に書く、またはIssue化して削除
// TODO(2024-03): Phase2でキャッシュ実装予定 (Issue #123)
// FIXME: 外部APIのバグ回避。API v2移行時に削除 (Issue #456)
```

| パターン | 評価 |
|----------|------|
| TODO + Issue番号 + 期限 | ✅ OK |
| TODO のみ（放置） | ⚠️ Issue化推奨 |
| 1年以上前のTODO | ❌ 対応するか削除 |

### 8. マジック値

#### マジックナンバー

意味のある数値は定数化する。

```php
// ❌ 問題: マジックナンバー
if ($retryCount > 3) { }
$timeout = 30;
$pageSize = 20;
if ($status === 1) { }

// ✅ 推奨: 定数化
private const MAX_RETRY_COUNT = 3;
private const DEFAULT_TIMEOUT_SECONDS = 30;
private const DEFAULT_PAGE_SIZE = 20;

if ($retryCount > self::MAX_RETRY_COUNT) { }

// ✅ または: Enum
enum ArticleStatus: int {
    case Draft = 0;
    case Published = 1;
    case Archived = 2;
}

if ($status === ArticleStatus::Published->value) { }
```

**例外（定数化不要）:**
- `0`, `1`, `-1`（境界値チェック）
- 配列の最初/最後の要素取得
- 数学的に意味がある値（`* 2`, `/ 100`）

**なぜ問題か:**
- 「3って何？」が分からない
- 同じ数値が複数箇所にあると変更漏れ
- 意図が伝わらない

#### マジックストリング

文字列リテラルも定数化を検討。特にキー名やステータス。

```php
// ❌ 問題: マジックストリング
if ($article['status'] === 'published') { }
$this->cache->get('article_' . $id);
$type = 'premium';

// ✅ 推奨: 定数またはEnum
enum ArticleStatus: string {
    case Draft = 'draft';
    case Published = 'published';
}

if ($article['status'] === ArticleStatus::Published->value) { }

// キャッシュキーはメソッド化
private function getArticleCacheKey(int $id): string
{
    return "article_{$id}";
}
```

### 9. 死んだコード

#### 到達不能コード

returnやthrowの後のコードは実行されない。

```php
// ❌ 問題: 到達不能
public function process(): void
{
    return;
    $this->cleanup();  // 絶対に実行されない
}

public function onGet(int $id): static
{
    throw new NotFoundException();
    $this->body = [];  // 絶対に実行されない
    return $this;
}

// ❌ 問題: 常にtrueの条件
if ($value > 0 || $value <= 0) {  // 常にtrue
    // ...
}
```

#### 使われていないコード

- 呼び出されないprivateメソッド
- 使われていない変数
- 読み込まれないuse文

```php
// ❌ 問題: 使われていない
use App\Service\UnusedService;  // 未使用

class ArticleResource extends ResourceObject
{
    private function unusedMethod(): void  // 呼ばれない
    {
        // ...
    }

    public function onGet(int $id): static
    {
        $unusedVariable = 'test';  // 使われない
        $article = $this->query->item($id);
        $this->body = ['article' => $article];
        return $this;
    }
}
```

**対策:**
- IDEやPHPStanの警告を有効に
- `composer sa` で定期チェック
- 使わないコードは削除

### 10. スパゲッティレベル 🍝 と問題サイズ ☕

コードの「絡まり具合」と「問題の大きさ」を測定。数百行のメソッドは教育の不在を示す。

#### 問題サイズ（スタバサイズ）☕

| サイズ | 程度 | 対応 |
|--------|------|------|
| ☕ Short | 軽微 | 余裕があれば対応 |
| ☕ Tall | 小さい | 次のリファクタで対応 |
| ☕ Grande | 中程度 | 計画的に対応 |
| ☕ Venti | 大きい | 優先的に対応 |
| ☕ Trenta | 巨大 | 緊急対応必須 |

**使用例:**
- 「この変数名、Tallくらいの問題ですね」
- 「このメソッドはVenti級のスパゲッティです」
- 「Grandeレベルのリファクタリングが必要」

#### スパゲッティ度の判定 🍝

| レベル | 状態 | 特徴 |
|--------|------|------|
| 🍝 | カルボナーラ（理想） | シンプル、短い、責務明確 |
| 🍝🍝 | ペペロンチーノ | 少し長いが追える |
| 🍝🍝🍝 | ボロネーゼ | 複雑だが分離可能 |
| 🍝🍝🍝🍝 | ナポリタン | 絡まり始め、要リファクタ |
| 🍝🍝🍝🍝🍝 | 闇鍋スパゲッティ | 誰も触れない、負債 |

#### メソッド行数とスパゲッティ度

| 行数 | スパゲッティ | サイズ | アクション |
|------|-------------|--------|-----------|
| 1-20行 | 🍝 | Short | 理想的 |
| 21-50行 | 🍝🍝 | Tall | 許容範囲 |
| 51-100行 | 🍝🍝🍝 | Grande | 分割を検討 |
| 101-200行 | 🍝🍝🍝🍝 | Venti | 要リファクタリング |
| 201行以上 | 🍝🍝🍝🍝🍝 | Trenta | 緊急対応必須 |

#### スパゲッティの兆候

```php
// 🍝🍝🍝🍝🍝 闇鍋スパゲッティの特徴

public function onGet(int $id): static
{
    // 300行のメソッド...

    // 兆候1: 複数の責務が混在
    // データ取得、加工、バリデーション、整形が1メソッドに

    // 兆候2: 深いネスト
    if (...) {
        foreach (...) {
            if (...) {
                while (...) {
                    // 何をしているか追えない
                }
            }
        }
    }

    // 兆候3: 大量のローカル変数
    $a = ...; $b = ...; $c = ...; $d = ...;
    // 20個以上の変数が飛び交う

    // 兆候4: 同じ引数を何度も渡す
    $this->processA($user, $article, $settings, $options);
    $this->processB($user, $article, $settings, $options);
    $this->processC($user, $article, $settings, $options);

    // 兆候5: コメントで区切りを入れないと読めない
    // ====== ここからデータ取得 ======
    // ...
    // ====== ここから加工処理 ======
    // ...
}
```

#### スパゲッティの解消法

```php
// 🍝 カルボナーラへのリファクタリング

// Before: 300行の巨大メソッド
public function onGet(int $id): static { /* 300行 */ }

// After: 責務を分離
public function onGet(int $id): static
{
    $article = $this->articleQuery->item($id);
    if ($article === null) {
        throw new ArticleNotFoundException($id);
    }

    $this->body = $this->articleViewBuilder->build($article);

    return $this;
}

// 詳細はサービスに委譲
class ArticleViewBuilder
{
    public function build(Article $article): array
    {
        return [
            'article' => $article,
            'author' => $this->getAuthor($article),
            'relatedArticles' => $this->getRelatedArticles($article),
            'metadata' => $this->buildMetadata($article),
        ];
    }

    // 各メソッドは20行以内
    private function getAuthor(Article $article): Author { /* 10行 */ }
    private function getRelatedArticles(Article $article): array { /* 15行 */ }
    private function buildMetadata(Article $article): array { /* 10行 */ }
}
```

#### なぜ長いメソッドが生まれるか

| 原因 | 対策 |
|------|------|
| 教育の不在 | このレビュー基準を共有 |
| 時間的プレッシャー | 技術的負債として記録、後でリファクタ |
| 「動いてるから」 | 保守コストを説明 |
| 全体設計の欠如 | 事前の設計レビュー |
| 責務の曖昧さ | 単一責任の原則を徹底 |

#### 長いメソッドの問題

- **テストが書けない**: 300行のメソッドに何パターンのテストが必要？
- **バグが隠れる**: どこで何が起きているかわからない
- **変更が怖い**: 影響範囲が読めない
- **レビューできない**: 誰も全体を把握できない
- **引き継げない**: 新人が理解するのに何日かかる？

### 11. 困った人のコード図鑑 🚨

よく見かける問題パターンとその対処法。

#### 🦸 God Class（神クラス）

何でもやる巨大クラス。1000行超え、20以上のメソッド。

```php
// ❌ 問題: 何でもやるクラス
class ArticleManager
{
    public function create() { }
    public function update() { }
    public function delete() { }
    public function validate() { }
    public function sendNotification() { }
    public function generatePdf() { }
    public function exportCsv() { }
    public function calculateStats() { }
    public function syncToExternalApi() { }
    public function sendEmail() { }
    public function processPayment() { }  // なぜ記事に支払い処理が...
    // さらに30メソッド続く...
}

// ✅ 推奨: 責務ごとに分離
class ArticleRepository { }      // CRUD
class ArticleValidator { }       // バリデーション
class ArticleNotifier { }        // 通知
class ArticleExporter { }        // エクスポート
class ArticleStatsCalculator { } // 統計
```

**兆候:**
- 「〇〇Manager」「〇〇Service」「〇〇Helper」という名前
- コンストラクタの依存が10個以上
- 「このクラスに追加しておけばいいか」という思考

#### 📋 コピペ戦士

同じコードをあちこちにコピペ。修正時に全箇所直す必要あり。

```php
// ❌ 問題: 3箇所に同じコード
// ArticleResource.php
$date = new DateTimeImmutable($article['createdAt']);
$formatted = $date->format('Y年m月d日');

// BlogResource.php
$date = new DateTimeImmutable($blog['createdAt']);
$formatted = $date->format('Y年m月d日');

// NewsResource.php
$date = new DateTimeImmutable($news['createdAt']);
$formatted = $date->format('Y年m月d日');

// ✅ 推奨: 共通化
class DateFormatter
{
    public function toJapanese(string $datetime): string
    {
        return (new DateTimeImmutable($datetime))->format('Y年m月d日');
    }
}
```

**目安:** 同じコードが3箇所以上 → 共通化を検討

#### 🔨 Primitive Obsession（プリミティブ依存症）

何でも `string` / `int` / `array` で表現。型を作らない。

```php
// ❌ 問題: 全部string
public function createUser(
    string $email,           // メールアドレス
    string $phone,           // 電話番号
    string $postalCode,      // 郵便番号
    string $status,          // 'active' | 'inactive' | 'pending'
    int $age,                // 0-150の範囲
    string $gender,          // 'male' | 'female' | 'other'
): void {
    // $email に 'hello' が来ても通る
    // $status に 'banana' が来ても通る
    // $age に -5 が来ても通る
}

// ✅ 推奨: 値オブジェクトで表現
public function createUser(
    Email $email,
    PhoneNumber $phone,
    PostalCode $postalCode,
    UserStatus $status,      // Enum
    Age $age,
    Gender $gender,          // Enum
): void {
    // 不正な値はオブジェクト生成時に弾かれる
}

// 値オブジェクト例
final readonly class Email
{
    public function __construct(
        public string $value
    ) {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }
    }
}
```

**作るべき値オブジェクト:**
- メールアドレス、電話番号、郵便番号
- 金額（Money）
- 日付範囲（DateRange）
- ID類（UserId, ArticleId）

#### 🎭 Boolean Blindness

boolを返すが、trueが何を意味するかわからない。

```php
// ❌ 問題: true/falseの意味が不明
if ($this->check($user, $article)) { }  // 何をチェック？
if ($this->process($data)) { }          // 成功？存在？
if ($user->validate()) { }              // 有効？バリデーション成功？

// ✅ 推奨: メソッド名で意味を明確に
if ($this->canUserEditArticle($user, $article)) { }
if ($this->wasProcessedSuccessfully($data)) { }
if ($user->isValid()) { }

// ✅ または: 結果オブジェクトを返す
$result = $this->validateUser($user);
if ($result->isValid()) { }
foreach ($result->getErrors() as $error) { }
```

#### 🧵 Stringly Typed（文字列型付け）

型の代わりに文字列で何でも表現。

```php
// ❌ 問題: 文字列で型を表現
$user['type'] = 'admin';           // typo で 'adimn' になっても動く
$article['status'] = 'published';  // 'pubilshed' でも通る
$config['mode'] = 'production';    // 何が有効な値かわからない

// ✅ 推奨: Enumで型安全に
enum UserType: string {
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';
}

enum ArticleStatus: string {
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

$user->type = UserType::Admin;  // typoはコンパイルエラー
```

#### 🦥 横着コード

「動けばいい」精神。エラー処理なし、型なし、テストなし。

```php
// ❌ 問題: 横着コード
function getData($id) {
    $data = file_get_contents("https://api.example.com/data/$id");
    return json_decode($data);
    // APIが落ちたら？JSONが壊れてたら？$idが空だったら？
}

// ✅ 推奨: 防御的に書く
function getData(int $id): Data
{
    if ($id <= 0) {
        throw new InvalidArgumentException("Invalid ID: {$id}");
    }

    $response = $this->httpClient->get("/data/{$id}");

    if (! $response->isSuccess()) {
        throw new ApiException("API error: {$response->getStatusCode()}");
    }

    return Data::fromJson($response->getBody());
}
```

#### 🚗 車輪の再発明

標準機能があるのに自作。

```php
// ❌ 問題: 自作
function myArrayMap($array, $callback) {
    $result = [];
    foreach ($array as $item) {
        $result[] = $callback($item);
    }
    return $result;
}

function myJsonEncode($data) {
    // 500行の自作JSONエンコーダー...
}

// ✅ 推奨: 標準関数を使う
array_map($callback, $array);
json_encode($data);
```

**よくある再発明:**
- 日付操作 → Carbon / DateTimeImmutable
- バリデーション → JsonSchema / Symfony Validator
- HTTP クライアント → Guzzle
- コレクション操作 → array_* 関数

#### 🙈 俺にしかわからないコード

暗黙知依存。書いた本人以外理解不能。

```php
// ❌ 問題: 暗黙知だらけ
$x = $this->proc($d, 7, true, null, 'X');
// proc って何？d って何？7 って何？true って何？'X' って何？

// 別ファイルの定数を知らないと読めない
if ($status === 3) { }  // 3 = 公開済み（どこにも書いてない）

// ✅ 推奨: 明示的に
$article = $this->articlePublisher->publish(
    article: $draft,
    publishAt: new DateTimeImmutable('+7 days'),
    notifySubscribers: true,
    embargo: null,
    visibility: Visibility::Public,
);

if ($status === ArticleStatus::Published) { }
```

#### 🔧 略語マニア

過度な省略で意味不明。

```php
// ❌ 問題: 略しすぎ
$usrAccMgr->procTxn($txnDt, $amt, $curr);
$artCtgSvc->getActCatLst();
$cfgHndlr->ldSysCfg();

// ✅ 推奨: 読めるように
$userAccountManager->processTransaction($transactionDate, $amount, $currency);
$articleCategoryService->getActiveCategoryList();
$configHandler->loadSystemConfig();
```

**許容される略語:**
- `id`, `url`, `html`, `json`, `api`
- 業界標準の略語

**避けるべき略語:**
- `mgr`, `svc`, `hndlr`, `proc`, `cfg`, `usr`, `amt`

#### 🦠 Feature Envy（他クラス依存症）

他のクラスのデータばかり使う。

```php
// ❌ 問題: Order のデータを使いまくる
class InvoiceGenerator
{
    public function generate(Order $order): Invoice
    {
        $subtotal = 0;
        foreach ($order->getItems() as $item) {
            $subtotal += $item->getPrice() * $item->getQuantity();
        }
        $tax = $subtotal * $order->getTaxRate();
        $shipping = $order->getShippingAddress()->getShippingCost();
        $total = $subtotal + $tax + $shipping;
        // ...
    }
}

// ✅ 推奨: Order に計算を任せる
class Order
{
    public function getSubtotal(): Money { }
    public function getTax(): Money { }
    public function getShippingCost(): Money { }
    public function getTotal(): Money { }
}

class InvoiceGenerator
{
    public function generate(Order $order): Invoice
    {
        return new Invoice(
            subtotal: $order->getSubtotal(),
            tax: $order->getTax(),
            shipping: $order->getShippingCost(),
            total: $order->getTotal(),
        );
    }
}
```

#### 🐙 メソッド追加マン（クラス作れない症候群）

新しいクラスを作れず、既存クラスにメソッドを追加し続ける。

```php
// ❌ 問題: 1つのクラスにメソッドを追加し続ける
class ArticleResource extends ResourceObject
{
    // 最初は普通だった
    public function onGet(int $id): static { }
    public function onPost(string $title): static { }

    // 「関連機能だから」と追加
    private function formatDate($date) { }
    private function sanitizeHtml($html) { }
    private function generateSlug($title) { }

    // 「ここにあると便利だから」と追加
    private function sendNotification($userId) { }
    private function updateSearchIndex($article) { }
    private function invalidateCache($id) { }

    // 「一箇所にまとまってた方が」と追加
    private function calculateReadingTime($content) { }
    private function extractKeywords($content) { }
    private function generateOgImage($article) { }

    // 気づいたら30メソッド...
}

// ✅ 推奨: 責務ごとにクラスを分離
class ArticleResource extends ResourceObject
{
    public function __construct(
        private readonly ArticleQuery $query,
        private readonly ArticleCommand $command,
    ) {}

    public function onGet(int $id): static { }
    public function onPost(string $title): static { }
}

// 別クラスに分離
class DateFormatter { public function format($date): string { } }
class HtmlSanitizer { public function sanitize($html): string { } }
class SlugGenerator { public function generate($title): string { } }
class ArticleNotifier { public function notify($userId): void { } }
class SearchIndexer { public function update($article): void { } }
class CacheInvalidator { public function invalidate($id): void { } }
class ReadingTimeCalculator { public function calculate($content): int { } }
class KeywordExtractor { public function extract($content): array { } }
class OgImageGenerator { public function generate($article): string { } }
```

**なぜクラスを作れないか:**
- 「ファイルが増えるのが嫌」→ 1ファイル1000行より10ファイル100行の方が良い
- 「どこに置けばいいかわからない」→ 責務に合ったディレクトリを作る
- 「クラス名が思いつかない」→ 動詞+名詞（`SlugGenerator`, `CacheInvalidator`）
- 「小さすぎる気がする」→ 小さいクラスは良いクラス
- 「依存が増える」→ DIで解決、テストしやすくなる

**クラス分離の目安:**
- privateメソッドが5つ以上 → 分離を検討
- 「〇〇のための処理」とコメントがある → そこで分離
- 別の場所でも使いたい → 即分離

#### 📦 Mixed脳（`array<string, mixed>` 依存症）

`mixed` で型を放棄。何が入っているかわからない配列を引き回す。

**注意:** 問題は `array` ではなく `mixed`。BEAR.Sundayは配列を多用するが、型付き配列なら問題ない。

```php
// ❌ 問題: mixed だらけ（何が入っているかわからない）
/** @param array<string, mixed> $user */
function processUser(array $user): array {
    // $user['name'] は string? int? null? array?
    // $user['address'] は何？
    // 誰にもわからない...
}

/** @return array<string, mixed> */
function getArticle(int $id): array {
    // 何が返ってくるの？
}

// ✅ OK: 型付き配列（構造が明確）
/** @return array{id: int, title: string, body: string} */
function getArticle(int $id): array { }

/** @return array<Article> */
function getArticles(): array { }

/** @param array{name: string, email: string} $input */
function createUser(array $input): void { }

// ✅ OK: 配列は大きくても深くてもよい（型があれば）
/**
 * @return array{
 *     article: array{id: int, title: string, body: string},
 *     author: array{id: int, name: string},
 *     comments: array<array{id: int, body: string, user: string}>,
 *     metadata: array{views: int, likes: int}
 * }
 */
function getArticleDetail(int $id): array { }
```

**問題は `mixed` であって `array` ではない:**

| 書き方 | 評価 |
|--------|------|
| `array<string, mixed>` | ❌ 何が入ってるかわからない |
| `array<int, mixed>` | ❌ 同上 |
| `mixed` | ❌ 型の放棄 |
| `array{id: int, name: string}` | ✅ 構造が明確 |
| `array<Article>` | ✅ 要素の型が明確 |
| `array<string, int>` | ✅ キーと値の型が明確 |

**`mixed` を使いたくなったら:**

```php
// ❌ mixed に逃げる
/** @param mixed $data */
function process($data): void { }

// ✅ Union型で明示
function process(Article|Comment|User $entity): void { }

// ✅ インターフェースで抽象化
function process(EntityInterface $entity): void { }

// ✅ PHPDocで構造を明示
/** @param array{type: string, payload: array{id: int}} $event */
function process(array $event): void { }
```

#### 📜 名前が文章になってるマン

変数名やメソッド名が文章。テストじゃないんだから！

```php
// ❌ 問題: 文章になってる変数名
$userWhoHasAdminRoleAndIsCurrentlyActive = $this->findUser($id);
$articlesPublishedInLastWeekWithMoreThanTenComments = $this->query->find();
$shouldSendNotificationEmailToUserAfterRegistration = true;

// ❌ 問題: 文章になってるメソッド名
public function getUserByIdAndStatusAndRoleAndCreatedAtBetween(
    int $id,
    string $status,
    string $role,
    DateTimeImmutable $from,
    DateTimeImmutable $to
) { }

public function findAllArticlesThatArePublishedAndHaveCommentsEnabled() { }
public function checkIfUserCanAccessResourceAndHasPermission() { }

// ✅ 推奨: シンプルに
$adminUser = $this->findUser($id);
$recentPopularArticles = $this->query->find();
$shouldNotify = true;

public function find(int $id): ?User { }
public function findBy(UserCriteria $criteria): array { }
public function canAccess(User $user, Resource $resource): bool { }
```

**テストなら許容:**

```php
// ✅ OK: テストメソッド名は説明的で良い
public function testUserCannotAccessAdminPageWithoutAdminRole(): void { }
public function testArticleIsPublishedWhenStatusChangesToPublished(): void { }

// ❌ NG: 本番コードで文章
public function getUserCannotAccessAdminPageWithoutAdminRole(): bool { }
```

**なぜ問題か:**
- 読むのに時間がかかる
- 変更するたびに名前を変える必要がある
- 名前が長すぎて1行に収まらない

#### 🏷️ なんでもManager/Resolver/Data

「名前が思いつかない？Managerで！」→ 全部管理、全部解決、全部データ。

```php
// ❌ 問題: 意味のない名前
class ArticleManager { }      // 何を manage するの？
class DataResolver { }        // 何を resolve するの？
class UserData { }            // Data って何？Entity？DTO？
class ContentHandler { }      // 何を handle？
class InfoProcessor { }       // 何の info を process？
class ItemHelper { }          // 何を help？
class ServiceUtils { }        // Utils って何でも入れていい箱？

// 結果: 何でも入る God Class になる
class ArticleManager
{
    public function create() { }
    public function update() { }
    public function delete() { }
    public function validate() { }
    public function export() { }
    public function notify() { }
    // Manager だから何でも manage できる！
}

// ✅ 推奨: 責務を表す具体的な名前
class ArticleRepository { }      // DBアクセス
class ArticleValidator { }       // バリデーション
class ArticleExporter { }        // エクスポート
class ArticlePublisher { }       // 公開処理
```

**避けるべき曖昧な接尾辞:**

| 接尾辞 | 問題 | 代替案 |
|--------|------|--------|
| `Manager` | 何でも入る | Publisher, Validator, Repository |
| `Handler` | 何を handle? | Parser, Processor |
| `Resolver` | 何を resolve? | PathResolver, DependencyResolver |
| `Helper` | 何を help? | クラス自体が不要かも |
| `Utils` | ゴミ箱 | 個別クラスに分離 |
| `Data` | 何のデータ? | Entity, Dto, Input, Response |
| `Info` | 曖昧 | Details, Metadata, Summary |

#### 🚩 Boolでメソッド統合マン

「2つのメソッド？boolで1つにまとめよう！」→ 1メソッド2責務。

```php
// ❌ 問題: boolで動作が変わる
class ArticleRepository
{
    public function find(int $id, bool $withComments = false): Article
    {
        $article = $this->query->find($id);
        if ($withComments) {
            $article->comments = $this->commentQuery->findByArticle($id);
        }
        return $article;
    }

    public function getList(bool $onlyPublished = true, bool $withAuthor = false): array
    {
        // bool が増えていく...
    }
}

// 呼び出し側で意味不明
$article = $repo->find($id, true);   // true って何？
$list = $repo->getList(true, false); // 何がなんだか...

// ✅ 推奨: メソッドを分ける
class ArticleRepository
{
    public function find(int $id): Article { }
    public function findWithComments(int $id): Article { }
}
```

**boolフラグの問題:**
- 呼び出し側で `true/false` の意味がわからない
- 1メソッドが2つの責務を持つ
- フラグが増殖する（`$withA`, `$withB`, `$withC`...）

**リファクタリング:**

| Before | After |
|--------|-------|
| `find($id, true)` | `findWithComments($id)` |
| `save($data, true)` | `saveAsDraft($data)` |
| `delete($id, false)` | `softDelete($id)` / `hardDelete($id)` |

#### 🧰 親クラスにユーティリティてんこもり

「便利だから親クラスに入れとこう！」→ 継承で共有しようとしすぎ。

```php
// ❌ 問題: 親クラスがユーティリティ集になる
abstract class BaseResource extends ResourceObject
{
    // 「みんな使うから」と追加されていく
    protected function formatDate(\DateTimeInterface $date): string { }
    protected function sanitizeHtml(string $html): string { }
    protected function generateSlug(string $title): string { }
    protected function truncate(string $text, int $length): string { }
    protected function toJson(array $data): string { }
    protected function fromJson(string $json): array { }
    protected function encrypt(string $data): string { }
    protected function decrypt(string $data): string { }
    // 50個のprotectedメソッド...
}

class ArticleResource extends BaseResource
{
    public function onGet(int $id): static
    {
        $article = $this->query->find($id);
        $article['slug'] = $this->generateSlug($article['title']);  // 親のメソッド
        $article['body'] = $this->sanitizeHtml($article['body']);   // 親のメソッド
    }
}
// 問題: ArticleResource は slug も sanitize も「できる」ことになる（責務過多）

// ✅ 推奨: 独立したサービスに分離して注入
final class ArticleResource extends ResourceObject
{
    public function __construct(
        private readonly SlugGenerator $slugGenerator,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function onGet(int $id): static
    {
        $article = $this->query->find($id);
        $article['slug'] = $this->slugGenerator->generate($article['title']);
        $article['body'] = $this->sanitizer->sanitize($article['body']);
    }
}
```

**なぜ問題か:**
- 親クラスが肥大化（God Class化）
- 使わないメソッドも継承される
- 「どこで使われてるかわからない」protected地獄
- テストで親クラス全体を考慮する必要

**親クラスに置いていいもの:**
- フレームワークが要求するもの
- 本当に全子クラスで使う抽象メソッド
- それ以外は **注入**

#### 🔓 Final嫌い（継承キング）

「finalつけないで！拡張できなくなるから！」→ 継承で解決しようとしすぎ。

```php
// ❌ 問題: 「拡張できるように」と final を避ける
class ArticleRepository  // final つけたくない...
{
    public function find(int $id): ?Article { }
    public function save(Article $article): void { }
}

// そして継承で「拡張」
class CachedArticleRepository extends ArticleRepository
{
    public function find(int $id): ?Article
    {
        // キャッシュ処理を追加
        return parent::find($id);
    }
}

class LoggingArticleRepository extends CachedArticleRepository
{
    public function find(int $id): ?Article
    {
        // ログ処理を追加
        return parent::find($id);
    }
}
// 継承の連鎖... 親を変更すると全部壊れる

// ✅ 推奨: final + 合成（Composition）
final class ArticleRepository implements ArticleRepositoryInterface
{
    public function find(int $id): ?Article { }
    public function save(Article $article): void { }
}

// デコレータパターンで機能追加
final class CachedArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $inner,
        private readonly CacheInterface $cache,
    ) {}

    public function find(int $id): ?Article
    {
        return $this->cache->remember(
            "article:{$id}",
            fn() => $this->inner->find($id)
        );
    }
}

// Module で組み合わせ
$this->bind(ArticleRepositoryInterface::class)
    ->toConstructor(CachedArticleRepository::class);
```

**なぜ final を使うべきか:**
- **継承は最も強い結合**: 親の変更が子に波及
- **Liskov置換原則違反しやすい**: 子が親の契約を破る
- **テストが複雑に**: 継承階層全体をテスト
- **合成の方が柔軟**: 組み合わせを自由に変更可能

**「拡張できない」への回答:**

| 反論 | 回答 |
|------|------|
| 「継承できないと拡張できない」 | インターフェース + デコレータで拡張 |
| 「オーバーライドしたい」 | 元のクラスを修正するか、別実装を作る |
| 「ちょっとだけ変えたい」 | それは元のクラスの責務が大きすぎる兆候 |
| 「テストでモックしたい」 | インターフェースに依存すればモック可能 |

```php
// ✅ final でも拡張可能
interface ArticleRepositoryInterface { }

final class ArticleRepository implements ArticleRepositoryInterface { }
final class InMemoryArticleRepository implements ArticleRepositoryInterface { }  // テスト用
final class CachedArticleRepository implements ArticleRepositoryInterface { }    // 機能追加
```

**継承が許容されるケース:**
- フレームワークが継承を要求（`extends ResourceObject`）
- 本当に「is-a」関係がある場合（稀）

#### 🎭 それFacadeじゃないです

「Facadeパターン使ってます！」→ それService LocatorかStatic Proxy。

```php
// Laravel の "Facade"
Cache::get('key');      // これは Facade パターンではない
Log::info('message');   // Static Proxy（または Service Locator）
DB::table('users');     // GoFのFacadeとは別物

// 実際の動作
class Cache extends Facade
{
    // 静的呼び出しを実インスタンスに委譲
    protected static function getFacadeAccessor()
    {
        return 'cache';  // コンテナから取得
    }
}
// → Service Locator + Static Proxy
```

**本当のFacadeパターン（GoF）:**

```php
// ✅ 本物のFacade: 複雑なサブシステムを単純なインターフェースで隠蔽
class OrderFacade
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentService $payment,
        private readonly ShippingService $shipping,
        private readonly NotificationService $notification,
    ) {}

    // 複雑な処理を1つのメソッドに
    public function placeOrder(Order $order): OrderResult
    {
        $this->inventory->reserve($order->items);
        $payment = $this->payment->charge($order->total);
        $this->shipping->schedule($order);
        $this->notification->sendConfirmation($order);

        return new OrderResult($order, $payment);
    }
}
```

**用語の整理:**

| 呼び方 | 実際のパターン |
|--------|---------------|
| Laravel Facade | Static Proxy + Service Locator |
| GoF Facade | サブシステムの単純化 |

**なぜ問題か:**
- 用語の誤用が広まる
- 本当のFacadeを知らないまま
- 「Facade使ってる」で思考停止

**BEAR.Sundayでは:**
- DIで依存を注入 → テスト可能
- 静的呼び出し不要 → 明示的な依存

#### 🔮 補完が効かないコード

IDE「...」→ 開発効率ガタ落ち。

```php
// ❌ 問題: 補完が効かない
$data = $this->getData();  // mixed が返る
$data['user']['name'];     // 補完なし、typoしても気づかない

$user = $container->get('user');  // 何が返る？
$user->getName();  // 補完なし

$article->$dynamicProperty;  // 動的プロパティ
$service->$methodName();     // 動的メソッド

// マジックメソッド地獄
class Config
{
    public function __get($name) { return $this->data[$name]; }
    public function __call($name, $args) { /* ... */ }
}
$config->database->host;  // 補完なし

// ✅ 推奨: 型を明示
/** @return array{user: array{id: int, name: string}} */
public function getData(): array { }

public function getUser(): User { }  // 返り値型

// コンテナも型付きで
public function __construct(
    private readonly UserRepositoryInterface $userRepository,  // 補完効く！
) {}
```

**補完が効かなくなる原因:**

| 原因 | 対策 |
|------|------|
| `mixed` 返り値 | 具体的な型を返す |
| `array<string, mixed>` | 型付き配列 or オブジェクト |
| `$container->get('name')` | コンストラクタ注入 |
| `__get` / `__call` | 通常のプロパティ/メソッド |
| 動的プロパティ | readonly プロパティ |
| 文字列でクラス名 | `::class` 定数 |

```php
// ❌ 文字列でクラス名
$container->get('App\Service\UserService');

// ✅ ::class で補完 + リファクタリング安全
$container->get(UserService::class);
```

**なぜ重要か:**
- 補完なし = タイポし放題
- 補完なし = 定義にジャンプできない
- 補完なし = リファクタリングが手作業
- 補完なし = コードを読まないと使えない

#### 📊 静的解析の看板倒れ（Static Analysis Theater）

PHPStan/Psalmを導入しているのに、`mixed`だらけで何も検出できない状態。「静的解析使ってます！」という形だけのセキュリティブランケット。

```php
// ❌ 問題: mixedだらけで静的解析が無意味
// phpstan.neon: level: 5 なのに...

class DataProcessor
{
    /** @var mixed */
    private $data;

    /** @param mixed $input */
    public function process($input): mixed
    {
        /** @var mixed $result */
        $result = $this->transform($input);
        return $result;
    }

    /** @phpstan-ignore-next-line */
    private function transform($data)
    {
        return $data['items'] ?? [];  // 何が来るかわからない
    }
}

// さらにひどいケース:
/**
 * @psalm-suppress all
 * @phpstan-ignore-next-line
 */
function doSomething($x) {
    return $x->foo()->bar()->baz();  // 型? 知らんがな
}

// ✅ 推奨: 型を活用する静的解析
class DataProcessor
{
    /** @param list<Article> $articles */
    public function process(array $articles): ProcessResult
    {
        $transformed = array_map(
            fn(Article $article) => $this->transform($article),
            $articles
        );
        return new ProcessResult($transformed);
    }

    private function transform(Article $article): TransformedArticle
    {
        return new TransformedArticle(
            title: $article->title,
            excerpt: mb_substr($article->body, 0, 100)
        );
    }
}
```

**看板倒れのサイン:**
- `@phpstan-ignore-next-line` が10個以上ある
- `@psalm-suppress` を「おまじない」として貼っている
- PHPStan level 0〜3 で「エラー0件」を誇る
- `mixed` の使用率が20%を超えている
- baseline.neon が1000行ある（見なかったことにしたエラー）

**🙈 ignore多すぎをignoreする人:**
```php
// PHPStan: "Too many @phpstan-ignore annotations in this file"
// ↓ 解決策（？）

/** @phpstan-ignore-next-line */
// @phpstan-ignore-next-line が多すぎるという警告をignore

// さらに進化形:
// phpstan.neon
parameters:
    ignoreErrors:
        - '#Too many @phpstan-ignore#'  // ignoreが多い警告をignore
        - '#Ignored error pattern#'      // ignoreしたことをignore
```

エラーを直すのではなく、エラーを隠すことに全力を注ぐ。まるでゴキブリを見なかったことにする人。

**なぜ問題か:**
- 静的解析を入れたコスト（CI時間、学習コスト）だけ払って恩恵ゼロ
- 「静的解析でチェックしてます」という偽りの安心感
- 新規コードも「まあmixedでいいか」になる負のスパイラル
- 本当のバグは本番で発覚する

**解決策:**
```bash
# 現状把握: mixed使用箇所をカウント
grep -r "@var mixed\|: mixed\|@param mixed\|@return mixed" src/ | wc -l

# PHPStanレベルを1つ上げてエラーを確認
# 一気に上げず、1レベルずつ対応する
```

**本気の静的解析:**
- PHPStan/Psalm level 6以上を目標に
- `mixed` を使う場合は必ずコメントで理由を書く
- 新規コードは `mixed` 禁止をレビューで徹底
- CIでbaselineの行数増加を検知してブロック

#### 🥤 Static Cola（静的メソッド中毒）

何でも静的メソッドで呼ぶ。テスト不能、差し替え不能。

```php
// ❌ 問題: 静的メソッドだらけ
class ArticleResource extends ResourceObject
{
    public function onGet(int $id): static
    {
        $article = ArticleRepository::find($id);        // static
        $formatted = DateHelper::format($article->createdAt);  // static
        $html = HtmlPurifier::clean($article->body);    // static
        Logger::info('Article viewed', ['id' => $id]);  // static
        Cache::remember("article:{$id}", $article);     // static

        $this->body = ['article' => $article];
        return $this;
    }
}

// 問題点:
// - テストでモックできない（本物のDBにアクセスする）
// - 実装を差し替えられない（キャッシュをRedisに変えたい等）
// - 隠れた依存関係（コンストラクタを見てもわからない）
// - グローバル状態への依存

// ✅ 推奨: 依存性注入
class ArticleResource extends ResourceObject
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly DateFormatterInterface $dateFormatter,
        private readonly HtmlPurifierInterface $purifier,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
    ) {}

    public function onGet(int $id): static
    {
        $article = $this->repository->find($id);
        // テストではモックを注入できる
    }
}
```

**静的メソッドが許容されるケース:**

```php
// ✅ OK: ファクトリメソッド（自分自身を返す）
$user = User::fromArray($data);
$date = DateTimeImmutable::createFromFormat('Y-m-d', $str);

// ✅ OK: 純粋関数（副作用なし、外部状態に依存しない）
$hash = Password::hash($plain);  // 入力だけで出力が決まる
$slug = Str::slug($title);       // 状態を持たない

// ✅ OK: 定数的な値
$types = ContentType::all();
```

**静的が問題になるケース:**

| パターン | 問題 |
|----------|------|
| `Repository::find()` | DBアクセスをモックできない |
| `Logger::info()` | ログ出力先を変えられない |
| `Cache::get()` | キャッシュ実装を差し替えられない |
| `Mail::send()` | テストで本当にメール送信される |
| `DateTime::now()` | 時刻固定のテストができない |

**なぜ問題か:**
- **テスト不能**: 本物のDB/API/メールに依存
- **差し替え不能**: 実装を変更できない
- **隠れた依存**: コンストラクタに現れない
- **グローバル状態**: 予測不能な動作

#### 🔗 デメテルの法則違反（電車衝突）

「友達の友達と話すな」→ 違反者「友達の友達の友達もみんな友達！」

長いメソッドチェーンで内部構造に依存。

```php
// ❌ 問題: 電車衝突（Train Wreck）
$city = $order->getCustomer()->getAddress()->getCity();
$managerName = $employee->getDepartment()->getManager()->getName();
$price = $article->getCategory()->getPricing()->getBasePrice()->getValue();

// 問題点:
// - Order が Customer の内部構造を知っている
// - Customer が Address を持つことを知っている
// - Address が city を持つことを知っている
// → 途中のどれかが変わると全部壊れる

// ✅ 推奨: 必要な情報を直接提供
$city = $order->getShippingCity();
$managerName = $employee->getManagerName();
$price = $article->getBasePrice();

// Order 内部で委譲
class Order
{
    public function getShippingCity(): string
    {
        return $this->customer->getAddress()->getCity();
        // 内部構造の知識はここに閉じ込める
    }
}
```

**デメテルの法則:**
- メソッドは以下のオブジェクトのメソッドのみ呼べる
  - 自分自身 (`$this`)
  - 引数で渡されたオブジェクト
  - 自分が生成したオブジェクト
  - 自分のフィールド

```php
// ❌ 違反: 引数の中身の中身にアクセス
public function process(Order $order): void
{
    $city = $order->getCustomer()->getAddress()->getCity();
}

// ✅ 遵守: 引数に直接聞く
public function process(Order $order): void
{
    $city = $order->getShippingCity();
}
```

**例外（許容されるケース）:**
- Fluent Interface / Builder パターン
- 値オブジェクトのチェーン
- コレクション操作

```php
// ✅ OK: Fluent Interface（同じオブジェクトを返す）
$query->where('status', 'active')
      ->orderBy('created_at')
      ->limit(10);

// ✅ OK: 値オブジェクトのチェーン（イミュータブル）
$price->multiply(1.1)->round()->format();

// ✅ OK: コレクション操作
$users->filter(fn($u) => $u->isActive())
      ->map(fn($u) => $u->getName())
      ->toArray();
```

**なぜ問題か:**
- 内部構造の変更が波及する
- テストが困難（モックの連鎖）
- 結合度が高くなる

#### 🔄 for一筋（イテレーター/ジェネレーター知らず）

何でもforループで書く。イテレーターもジェネレーターも知らない。メモリ？ 知らん。

```php
// ❌ 問題: 全部メモリに載せる
class ReportGenerator
{
    public function generateLargeReport(): array
    {
        $results = [];
        for ($i = 0; $i < 1000000; $i++) {
            $row = $this->fetchRow($i);
            $results[] = $this->processRow($row);  // 100万件メモリに
        }
        return $results;  // メモリ爆発💥
    }

    public function processUsers(array $users): array
    {
        $processed = [];
        for ($i = 0; $i < count($users); $i++) {  // count()を毎回呼ぶ
            $user = $users[$i];
            $processed[] = [
                'name' => $user['name'],
                'email' => $user['email'],
            ];
        }
        return $processed;
    }
}

// ✅ 推奨: ジェネレーターで遅延評価
class ReportGenerator
{
    public function generateLargeReport(): Generator
    {
        for ($i = 0; $i < 1000000; $i++) {
            yield $this->processRow($this->fetchRow($i));
            // 1件ずつ処理、メモリは最小限
        }
    }

    /** @param iterable<User> $users */
    public function processUsers(iterable $users): Generator
    {
        foreach ($users as $user) {
            yield new ProcessedUser(
                name: $user->name,
                email: $user->email,
            );
        }
    }
}

// 使用側
foreach ($generator->generateLargeReport() as $row) {
    $this->output($row);  // 1件ずつ処理
}
```

**for一筋の症状:**
```php
// 症状1: インデックスへの執着
for ($i = 0; $i < count($arr); $i++) { ... }
// → foreach ($arr as $item) { ... }

// 症状2: 手動イテレーション
$keys = array_keys($map);
for ($i = 0; $i < count($keys); $i++) {
    $value = $map[$keys[$i]];
}
// → foreach ($map as $key => $value) { ... }

// 症状3: 配列構築の繰り返し
$result = [];
for (...) { $result[] = transform($item); }
// → array_map(fn($item) => transform($item), $items)

// 症状4: フィルタリング
$filtered = [];
for (...) { if ($cond) $filtered[] = $item; }
// → array_filter($items, fn($item) => $cond)
```

**なぜジェネレーターを使うべきか:**
- メモリ効率: 100万件でも1件分のメモリ
- 遅延評価: 必要になるまで処理しない
- 無限シーケンス: `while(true)` でも問題なし
- パイプライン: 複数のジェネレーターを連結可能

**イテレーターの活用:**
```php
// ファイルを1行ずつ（メモリ効率良い）
$lines = new SplFileObject('huge.csv');
foreach ($lines as $line) { ... }

// ディレクトリ走査
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path)
);

// 日付範囲
$period = new DatePeriod($start, new DateInterval('P1D'), $end);
foreach ($period as $date) { ... }
```

#### 🐘 全部載せてからフィルター（PHP脳フィルタリング）

DBから全件取得 → PHPの配列関数でフィルタリング。SQLのWHEREを知らないかのような実装。

```php
// ❌ 問題: 全部取ってきてPHPでフィルター
class UserRepository
{
    public function findActiveUsers(): array
    {
        // 10万ユーザー全部取得
        $allUsers = $this->query->list();  // SELECT * FROM users

        // PHPでフィルタリング
        $activeUsers = array_filter(
            $allUsers,
            fn($user) => $user['status'] === 'active'
        );

        return array_values($activeUsers);
    }

    public function getUserEmails(): array
    {
        $allUsers = $this->query->list();  // 全カラム取得
        return array_column($allUsers, 'email');  // emailだけ使う
    }

    public function findUsersByAge(int $minAge): array
    {
        $allUsers = $this->query->list();
        return array_filter(
            $allUsers,
            fn($u) => $u['age'] >= $minAge
        );
    }
}

// ✅ 推奨: SQLでフィルタリング
class UserRepository
{
    public function findActiveUsers(): array
    {
        // SQLでフィルタリング（インデックス活用）
        return $this->query->listActive();
        // SELECT * FROM users WHERE status = 'active'
    }

    public function getUserEmails(): array
    {
        return $this->query->listEmails();
        // SELECT email FROM users
    }

    public function findUsersByAge(int $minAge): array
    {
        return $this->query->listByMinAge($minAge);
        // SELECT * FROM users WHERE age >= :minAge
    }
}
```

**PHP脳フィルタリングの症状:**
```php
// 症状1: array_filter でWHERE句を再実装
$users = $query->list();
$filtered = array_filter($users, fn($u) => $u['role'] === 'admin');
// → SELECT * FROM users WHERE role = 'admin'

// 症状2: array_column で特定カラムだけ抽出
$users = $query->list();  // SELECT * で全カラム
$ids = array_column($users, 'id');
// → SELECT id FROM users

// 症状3: array_slice でLIMIT
$users = $query->list();  // 全件取得
$first10 = array_slice($users, 0, 10);
// → SELECT * FROM users LIMIT 10

// 症状4: array_unique で重複排除
$items = $query->list();
$unique = array_unique(array_column($items, 'category'));
// → SELECT DISTINCT category FROM items

// 症状5: usort でソート
$users = $query->list();
usort($users, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
// → SELECT * FROM users ORDER BY created_at DESC
```

**なぜ問題か:**
- **メモリ爆発**: 10万件を配列に載せるとメモリ枯渇
- **インデックス無視**: DBのインデックスが活用されない
- **ネットワーク負荷**: 不要なデータまで転送
- **スケールしない**: データ増加で破綻

**例外（PHPでのフィルタリングが適切な場合）:**
```php
// ✅ OK: 既に取得済みの小さなデータセット内での操作
$orderItems = $order->getItems();  // 1注文の商品（数十件）
$expensiveItems = array_filter($items, fn($i) => $i->price > 10000);

// ✅ OK: 複雑なビジネスロジックでのフィルタリング
$users = $query->listActive();  // まずSQLで絞り込み
$eligible = array_filter($users, fn($u) => $this->eligibilityChecker->isEligible($u));

// ✅ OK: 複数ソースからの集約後の処理
$merged = array_merge($localUsers, $externalUsers);
$filtered = array_filter($merged, ...);
```

#### 🎰 Setter/Getterマン（カプセル化してるつもり）

全プロパティにsetter/getterを生やす。「privateだからカプセル化できてる」と思い込んでいるのが罪。実態はpublicと同じ。

```php
// ❌ 問題: 全部にsetter/getter
class User
{
    private string $name;
    private string $email;
    private int $age;
    private string $status;
    private ?DateTime $lastLogin;

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }

    public function getAge(): int { return $this->age; }
    public function setAge(int $age): void { $this->age = $age; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }

    public function getLastLogin(): ?DateTime { return $this->lastLogin; }
    public function setLastLogin(?DateTime $lastLogin): void { $this->lastLogin = $lastLogin; }
}

// 使う側: ロジックが外に漏れる
$user->setStatus('suspended');
$user->setLastLogin(null);
// ↑ 「停止」の意味が呼び出し側にある

// ✅ 推奨: 意味のあるメソッドと不変オブジェクト
readonly class User
{
    public function __construct(
        public string $name,
        public string $email,
        public int $age,
        public UserStatus $status,
        public ?DateTime $lastLogin,
    ) {}

    public function suspend(): self
    {
        return new self(
            $this->name,
            $this->email,
            $this->age,
            UserStatus::Suspended,
            null,  // 停止時はログイン日時クリア
        );
    }

    public function activate(): self
    {
        return new self(
            $this->name,
            $this->email,
            $this->age,
            UserStatus::Active,
            new DateTime(),
        );
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}

// 使う側: 意図が明確
$user = $user->suspend();  // 「停止する」という意味
```

**Setter/Getterマンの症状:**
```php
// 症状1: IDEの自動生成を全プロパティに適用
// "Generate Getters and Setters" → 全選択 → OK

// 症状2: setterで不整合な状態を作れる
$order->setStatus('shipped');
$order->setShippedAt(null);  // 出荷済みなのに日時なし？

// 症状3: getterで内部構造を晒す
$items = $order->getItems();
$items[] = $newItem;  // 外部から変更できてしまう

// 症状4: ビジネスロジックが呼び出し側に散らばる
if ($user->getAge() >= 20) {
    $user->setCanDrink(true);
}
// ↑ これはUserクラスの責務
```

**なぜ問題か:**
- **カプセル化の破壊**: private意味なし（実質public）
- **不変条件の崩壊**: 不整合な状態を作れる
- **ロジックの分散**: ビジネスルールが呼び出し側に漏れる
- **変更に弱い**: 内部構造の変更が全箇所に波及

**どうすべきか:**
```php
// 1. readonly + コンストラクタ（PHP 8.1+）
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public function add(Money $other): self
    {
        assert($this->currency === $other->currency);
        return new self($this->amount + $other->amount, $this->currency);
    }
}

// 2. 意味のあるメソッド名
class Account
{
    public function deposit(Money $amount): void { ... }   // ✅ setBalanceではない
    public function withdraw(Money $amount): void { ... }  // ✅ 意図が明確
    public function freeze(): void { ... }                 // ✅ setStatusではない
}

// 3. getterが必要な場合は防御的コピー
public function getItems(): array
{
    return [...$this->items];  // コピーを返す
}
```

#### 📝 CRUD Boy（データモデル脳）

全てがCRUD。ドメインの振る舞いが見えず、データの出し入れしか頭にない。

```php
// ❌ 問題: 全部CRUDで考える
// 「注文をキャンセルする」→ status を 'cancelled' に UPDATE
class OrderResource extends ResourceObject
{
    public function onPut(string $id, string $status): static
    {
        $this->command->update($id, $status);  // UPDATE orders SET status = :status
        return $this;
    }
}
// 呼び出し側
$this->resource->put('app://self/order', ['id' => $id, 'status' => 'cancelled']);

// 何が起きる？
// - 在庫戻す？ 決済キャンセル？ メール送信？
// - 'canclled' とタイポしても通る
// - 'shipped' から 'cancelled' への遷移は許可される？

// ✅ 推奨: ドメインの振る舞いを表現
class OrderResource extends ResourceObject
{
    public function onPost(string $id): static  // cancel アクション
    {
        // ビジネスロジックをドメインに委譲
        $order = $this->query->item($id);

        if ($order === null) {
            throw new ResourceNotFoundException('order');
        }

        if (!$order->canCancel()) {
            throw new BadRequestException('This order cannot be cancelled');
        }

        // キャンセル処理（在庫戻し、決済取消等はドメインイベントで）
        $this->command->cancel($id);

        $this->code = 200;
        return $this;
    }
}
// URI: POST /order/{id}/cancel
// 意図が明確、ルールはドメインが持つ
```

**CRUD Boyの症状:**
```php
// 症状1: 全てがステータス更新
$resource->put('app://self/user', ['id' => $id, 'status' => 'premium']);
// → User::upgradeToPremium() という振る舞いがない

// 症状2: フラグの直接操作
$resource->put('app://self/article', ['id' => $id, 'is_published' => true]);
// → Article::publish() という振る舞いがない

// 症状3: 日付の直接設定
$resource->put('app://self/subscription', ['id' => $id, 'expires_at' => $newDate]);
// → Subscription::extend(Period $period) という振る舞いがない

// 症状4: 複数フィールドの同時更新で状態遷移を表現
$resource->put('app://self/order', [
    'id' => $id,
    'status' => 'shipped',
    'shipped_at' => date('Y-m-d H:i:s'),
    'tracking_number' => $tracking,
]);
// → Order::ship(TrackingNumber $tracking) という振る舞いがない
```

**CRUD vs ドメイン思考:**
| CRUD Boy | ドメイン思考 |
|----------|-------------|
| `UPDATE status = 'cancelled'` | `Order::cancel()` |
| `UPDATE is_published = true` | `Article::publish()` |
| `UPDATE balance = balance + 100` | `Account::deposit(Money)` |
| `INSERT INTO followers` | `User::follow(User)` |
| `DELETE FROM cart_items` | `Cart::clear()` |
| `UPDATE expires_at = ...` | `Subscription::renew()` |

**なぜ問題か:**
- **ルールの散在**: 「キャンセルできる条件」が呼び出し側にバラバラ
- **不整合リスク**: 状態遷移のルールを毎回正しく実装する必要
- **意図の喪失**: なぜその更新をするのかコードから読めない
- **テスト困難**: ビジネスルールのテストが書きにくい

**処方箋:**
```php
// 1. リソースURIで意図を表現
POST /order/{id}/cancel      // キャンセル
POST /order/{id}/ship        // 出荷
POST /article/{id}/publish   // 公開
POST /user/{id}/upgrade      // アップグレード

// 2. ドメインオブジェクトに振る舞いを持たせる
class Order
{
    public function cancel(): void
    {
        if ($this->status === OrderStatus::Shipped) {
            throw new DomainException('出荷済みはキャンセル不可');
        }
        $this->status = OrderStatus::Cancelled;
        $this->cancelledAt = new DateTimeImmutable();
    }
}

// 3. CQRSで読み書きを分離
// Query: データの取得（CRUD的でOK）
// Command: ビジネスアクション（振る舞い）
```

#### 💉 それDIじゃなくてSLだよ（Service Locator）

「DIコンテナ使ってるからDIできてる」と思い込んでいる。コンテナから取り出してるならそれはService Locator。

```php
// ❌ 問題: これはDIじゃない、Service Locator
class OrderResource extends ResourceObject
{
    public function __construct(
        private ContainerInterface $container  // コンテナを注入
    ) {}

    public function onPost(array $data): static
    {
        // メソッド内でコンテナから取得 = Service Locator
        $validator = $this->container->get(ValidatorInterface::class);
        $repository = $this->container->get(OrderRepositoryInterface::class);
        $mailer = $this->container->get(MailerInterface::class);

        $validator->validate($data);
        $order = $repository->save($data);
        $mailer->sendConfirmation($order);

        return $this;
    }
}

// 何が問題？
// - 依存関係がコンストラクタから見えない
// - テストでモックしにくい（コンテナごとモック？）
// - 実際に何に依存してるか実行するまでわからない

// ✅ 推奨: 本物のDI（依存性の注入）
class OrderResource extends ResourceObject
{
    public function __construct(
        private ValidatorInterface $validator,      // 依存が明示的
        private OrderRepositoryInterface $repository,
        private MailerInterface $mailer,
    ) {}

    public function onPost(array $data): static
    {
        $this->validator->validate($data);
        $order = $this->repository->save($data);
        $this->mailer->sendConfirmation($order);

        return $this;
    }
}
// コンストラクタを見れば依存関係が全部わかる
```

**Service Locatorの症状:**
```php
// 症状1: コンテナを注入
public function __construct(ContainerInterface $container)

// 症状2: メソッド内で get()
$service = $this->container->get(SomeService::class);

// 症状3: グローバルなコンテナアクセス
$service = Container::getInstance()->get(SomeService::class);

// 症状4: ファサード経由（実質SL）
$result = DB::query(...);      // Laravelファサード
$user = Auth::user();

// 症状5: make() や resolve() の乱用
$handler = app()->make(Handler::class);
$service = resolve(ServiceInterface::class);
```

**DI vs Service Locator:**
| | DI（依存性注入） | SL（Service Locator） |
|---|---|---|
| 依存の宣言 | コンストラクタで明示 | 実行時に取得 |
| 可視性 | 見ればわかる | 実行するまでわからない |
| テスト | モック注入が容易 | コンテナごとモック |
| 結合度 | 低い | コンテナに依存 |

**なぜService Locatorが問題か:**
- **隠れた依存**: コンストラクタを見ても依存がわからない
- **テスト困難**: コンテナをモックする必要がある
- **実行時エラー**: 存在しないサービスは実行時まで発覚しない
- **IDE支援なし**: コンテナから取得する型が不明

**BEAR.Sundayでの正しいDI:**
```php
// Module で束縛
class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->bind(MailerInterface::class)
             ->to(SmtpMailer::class);
    }
}

// Resource はコンストラクタインジェクションのみ
class OrderResource extends ResourceObject
{
    public function __construct(
        private MailerInterface $mailer,  // 自動注入される
    ) {}
}

// テストではモック注入
$resource = new OrderResource(new FakeMailer());
```

#### 🌍 グローバルランチ（環境分岐症候群）

`if (APP_DEBUG)` や `$_ENV['APP_ENV']` でビジネスロジックの振る舞いを変える。テスト不能、予測不能。

```php
// ❌ 問題: グローバル定数で振る舞い変更
class PaymentService
{
    public function charge(Money $amount): PaymentResult
    {
        if (APP_DEBUG) {
            // 開発環境ではダミー決済
            return new PaymentResult(success: true, transactionId: 'dummy-123');
        }

        if ($_ENV['APP_ENV'] === 'staging') {
            // ステージングではサンドボックス
            return $this->sandboxGateway->charge($amount);
        }

        return $this->gateway->charge($amount);
    }
}

// 何が問題？
// - 本番で動くコードがテストで動かない
// - 環境によって全く違うパスを通る
// - 「本番だけバグ」が起きる

// ✅ 推奨: 環境差異はDIで解決
interface PaymentGatewayInterface
{
    public function charge(Money $amount): PaymentResult;
}

// 本番用
class StripeGateway implements PaymentGatewayInterface { ... }

// 開発/テスト用
class FakeGateway implements PaymentGatewayInterface { ... }

// Moduleで環境別に束縛
class PaymentModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $gateway = $this->appMeta->appDir === 'prod'
            ? StripeGateway::class
            : FakeGateway::class;

        $this->bind(PaymentGatewayInterface::class)->to($gateway);
    }
}

// サービスは環境を知らない
class PaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,  // 何が来るかは知らない
    ) {}

    public function charge(Money $amount): PaymentResult
    {
        return $this->gateway->charge($amount);  // 常に同じコード
    }
}
```

**グローバルランチの症状:**
```php
// 症状1: APP_DEBUG でログ出力切り替え
if (APP_DEBUG) {
    error_log($sensitiveData);  // 本番では出ない（はず）
}

// 症状2: 環境変数で機能ON/OFF
if ($_ENV['FEATURE_X_ENABLED'] === 'true') {
    $this->doNewFeature();
}

// 症状3: 本番だけ特別扱い
if ($_ENV['APP_ENV'] === 'production') {
    $this->sendRealEmail();
} else {
    $this->logEmail();
}

// 症状4: 定数でバリデーション緩和
if (!STRICT_MODE) {
    return true;  // 開発中は通す
}
```

**なぜ問題か:**
- **テスト不能**: 本番パスをテストできない
- **予測不能**: 環境によって違う動作
- **本番バグ**: 開発で通っても本番で落ちる
- **隠れた分岐**: コードを追わないと動作がわからない

**どこならOKか:**
```php
// ✅ OK: ブートストラップ/エントリーポイント
// public/index.php
if (getenv('APP_ENV') === 'development') {
    $module = new DevModule();
} else {
    $module = new ProdModule();
}

// ✅ OK: エラーハンドラーの詳細表示
class ErrorHandler
{
    public function __construct(
        private bool $showDetails,  // 注入される
    ) {}
}

// ✅ OK: Moduleでの束縛切り替え（上記例）
// → ここで吸収すれば、ビジネスロジックは環境を知らない
```

#### 🪆 具象継承マトリョーシカ

具象クラスを具象クラスで継承。BEAR.Sundayではまず見ない。AOPとDecoratorがあるから。

```php
// ❌ 問題: 具象クラスの継承チェーン
class BaseRepository
{
    public function find(int $id): ?array
    {
        return $this->db->fetch($id);
    }

    public function save(array $data): void
    {
        $this->db->insert($data);
    }
}

class CachedRepository extends BaseRepository
{
    public function find(int $id): ?array
    {
        if ($cached = $this->cache->get($id)) {
            return $cached;
        }
        $result = parent::find($id);  // 親に依存
        $this->cache->set($id, $result);
        return $result;
    }
}

class LoggingCachedRepository extends CachedRepository
{
    public function find(int $id): ?array
    {
        $this->logger->info("Finding: $id");
        return parent::find($id);  // 祖父母まで依存
    }
}

// 問題点:
// - 継承順序が固定（ログ→キャッシュ→本体）
// - 親を変更すると全部壊れる（脆弱な基底クラス問題）
// - テストで親をモックできない
// - 機能の組み合わせが継承階層で固定

// ✅ 推奨: BEAR.SundayならAOP
#[CacheableRead]
#[Loggable]
class UserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return $this->query->item($id);
    }
}

// インターセプターで横断的関心事を分離
class CacheInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        $key = $this->buildKey($invocation);
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }
        $result = $invocation->proceed();
        $this->cache->set($key, $result);
        return $result;
    }
}

// ✅ 推奨: または Decorator パターン
interface RepositoryInterface
{
    public function find(int $id): ?array;
}

class DbRepository implements RepositoryInterface { ... }

class CachedRepository implements RepositoryInterface
{
    public function __construct(
        private RepositoryInterface $inner,  // 具象ではなくインターフェース
        private CacheInterface $cache,
    ) {}

    public function find(int $id): ?array
    {
        return $this->cache->get($id)
            ?? $this->cache->set($id, $this->inner->find($id));
    }
}

// DIで組み立て
$this->bind(RepositoryInterface::class)
     ->toConstructor(
         CachedRepository::class,
         ['inner' => DbRepository::class]
     );
```

**「5000行の親クラス、でも俺はクリーン」問題:**
```php
// 某ORM
class User extends Model  // ← 5000行のクラスを継承
{
    protected $table = 'users';  // 俺のコードは3行！クリーン！
    protected $fillable = ['name', 'email'];
}

// 実態:
// - 5000行の責務を暗黙的に背負っている
// - $this->save() で何が起きるか把握してる？
// - Model の protected メソッド全部が使える（使っていい？）
// - 親の変更で子が壊れる可能性
// - 「クリーン」なのは見た目だけ
```

継承は「親のコード行数も自分の責任」という意識が必要。

**なぜBEAR.Sundayで具象継承を見ないか:**
- **AOP**: 横断的関心事（ログ、キャッシュ、認証）はインターセプターで
- **DI**: 実装の切り替えはModuleで
- **Decorator**: 機能追加は委譲で
- **ResourceObject**: 継承するのはResourceObjectだけ（しかも薄い）

**具象継承が許される稀なケース:**
```php
// ✅ OK: フレームワークが要求する継承
class Index extends ResourceObject { ... }

// ✅ OK: Exceptionの継承
class OrderNotFoundException extends ResourceNotFoundException { ... }

// ✅ OK: 本当に「is-a」関係で、かつ拡張ポイントが設計されている
abstract class AbstractValueObject { ... }  // 抽象クラスからの継承
```

#### 📞 parent::コール（親呼び出し依存）

`parent::method()` が出てきたら設計を疑う。BEAR.Sundayではまず見ない。

```php
// ❌ 問題: parent:: の連鎖
class SpecialOrder extends Order
{
    public function calculate(): Money
    {
        $base = parent::calculate();  // 親に依存
        return $base->multiply(0.9);  // 10%引き
    }
}

class SuperSpecialOrder extends SpecialOrder
{
    public function calculate(): Money
    {
        $discounted = parent::calculate();  // 祖父母にも間接依存
        return $discounted->subtract(new Money(500));
    }
}

// 親が変わると子が全部壊れる
// テストで親をモックできない
// 処理の流れが追いにくい

// ✅ 推奨: 合成で解決
class OrderCalculator
{
    public function __construct(
        private array $discountStrategies,  // 戦略を注入
    ) {}

    public function calculate(Order $order): Money
    {
        $total = $order->subtotal();
        foreach ($this->discountStrategies as $strategy) {
            $total = $strategy->apply($total, $order);
        }
        return $total;
    }
}
```

**parent:: が許されるケース:**
```php
// ✅ OK: コンストラクタでの初期化
public function __construct(Foo $foo)
{
    parent::__construct();  // フレームワーク要求
    $this->foo = $foo;
}

// ✅ OK: テンプレートメソッドパターン（設計意図が明確）
abstract class AbstractImporter
{
    final public function import(): void  // final で固定
    {
        $this->validate();
        $this->doImport();  // サブクラスが実装
        $this->notify();
    }

    abstract protected function doImport(): void;
}
```

**BEAR.Sundayで parent:: を見ない理由:**
- ResourceObjectを継承するが、`parent::onGet()` は呼ばない
- 振る舞いの追加はAOPインターセプターで
- 機能の合成はDIで

#### 🎂 層だけアーキテクチャ

Controller → Service → Repository → Entity... レイヤーいっぱい！でも中身はトランザクションスクリプト＋CRUD。

```php
// ❌ 問題: レイヤーは立派、中身はただの転送
// Controller
class UserController
{
    public function store(Request $request): Response
    {
        $data = $request->all();
        $this->userService->create($data);  // 右から左へ
        return response()->json(['ok' => true]);
    }
}

// Service（という名の転送係）
class UserService
{
    public function create(array $data): User
    {
        // 「ビジネスロジック」がない、ただの転送
        return $this->userRepository->create($data);
    }

    public function update(int $id, array $data): User
    {
        return $this->userRepository->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->userRepository->delete($id);
    }
}

// Repository（という名のCRUD）
class UserRepository
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }
}

// 結果:
// - 5ファイル経由してやってることはINSERT/UPDATE
// - どのレイヤーにもドメインロジックがない
// - 変更するとき全レイヤーを修正
// - 「アーキテクチャ」という名の儀式
```

**レイヤーマンの症状:**
```php
// 症状1: Serviceがただの転送
public function getUser(int $id): User
{
    return $this->repository->find($id);  // それだけ？
}

// 症状2: 全メソッドがCRUDの鏡写し
class OrderService
{
    public function create($data) { return $this->repo->create($data); }
    public function read($id) { return $this->repo->find($id); }
    public function update($id, $data) { return $this->repo->update($id, $data); }
    public function delete($id) { return $this->repo->delete($id); }
    // ↑ Serviceの存在意義は？
}

// 症状3: 「将来のため」という言い訳
// 「今はシンプルだけど、将来ビジネスロジックが増えたら...」
// → 3年経っても転送のまま

// 症状4: DTO地獄
Request → RequestDTO → ServiceDTO → RepositoryDTO → Entity → ResponseDTO → Response
// 変換だけで100行
```

**本当にレイヤーが必要なとき:**
```php
// ✅ Serviceにドメインロジックがある
class OrderService
{
    public function place(Cart $cart, PaymentMethod $payment): Order
    {
        // 在庫確認
        foreach ($cart->items() as $item) {
            if (!$this->inventory->hasStock($item)) {
                throw new OutOfStockException($item);
            }
        }

        // 注文作成
        $order = Order::fromCart($cart);

        // 決済
        $result = $this->paymentGateway->charge($payment, $order->total());
        if (!$result->success()) {
            throw new PaymentFailedException($result);
        }

        // 在庫引当
        $this->inventory->reserve($order);

        // 永続化
        $this->orderRepository->save($order);

        // イベント発行
        $this->eventDispatcher->dispatch(new OrderPlaced($order));

        return $order;
    }
}
// ↑ これならServiceの存在意義がある
```

**BEAR.Sundayのアプローチ:**
```php
// レイヤーを減らす: Resource が直接 Query/Command を使う
class OrderResource extends ResourceObject
{
    public function __construct(
        private OrderQueryInterface $query,
        private OrderCommandInterface $command,
    ) {}

    public function onGet(string $id): static
    {
        $this->body = $this->query->item($id);
        return $this;
    }

    public function onPost(/* ... */): static
    {
        // ビジネスロジックはここ、または専用のドメインサービスへ
    }
}
// 不要な転送レイヤーがない
```

**レイヤーの価値基準:**
- そのレイヤーで何かを**判断**しているか？
- そのレイヤーを消したら**ロジックが失われる**か？
- 「転送」以外の**責務**があるか？

全部Noなら、そのレイヤーは儀式。

#### 🏜️ NOドメインDDD

「うちはDDDやってます」→ ドメイン層どこ？ Entity は getter/setter だけ、ロジックは全部 Service。

```php
// ❌ 問題: DDDと言いながらドメインがない
// Entity（という名のデータ入れ物）
class Order
{
    private int $id;
    private string $status;
    private int $total;
    private DateTime $createdAt;

    // getter/setter だけ
    public function getId(): int { return $this->id; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function getTotal(): int { return $this->total; }
    public function setTotal(int $total): void { $this->total = $total; }
}

// Service（ドメインロジックが全部ここ）
class OrderService
{
    public function cancel(Order $order): void
    {
        // キャンセル可能かのルールがServiceに
        if ($order->getStatus() === 'shipped') {
            throw new Exception('出荷済みはキャンセル不可');
        }
        if ($order->getStatus() === 'cancelled') {
            throw new Exception('既にキャンセル済み');
        }

        $order->setStatus('cancelled');
        $this->repository->save($order);
    }

    public function ship(Order $order, string $trackingNumber): void
    {
        // 出荷可能かのルールもServiceに
        if ($order->getStatus() !== 'paid') {
            throw new Exception('支払い済みでないと出荷不可');
        }

        $order->setStatus('shipped');
        $order->setTrackingNumber($trackingNumber);
        $this->repository->save($order);
    }
}

// これのどこが「ドメイン駆動」？
// - Order は何も知らない（貧血ドメインモデル）
// - ビジネスルールが Service に散らばる
// - Order を使う全員がルールを知る必要がある

// ✅ 推奨: ドメインにロジックを持たせる
class Order
{
    private function __construct(
        private OrderId $id,
        private OrderStatus $status,
        private Money $total,
        private DateTimeImmutable $createdAt,
        private ?TrackingNumber $trackingNumber = null,
    ) {}

    public static function create(Cart $cart): self
    {
        return new self(
            OrderId::generate(),
            OrderStatus::Pending,
            $cart->total(),
            new DateTimeImmutable(),
        );
    }

    public function cancel(): void
    {
        // ルールがOrder自身にある
        if ($this->status === OrderStatus::Shipped) {
            throw new OrderAlreadyShippedException($this->id);
        }
        if ($this->status === OrderStatus::Cancelled) {
            throw new OrderAlreadyCancelledException($this->id);
        }

        $this->status = OrderStatus::Cancelled;
    }

    public function ship(TrackingNumber $tracking): void
    {
        if ($this->status !== OrderStatus::Paid) {
            throw new OrderNotPaidException($this->id);
        }

        $this->status = OrderStatus::Shipped;
        $this->trackingNumber = $tracking;
    }

    public function canCancel(): bool
    {
        return !in_array($this->status, [
            OrderStatus::Shipped,
            OrderStatus::Cancelled,
        ], true);
    }
}

// Service は薄くなる
class OrderService
{
    public function cancel(OrderId $id): void
    {
        $order = $this->repository->find($id);
        $order->cancel();  // ルールはOrder が知ってる
        $this->repository->save($order);
    }
}
```

**ノードメインDDDの症状:**
```php
// 症状1: Entityがgetter/setterだけ（貧血ドメインモデル）
class User
{
    public function getName(): string { ... }
    public function setName(string $name): void { ... }
    // ビジネスメソッドなし
}

// 症状2: Serviceにビジネスルールが集中
class UserService
{
    public function canPurchase(User $user, Product $product): bool
    {
        // User も Product も判断できない
        if ($user->getAge() < 20 && $product->isAlcohol()) { ... }
    }
}

// 症状3: Value Object はあるけど、あるだけ
class Email
{
    public function __construct(
        public readonly string $value,  // ラップしただけ
    ) {}
    // バリデーションなし、振る舞いなし
}

// 本当の Value Object
class Email
{
    public function __construct(
        public readonly string $value,
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }
    }

    public function domain(): string
    {
        return explode('@', $this->value)[1];
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }
}

// 症状4: DDD用語だけ使う
// 「これはAggregateRootで、こっちはRepository で...」
// → 中身は CRUD + Transaction Script
```

**DDDの形だけ vs 本質:**
| 形だけDDD | 本質的DDD |
|-----------|-----------|
| Entity = データ + getter/setter | Entity = データ + 振る舞い + 不変条件 |
| Service にロジック集中 | Service は調整役、薄い |
| Value Object はあるけど空っぽ | Value Object に振る舞いと不変条件 |
| DDD用語を使う | ユビキタス言語でコードを書く |

#### 🧟 とりあえず消さない教・全部論理削除派

「データは消したくない」→ 全テーブルに `deleted_at`。でも本当に必要？

```php
// ❌ 問題: 思考停止の論理削除
// 全テーブルに deleted_at
CREATE TABLE users (
    id INT PRIMARY KEY,
    email VARCHAR(255) UNIQUE,  -- ← 問題発生ポイント
    deleted_at TIMESTAMP NULL
);

CREATE TABLE orders (
    id INT PRIMARY KEY,
    user_id INT,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE comments (
    id INT PRIMARY KEY,
    deleted_at TIMESTAMP NULL  -- コメントを論理削除する意味ある？
);

// 全クエリに WHERE deleted_at IS NULL が必要
class UserQuery
{
    public function find(int $id): ?User
    {
        // 毎回忘れずに書く必要がある
        return $this->db->query(
            'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    public function list(): array
    {
        // うっかり忘れると削除済みも取得
        return $this->db->query(
            'SELECT * FROM users WHERE deleted_at IS NULL'
        );
    }
}
```

**論理削除の問題点:**

```php
// 問題1: UNIQUE制約が壊れる
// ユーザーA: email='test@example.com' を論理削除
// ユーザーB: 同じメールで登録しようとする → UNIQUE違反！

// 問題2: 全クエリに条件が必要
// JOINも複雑に
SELECT o.* FROM orders o
JOIN users u ON o.user_id = u.id
WHERE o.deleted_at IS NULL
  AND u.deleted_at IS NULL  -- 忘れがち

// 問題3: データが増え続ける
// 1000万件のうち900万件が削除済み
// インデックスも肥大化

// 問題4: 復活の複雑さ
// 削除したユーザーを復活 → 関連データも全部復活？
// 削除中に作られた別データとの整合性は？
```

**本当に論理削除が必要なケース:**
```php
// ✅ 監査要件: 法的に保持義務がある
// → 論理削除ではなく、監査テーブルに移動

// ✅ 復元要件: ゴミ箱機能
// → 削除テーブルに移動、一定期間後に物理削除

// ✅ 参照整合性: 削除しても履歴で参照される
// → 別の解決策を検討（履歴テーブル、スナップショット）
```

**代替案:**
```sql
-- 代替案1: 履歴テーブルに移動
CREATE TABLE users_deleted (
    id INT PRIMARY KEY,
    email VARCHAR(255),
    deleted_at TIMESTAMP,
    deleted_by INT,
    original_data JSON  -- 削除時点のデータ
);

-- 代替案2: ステータスで管理（本当に必要な場合）
CREATE TABLE subscriptions (
    id INT PRIMARY KEY,
    status ENUM('active', 'cancelled', 'expired'),
    cancelled_at TIMESTAMP NULL
);
-- 「削除」ではなく「キャンセル」という業務概念

-- 代替案3: イベントソーシング
-- 状態ではなくイベントを保存
-- UserRegistered, UserDeleted, UserRestored...
```

**論理削除を入れる前に確認:**
- [ ] 本当に「削除後も参照」が必要？
- [ ] 法的な保持義務がある？
- [ ] 「ゴミ箱から復元」機能が要件にある？
- [ ] 全部Noなら物理削除でOK

#### 📦 とりあえずJSONカラム教

「スキーマ変更めんどい」「柔軟にしたい」→ 全部JSONに突っ込む。

```sql
-- ❌ 問題: 何でもJSON
CREATE TABLE users (
    id INT PRIMARY KEY,
    email VARCHAR(255),
    profile JSON,     -- 名前も住所も電話番号も全部ここ
    settings JSON,    -- 何が入ってるか誰も知らない
    metadata JSON     -- とりあえず何でも入れる用
);

CREATE TABLE orders (
    id INT PRIMARY KEY,
    user_id INT,
    data JSON         -- 商品も金額も配送先も全部ここ
);
```

```php
// 何が問題？

// 問題1: 検索できない（できても遅い）
SELECT * FROM users
WHERE JSON_EXTRACT(profile, '$.address.city') = '東京';
// → インデックス効かない、フルスキャン

// 問題2: 型がない
$user['profile']['age'] = "25";      // 文字列
$user['profile']['age'] = 25;         // 数値
$user['profile']['age'] = "twenty";   // これも入る

// 問題3: 何が入ってるかわからない
$profile = $user['profile'];
// name ある？ address ある？ 実行するまでわからない
// IDE補完も効かない

// 問題4: 外部キー使えない
// profile.company_id → companies.id の整合性は？
// JSON内のIDが存在するか保証できない

// 問題5: マイグレーションが地獄
// 「profile.phone を profile.phones（配列）に変更」
// → 全レコード舐めてJSONを書き換え
```

**JSONカラムが適切なケース:**
```sql
-- ✅ OK: 本当にスキーマレスなデータ
CREATE TABLE audit_logs (
    id INT PRIMARY KEY,
    action VARCHAR(50),
    payload JSON,           -- 監査ログは何が来るかわからない
    created_at TIMESTAMP
);

-- ✅ OK: 外部APIのレスポンス保存
CREATE TABLE webhook_payloads (
    id INT PRIMARY KEY,
    provider VARCHAR(50),
    raw_payload JSON,       -- 外部の形式をそのまま保存
    processed_at TIMESTAMP
);

-- ✅ OK: ユーザー定義のカスタムフィールド
CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    price INT,
    custom_attributes JSON  -- ユーザーが自由に追加する属性
);
```

**正規化すべきデータ:**
```sql
-- ❌ JSONに入れがち
data JSON  -- {"items": [{"product_id": 1, "qty": 2}], "shipping": {...}}

-- ✅ 正規化
CREATE TABLE orders (
    id INT PRIMARY KEY,
    user_id INT REFERENCES users(id),
    shipping_address_id INT REFERENCES addresses(id)
);

CREATE TABLE order_items (
    order_id INT REFERENCES orders(id),
    product_id INT REFERENCES products(id),
    quantity INT,
    price INT
);
-- 検索できる、型がある、整合性保証される
```

**JSONカラムを作る前に確認:**
- [ ] このデータで検索・集計する？ → 正規化
- [ ] 外部キーで参照される？ → 正規化
- [ ] 構造が決まってる？ → 正規化
- [ ] 本当にスキーマレス？ → JSONでOK

#### 🔧 意味なしSQLビルダー（それほぼSQLだよ）

クエリビルダー使ってるけど、ほぼ生SQL。何のためのビルダー？

```php
// ❌ 問題: ビルダーなのに文字列ベタ書き
$query = $this->db->createQueryBuilder()
    ->select('u.id, u.name, u.email')
    ->from('users', 'u')
    ->leftJoin('u', 'orders', 'o', 'u.id = o.user_id')
    ->where('u.status = :status')
    ->andWhere('u.created_at > :date')
    ->orderBy('u.created_at', 'DESC')
    ->setParameter('status', 'active')
    ->setParameter('date', $date);

// ↑ これ、SQLで書くと:
SELECT u.id, u.name, u.email
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE u.status = :status
  AND u.created_at > :date
ORDER BY u.created_at DESC

// 何が違う？
// - 文字数: ビルダーの方が長い
// - 可読性: SQLの方が読みやすい
// - テスト: SQLならDBクライアントで直接実行できる
// - 学習コスト: SQL知ってればビルダー不要

// さらにひどいケース: 文字列結合始める
$query->where("u.status = '{$status}'");  // SQLインジェクション！
$query->where('u.name LIKE ' . $this->db->quote("%{$name}%"));
```

**ビルダーが意味あるケース:**
```php
// ✅ OK: 動的にクエリを組み立てる
$qb = $this->db->createQueryBuilder()
    ->select('*')
    ->from('products');

if ($categoryId !== null) {
    $qb->andWhere('category_id = :category')
       ->setParameter('category', $categoryId);
}

if ($minPrice !== null) {
    $qb->andWhere('price >= :minPrice')
       ->setParameter('minPrice', $minPrice);
}

if ($sortBy === 'price') {
    $qb->orderBy('price', $direction);
} elseif ($sortBy === 'name') {
    $qb->orderBy('name', $direction);
}

// 条件によってクエリが変わる → ビルダーの価値あり
```

**BEAR.Sunday / Ray.MediaQuery のアプローチ:**
```php
// SQLファイルに書く（そのまんまSQL）
// var/sql/user_list.sql
SELECT u.id, u.name, u.email
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE u.status = :status
  AND u.created_at > :date
ORDER BY u.created_at DESC

// PHPはインターフェースだけ
interface UserQueryInterface
{
    #[DbQuery('user_list')]
    public function list(string $status, string $date): array;
}

// メリット:
// - SQLはSQLで書く（DBクライアントでテスト可能）
// - PHPは型付きインターフェース
// - ビルダーの学習コスト不要
```

**判断基準:**
| ケース | 推奨 |
|--------|------|
| 固定クエリ | 生SQL / SQLファイル |
| 動的条件（検索画面等） | クエリビルダー |
| 複雑なクエリ | 生SQL（可読性重視） |
| DB移植性が必要 | クエリビルダー（稀） |

#### 🎪 ミニグローバル変数（privateプロパティ乱用）

privateプロパティを「クラス内グローバル変数」として使う。メソッド間でデータ共有するためにプロパティにセット。引数で渡せばいいのに。

```php
// ❌ 問題: メソッド間の暗黙の依存
class OrderProcessor
{
    private ?Order $order = null;
    private ?User $user = null;
    private array $validationErrors = [];

    public function loadOrder(int $orderId): void
    {
        $this->order = $this->orderRepository->find($orderId);
    }

    public function loadUser(): void
    {
        // $this->order がセットされてる前提
        $this->user = $this->userRepository->find($this->order->userId);
    }

    public function validate(): void
    {
        // $this->order と $this->user がセットされてる前提
        if ($this->order->total > $this->user->creditLimit) {
            $this->validationErrors[] = 'Credit limit exceeded';
        }
    }

    public function process(): Result
    {
        // $this->validationErrors がセットされてる前提
        if (!empty($this->validationErrors)) {
            return Result::failure($this->validationErrors);
        }
        // 処理...
    }
}

// 使う側: 順番間違えると死ぬ
$processor = new OrderProcessor();
$processor->loadOrder(123);
$processor->loadUser();      // loadOrder の後じゃないとダメ
$processor->validate();      // loadUser の後じゃないとダメ
$processor->process();       // validate の後じゃないとダメ

// うっかり順番間違えると...
$processor->loadUser();      // order が null → 例外！
$processor->validate();      // user が null → 例外！

// ✅ 推奨: 依存を引数で明示
class OrderProcessor
{
    public function process(int $orderId): Result
    {
        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            return Result::failure(['Order not found']);
        }

        $user = $this->userRepository->find($order->userId);

        $errors = $this->validate($order, $user);
        if (!empty($errors)) {
            return Result::failure($errors);
        }

        return $this->executeOrder($order, $user);
    }

    private function validate(Order $order, User $user): array
    {
        $errors = [];
        if ($order->total > $user->creditLimit) {
            $errors[] = 'Credit limit exceeded';
        }
        return $errors;
    }
}

// 使う側: シンプル、順序関係なし
$result = $processor->process(123);
```

**ミニグローバルの症状:**
```php
// 症状1: 戻り値を返さずプロパティにセット
private function calculate(): void
{
    $this->result = $this->a + $this->b;  // なぜ return しない？
}

// 症状2: 引数で渡さずプロパティ経由
private function formatOutput(): string
{
    return "Total: {$this->result}";  // $result はどこから？
}

// 症状3: 一時データをプロパティに保存
private array $tempItems = [];

public function process(): void
{
    $this->tempItems = $this->fetchItems();  // なぜプロパティに？
    $this->filterItems();   // $this->tempItems を暗黙的に使う
    $this->sortItems();     // $this->tempItems を暗黙的に使う
    $this->saveItems();     // $this->tempItems を暗黙的に使う
}

// 症状4: プロパティが「今だけ」の値を持つ
private ?User $currentUser = null;  // "current" = グローバル変数の匂い
private ?Request $currentRequest = null;
```

**なぜ問題か:**
- **暗黙知**: コードを読んでも順序がわからない
- **脆弱**: 順序間違いで実行時エラー
- **テスト困難**: 状態を正しくセットアップする必要
- **並行処理不可**: 共有状態が変わる

**解決策:**
```php
// 1. 引数で渡す（最もシンプル）
public function process(Order $order, User $user): Result

// 2. コンストラクタで必須の依存を受け取る
class OrderProcessor
{
    public function __construct(
        private Order $order,
        private User $user,
    ) {}
}

// 3. ビルダーパターン（本当に段階的構築が必要な場合）
$order = OrderBuilder::create()
    ->withUser($user)
    ->withItems($items)
    ->build();  // ここで検証、不足があればエラー
```

#### 📞 メソッド間テレパシー（暗黙の呼び出し順序）

メソッドを特定の順番で呼ばないと動かない。順序はドキュメントにも書いてない。

```php
// ❌ 問題: 呼び出し順序が必須
$processor = new DataProcessor();
$processor->init();           // 1. まず初期化
$processor->loadConfig();     // 2. 設定を読む（initの後）
$processor->validate();       // 3. 検証（loadConfigの後）
$processor->execute();        // 4. 実行（validateの後）

// 順番間違えると...
$processor->execute();        // init してない → 例外
$processor->validate();       // loadConfig してない → 例外

// init() を呼び忘れると動かないクラス
class BadService
{
    private bool $initialized = false;

    public function init(): void
    {
        $this->initialized = true;
        // セットアップ処理...
    }

    public function doWork(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('init()を先に呼んでください');
        }
        // 処理...
    }
}

// ✅ 推奨: コンストラクタで初期化を完了
class GoodService
{
    public function __construct(
        private Config $config,
        private Validator $validator,
    ) {
        // 構築時点で使える状態
    }

    public function doWork(Data $data): Result
    {
        $this->validator->validate($data);
        // 処理...
    }
}
```

**テレパシーの症状:**
```php
// 症状1: init() / setup() / configure() が必要
$obj->init();
$obj->setup();
$obj->configure($options);
$obj->run();  // やっと使える

// 症状2: 「〇〇を先に呼んでください」例外
throw new \RuntimeException('connect()を先に呼んでください');
throw new \RuntimeException('login()を先に呼んでください');

// 症状3: isXxx フラグで状態チェック
if (!$this->isConnected) { throw ... }
if (!$this->isAuthenticated) { throw ... }
if (!$this->isInitialized) { throw ... }
```

**解決策:**
- コンストラクタで初期化を完了させる
- 使える状態でオブジェクトを生成
- 段階的な構築が必要ならビルダーパターン

### 12. 可読性の総合チェック

#### 「6ヶ月後の自分」テスト

このコードを6ヶ月後の自分（または他の開発者）が読んで理解できるか？

**チェックポイント:**
- [ ] 変数名から内容がわかるか
- [ ] メソッド名から処理がわかるか
- [ ] なぜそうしているか理解できるか
- [ ] 処理の流れを追えるか
- [ ] 副作用が予測できるか

#### 「説明が必要なコード」は悪いコード

コメントで説明しないと分からないコードは、コード自体を改善すべき。

```php
// ❌ 問題: 説明がないと理解できない
// $aが5より大きく、$bがnullでなく、$cが'active'の場合に処理
if ($a > 5 && $b !== null && $c === 'active') {
    $x = $d * 1.08;  // 消費税を加算
}

// ✅ 推奨: コード自体で説明
$isEligible = $age > self::MINIMUM_AGE
    && $subscription !== null
    && $accountStatus === AccountStatus::Active;

if ($isEligible) {
    $priceWithTax = $basePrice * self::TAX_RATE;
}
```

#### 認知負荷の軽減

一度に把握しなければならない情報を減らす。

```php
// ❌ 問題: 認知負荷が高い（一度に多くの情報）
$result = array_map(fn($x) => $x['items'][0]['data']['value'] * 1.1, array_filter($items, fn($i) => $i['type'] === 'premium' && $i['active']));

// ✅ 推奨: ステップに分解
$premiumItems = array_filter(
    $items,
    fn($item) => $item['type'] === 'premium' && $item['active']
);

$values = array_map(
    fn($item) => $item['items'][0]['data']['value'] * self::MARKUP_RATE,
    $premiumItems
);
```

**認知負荷が高いパターン:**
- 長いメソッドチェーン
- 深いネスト
- 複雑な条件式
- 複数の処理を1行に
- 略語だらけの変数名

## 出力フォーマット

```
## ファイル評価: [ファイルパス]

### PHPMDメトリクス

| メトリクス | 値 | 評価 |
|-----------|-----|------|
| Cyclomatic Complexity | X | A/B/C/D |
| NPath Complexity | X | A/B/C/D |
| Parameters | X | A/B/C/D |
| Fields | X | A/B/C/D |

### 命名規則

| 項目 | 評価 | コメント |
|------|------|----------|
| 変数名の長さ | OK/問題あり | 長すぎ/短すぎはないか |
| 曖昧な命名 | OK/問題あり | $data, $result 等を使っていないか |
| ブール変数命名 | OK/問題あり | is/has/can で始まっているか |
| 否定形の命名 | OK/問題あり | isNotXxx を避けているか |
| メソッド名 | OK/問題あり | 動詞で始まっているか |

### コード構造

| 項目 | 評価 | コメント |
|------|------|----------|
| ネスト深度 | OK/問題あり | 3段階以上のネストはないか |
| 早期リターン | OK/問題あり | ガード節を使っているか |
| メソッド長 | OK/問題あり | 50行以内か |
| 条件式の複雑さ | OK/問題あり | 複雑な条件は変数に抽出しているか |

### BEAR.Sunday固有評価

| 項目 | 評価 | コメント |
|------|------|----------|
| リソース設計 | A/B/C/D | ... |
| body代入 | OK/問題あり | 逐次代入ではなくまとめて構造を明示 |
| Embed使用 | OK/問題あり | resource->get()でbodyにセットしていないか |
| 戻り値型 | OK/推奨 | static を使用しているか |
| 依存性注入 | A/B/C/D | ... |
| 型安全性 | A/B/C/D | ... |

### エラーハンドリング

| 項目 | 評価 | コメント |
|------|------|----------|
| 例外設計 | OK/問題あり | ドメイン例外を使用しているか |
| ポケモンキャッチ | OK/問題あり | Throwable/Exception の広範キャッチはないか |
| 空のcatch | OK/問題あり | 握りつぶしていないか |
| 例外チェーン | OK/問題あり | 元例外を保持しているか |

### コメントとドキュメント

| 項目 | 評価 | コメント |
|------|------|----------|
| 不要なコメント | OK/問題あり | 自明なコメントはないか |
| 古いコメント | OK/問題あり | コードと不一致のコメントはないか |
| コメントアウト | OK/問題あり | コメントアウトされたコードはないか |
| TODO/FIXME | OK/問題あり | 放置されていないか |

### マジック値・死んだコード

| 項目 | 評価 | コメント |
|------|------|----------|
| マジックナンバー | OK/問題あり | 定数化されているか |
| マジックストリング | OK/問題あり | Enum/定数を使っているか |
| 到達不能コード | OK/問題あり | return/throw後のコードはないか |
| 未使用コード | OK/問題あり | 使われていないコードはないか |

### スパゲッティレベル 🍝 と問題サイズ ☕

| 項目 | スパゲッティ | サイズ | コメント |
|------|-------------|--------|----------|
| メソッド行数 | 🍝〜🍝🍝🍝🍝🍝 | Short〜Trenta | 最長メソッドの行数 |
| ネスト深度 | 🍝〜🍝🍝🍝🍝🍝 | Short〜Trenta | 最深のネスト段数 |
| ローカル変数数 | 🍝〜🍝🍝🍝🍝🍝 | Short〜Trenta | 1メソッド内の変数数 |
| 責務の混在 | 🍝〜🍝🍝🍝🍝🍝 | Short〜Trenta | 複数責務が混在していないか |

**スパゲッティ凡例:**
- 🍝 カルボナーラ（理想）
- 🍝🍝 ペペロンチーノ
- 🍝🍝🍝 ボロネーゼ
- 🍝🍝🍝🍝 ナポリタン（要リファクタ）
- 🍝🍝🍝🍝🍝 闇鍋スパゲッティ（緊急）

**問題サイズ凡例:** ☕ Short < Tall < Grande < Venti < Trenta

### 可読性

| 項目 | 評価 | コメント |
|------|------|----------|
| 6ヶ月後テスト | OK/問題あり | 他者が読んで理解できるか |
| 認知負荷 | OK/問題あり | 一度に把握する情報量は適切か |

### 総合評価: [A/B/C/D]

**評価基準:**
- A: 問題なし、模範的なコード
- B: 軽微な問題あり、許容範囲
- C: 改善が必要、リファクタリング推奨
- D: 重大な問題あり、即時対応必須

### 改善提案（優先度順）

#### 高優先度（すぐに対応すべき）
1. ...

#### 中優先度（次のリファクタリングで対応）
1. ...

#### 低優先度（余裕があれば対応）
1. ...
```

## 参考資料

- [BEAR.Sunday 1ページ版](https://bearsunday.github.io/llms-full.txt)
- [リソース](https://bearsunday.github.io/manuals/1.0/ja/resource.html)
- [リソースパラメーター](https://bearsunday.github.io/manuals/1.0/ja/resource_param.html)
- [DI](https://bearsunday.github.io/manuals/1.0/ja/di.html)
- [AOP](https://bearsunday.github.io/manuals/1.0/ja/aop.html)
- [バリデーション](https://bearsunday.github.io/manuals/1.0/ja/validation.html)
- [データベース](https://bearsunday.github.io/manuals/1.0/ja/database.html)
- [コーディングガイド](https://bearsunday.github.io/manuals/1.0/ja/coding-guide.html)
- [PHPMD Code Size Rules](https://phpmd.org/rules/codesize.html)
