---
name: php-cleancode-review
description: PHP clean code review skill. Evaluates code quality using PHPMD metrics (CC, NPath, parameters, fields) and programming standards (naming conventions, code structure, error handling, type safety). Use for code reviews, quality checks, and refactoring.
---

# PHP コードレビュースキル

## 評価手順

### 1. PHPMDによる定量評価

以下のコマンドでメトリクスを取得:

```bash
./vendor/bin/phpmd [ファイルパス] text codesize,design 2>/dev/null | grep -v "^Deprecated"
```

#### baselineなしでの実行

`phpmd.baseline.xml`が存在する場合、真の品質状態を把握するために一時的に無効化:

```bash
# 1. baselineを一時的にリネーム
mv phpmd.baseline.xml phpmd.baseline.xml.bak

# 2. 全ソースディレクトリに対してPHPMD実行
./vendor/bin/phpmd src text phpmd.xml 2>&1 > phpmd_output.txt

# 3. 復元
mv phpmd.baseline.xml.bak phpmd.baseline.xml
```

#### 統計集計コマンド

```bash
# 総違反数
cat phpmd_output.txt | wc -l

# カテゴリ別集計
echo "=== カテゴリ別統計 ==="
echo "LongVariable:           $(grep -c 'LongVariable' phpmd_output.txt) 件"
echo "CouplingBetweenObjects: $(grep -c 'CouplingBetweenObjects' phpmd_output.txt) 件"
echo "StaticAccess:           $(grep -c 'StaticAccess' phpmd_output.txt) 件"
echo "ElseExpression:         $(grep -c 'ElseExpression' phpmd_output.txt) 件"

# 複雑度違反（重要度高）
echo ""
echo "=== 複雑度違反（高優先度） ==="
echo "CyclomaticComplexity:   $(grep -c 'CyclomaticComplexity' phpmd_output.txt) 件"
echo "NPathComplexity:        $(grep -c 'NPathComplexity' phpmd_output.txt) 件"
echo "ExcessiveMethodLength:  $(grep -c 'ExcessiveMethodLength' phpmd_output.txt) 件"
echo "ExcessiveClassLength:   $(grep -c 'ExcessiveClassLength' phpmd_output.txt) 件"
echo "TooManyFields:          $(grep -c 'TooManyFields' phpmd_output.txt) 件"
```

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

20文字を超える変数名は冗長。

```php
// NG: 長すぎる
$articlePublishedDateTimeString = $article->getPublishedAt();
$userAuthenticationTokenValue = $auth->getToken();

// OK: 簡潔で意図が伝わる
$publishedAt = $article->getPublishedAt();
$authToken = $auth->getToken();
```

| 長さ | 評価 |
|------|------|
| 1-20文字 | OK |
| 21-25文字 | 長い（短縮を検討） |
| 26文字以上 | 問題 |

#### 短すぎる変数名（ShortVariable）

3文字未満の変数名は意図が不明。ループカウンタ以外では避ける。

```php
// NG: 意味不明
$a = $this->articleQuery->item($id);
$u = $this->userQuery->item($userId);

// OK: ループカウンタ
for ($i = 0; $i < $count; $i++) { }

// OK: 意図が明確
$article = $this->articleQuery->item($id);
$user = $this->userQuery->item($userId);
```

#### 曖昧な命名

`$data`, `$result`, `$info`, `$tmp` などは何を表すか不明。

```php
// NG: 曖昧
$data = $this->articleQuery->item($id);
$result = $this->validator->validate($input);

// OK: 具体的
$article = $this->articleQuery->item($id);
$validationResult = $this->validator->validate($input);
```

**避けるべき曖昧な名前:**
- `$data`, `$result`, `$info`, `$tmp`, `$temp`
- `$value`, `$item`, `$obj`, `$arr`, `$str`, `$flag`

#### ブール変数の命名

ブール変数は `is`, `has`, `can`, `should`, `was`, `will` などで始める。

```php
// NG: ブールかどうかわからない
$published = $article->isPublished();
$admin = $user->isAdmin();

// OK: ブールと明確
$isPublished = $article->isPublished();
$isAdmin = $user->isAdmin();
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

```php
// NG: 否定形（二重否定が発生しやすい）
$isNotValid = !$validator->validate($input);
if (!$isNotValid) { }  // 二重否定で混乱

// OK: 肯定形
$isValid = $validator->validate($input);
if ($isValid) { }
```

#### メソッド名は動詞で始める

```php
// NG: 動詞でない
public function article(int $id): Article { }
public function validation(array $data): bool { }

// OK: 動詞で始まる
public function getArticle(int $id): Article { }
public function validate(array $data): bool { }
```

| 接頭辞 | 用途 | 例 |
|--------|------|-----|
| `get` | 取得（単一） | `getUser()`, `getArticle()` |
| `list` / `getAll` | 取得（複数） | `listUsers()`, `getAllArticles()` |
| `find` | 検索 | `findByEmail()` |
| `create` / `add` | 作成 | `createUser()`, `addComment()` |
| `update` | 更新 | `updateProfile()` |
| `delete` / `remove` | 削除 | `deleteArticle()`, `removeTag()` |
| `is` / `has` / `can` | 判定 | `isValid()`, `hasPermission()` |
| `validate` | 検証 | `validateInput()` |
| `calculate` | 計算 | `calculateTotal()` |

#### 命名の一貫性

同じ概念には同じ名前を使う。

```php
// NG: バラバラ
class Order { public DateTime $createdAt; }
class User { public DateTime $created; }
class Article { public DateTime $createdDatetime; }

// OK: 統一
class Order { public DateTimeImmutable $createdAt; }
class User { public DateTimeImmutable $createdAt; }
class Article { public DateTimeImmutable $createdAt; }
```

### 4. コード構造

#### 深すぎるネスト

ネストが3段階以上は読みにくい。早期リターンやメソッド抽出で解消。

```php
// NG: 深いネスト
public function onGet(int $id): static
{
    $article = $this->articleQuery->item($id);
    if ($article !== null) {
        if ($article->isPublished()) {
            $author = $this->userQuery->item($article->authorId);
            if ($author !== null) {
                if ($author->isActive()) {
                    // 処理
                }
            }
        }
    }
    throw new NotFoundException();
}

// OK: 早期リターン
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

    // 正常処理
}
```

| ネスト深度 | 評価 |
|-----------|------|
| 1-2 | 良好 |
| 3 | 許容（簡潔なら） |
| 4以上 | リファクタリング必須 |

#### 長すぎるメソッド

1メソッド50行を超えたら分割を検討。

| 行数 | 評価 |
|------|------|
| 1-20行 | 良好 |
| 21-50行 | 許容（複雑なロジックなら） |
| 51行以上 | 分割を検討 |

#### elseの削減

`else` は早期リターンで減らせることが多い。

```php
// NG: 不要なelse
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

// OK: elseなし
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

```php
// NG: 複雑な条件式
if ($user->isActive() && $user->hasPermission('edit') && $article->authorId === $user->id && !$article->isLocked()) {
    // ...
}

// OK: 意図を変数名で説明
$isAuthor = $article->authorId === $user->id;
$canEdit = $user->isActive() && $user->hasPermission('edit');
$isEditable = ! $article->isLocked();

if ($isAuthor && $canEdit && $isEditable) {
    // ...
}
```

### 5. エラーハンドリング

#### 空のcatchブロック

例外を捕捉して何もしないのは問題。

```php
// NG: 空のcatch
try {
    $this->externalApi->call();
} catch (ApiException $e) {
    // 何もしない
}

// OK: 意図を明確に
try {
    $this->externalApi->call();
} catch (ApiException $e) {
    // 外部API失敗時はフォールバック値を使用
    return $this->getFallbackData();
}
```

#### 例外の情報損失

例外を再throwする際に、元の例外情報を失わない。

```php
// NG: 元の例外情報が失われる
try {
    $this->repository->save($entity);
} catch (DatabaseException $e) {
    throw new SaveFailedException('保存に失敗しました');
}

// OK: 元の例外をチェーン
try {
    $this->repository->save($entity);
} catch (DatabaseException $e) {
    throw new SaveFailedException('保存に失敗しました', 0, $e);
}
```

#### ドメイン例外を使用

```php
// NG
throw new Exception('記事が見つかりません');
throw new RuntimeException('無効な状態です');

// OK
throw new ArticleNotFoundException("Article not found: id={$id}");
throw new InvalidArticleStateException($state);
```

| 基底クラス | 用途 |
|-----------|------|
| `RuntimeException` | 実行時エラー（リソース不在、外部API失敗等） |
| `LogicException` | プログラムのロジックエラー（不正な引数等） |

### 6. コメントとドキュメント

#### 不要なコメント

コードを読めばわかることをコメントしない。

```php
// NG: 自明なコメント
// 記事を取得する
$article = $this->articleQuery->item($id);

// 1を足す
$count++;

// OK: 「なぜ」を説明する
// 下位互換性のため、削除フラグではなく物理削除
$this->repository->hardDelete($id);
```

#### コメントアウトされたコード

コメントアウトしたコードは削除する。Gitに履歴がある。

```php
// NG
$article = $this->articleQuery->item($id);
// $oldData = $this->legacyQuery->getData($id);
// if ($oldData !== null) {
//     $article = $this->merge($article, $oldData);
// }

// OK: 削除する
$article = $this->articleQuery->item($id);
```

#### TODO/FIXME

TODO/FIXMEは期限と担当を明記。放置しない。

```php
// NG: 放置されたTODO
// TODO: あとで直す
// FIXME: なんかおかしい

// OK: 具体的に
// TODO(2024-03): Phase2でキャッシュ実装予定 (Issue #123)
```

### 7. マジック値

#### マジックナンバー

意味のある数値は定数化する。

```php
// NG
if ($retryCount > 3) { }
$timeout = 30;

// OK
private const MAX_RETRY_COUNT = 3;
private const DEFAULT_TIMEOUT_SECONDS = 30;

if ($retryCount > self::MAX_RETRY_COUNT) { }
```

#### マジックストリング

```php
// NG
if ($article['status'] === 'published') { }

// OK: Enum
enum ArticleStatus: string {
    case Draft = 'draft';
    case Published = 'published';
}

if ($article['status'] === ArticleStatus::Published->value) { }
```

### 8. 死んだコード

#### 到達不能コード

```php
// NG
public function process(): void
{
    return;
    $this->cleanup();  // 実行されない
}
```

#### 使われていないコード

- 呼び出されないprivateメソッド
- 使われていない変数
- 読み込まれないuse文

### 9. スパゲッティコード検出

複雑度の高いコード（スパゲッティコード）の検出とリファクタリング提案には `pasta-lunch` スキルを使用してください。

```bash
./vendor/bin/pasta-lunch src/
```

詳細: [pasta-lunch](https://github.com/koriym/pasta-lunch)

### 10. 困った人のコード図鑑

#### God Class（神クラス）

何でもやる巨大クラス。1000行超え、20以上のメソッド。

```php
// NG: 何でもやるクラス
class ArticleManager
{
    public function create() { }
    public function update() { }
    public function delete() { }
    public function validate() { }
    public function sendNotification() { }
    public function generatePdf() { }
    public function exportCsv() { }
    // さらに30メソッド続く...
}

// OK: 責務ごとに分離
class ArticleRepository { }
class ArticleValidator { }
class ArticleNotifier { }
class ArticleExporter { }
```

#### コピペ戦士

同じコードをあちこちにコピペ。

```php
// NG: 3箇所に同じコード
// ArticleResource.php
$date = new DateTimeImmutable($article['createdAt']);
$formatted = $date->format('Y年m月d日');

// BlogResource.php
$date = new DateTimeImmutable($blog['createdAt']);
$formatted = $date->format('Y年m月d日');

// OK: 共通化
class DateFormatter
{
    public function toJapanese(string $datetime): string
    {
        return (new DateTimeImmutable($datetime))->format('Y年m月d日');
    }
}
```

#### Primitive Obsession

何でも `string` / `int` / `array` で表現。

```php
// NG: 全部string
public function createUser(
    string $email,
    string $phone,
    string $postalCode,
    string $status,
): void {
    // $email に 'hello' が来ても通る
}

// OK: 値オブジェクトで表現
public function createUser(
    Email $email,
    PhoneNumber $phone,
    PostalCode $postalCode,
    UserStatus $status,
): void {
    // 不正な値はオブジェクト生成時に弾かれる
}
```

#### Boolean Blindness

boolを返すが、trueが何を意味するかわからない。

```php
// NG
if ($this->check($user, $article)) { }
if ($this->process($data)) { }

// OK
if ($this->canUserEditArticle($user, $article)) { }
if ($this->wasProcessedSuccessfully($data)) { }
```

#### Stringly Typed

型の代わりに文字列で何でも表現。

```php
// NG
$user['type'] = 'admin';  // typoで 'adimn' になっても動く

// OK: Enum
enum UserType: string {
    case Admin = 'admin';
    case Member = 'member';
}
$user->type = UserType::Admin;  // typoはコンパイルエラー
```

#### Static Cola（静的メソッド中毒）

何でも静的メソッドで呼ぶ。テスト不能、差し替え不能。

```php
// NG
public function onGet(int $id): static
{
    $article = ArticleRepository::find($id);
    $formatted = DateHelper::format($article->createdAt);
    Logger::info('Article viewed', ['id' => $id]);
}

// OK: 依存性注入
public function __construct(
    private readonly ArticleRepositoryInterface $repository,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly LoggerInterface $logger,
) {}
```

**静的メソッドが許容されるケース:**
- ファクトリメソッド（`User::fromArray($data)`）
- 純粋関数（`Str::slug($title)`）
- 定数的な値（`ContentType::all()`）

#### デメテルの法則違反

長いメソッドチェーンで内部構造に依存。

```php
// NG: 電車衝突
$city = $order->getCustomer()->getAddress()->getCity();

// OK: 必要な情報を直接提供
$city = $order->getShippingCity();
```

**例外（許容）:**
- Fluent Interface
- 値オブジェクトのチェーン
- コレクション操作

#### Setter/Getterマン

全プロパティにsetter/getterを生やす。実態はpublicと同じ。

```php
// NG: 全部にsetter/getter
class User
{
    private string $name;
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    // ... 全プロパティに
}

// OK: readonly + 意味のあるメソッド
readonly class User
{
    public function __construct(
        public string $name,
        public UserStatus $status,
    ) {}

    public function suspend(): self
    {
        return new self($this->name, UserStatus::Suspended);
    }
}
```

#### Service Locator

「DIコンテナ使ってるからDI」と思い込んでいる。

```php
// NG: Service Locator
public function __construct(ContainerInterface $container) {}

public function onPost(array $data): static
{
    $validator = $this->container->get(ValidatorInterface::class);
    // ...
}

// OK: 本物のDI
public function __construct(
    private ValidatorInterface $validator,
) {}
```

#### Mixed脳

`mixed` で型を放棄。

```php
// NG
/** @param array<string, mixed> $user */
function processUser(array $user): array { }

// OK: 型付き配列
/** @return array{id: int, title: string, body: string} */
function getArticle(int $id): array { }

/** @return array<Article> */
function getArticles(): array { }
```

| 書き方 | 評価 |
|--------|------|
| `array<string, mixed>` | 何が入ってるかわからない |
| `mixed` | 型の放棄 |
| `array{id: int, name: string}` | 構造が明確 |
| `array<Article>` | 要素の型が明確 |

#### CRUD Boy

全てがCRUD。ドメインの振る舞いが見えない。

```php
// NG: ステータス更新でキャンセル表現
$resource->put('/order', ['id' => $id, 'status' => 'cancelled']);

// OK: ドメインの振る舞いを表現
// POST /order/{id}/cancel
public function onPost(string $id): static
{
    $order = $this->query->item($id);
    if (!$order->canCancel()) {
        throw new BadRequestException('This order cannot be cancelled');
    }
    $this->command->cancel($id);
    return $this;
}
```

#### 層だけアーキテクチャ

Controller → Service → Repository... レイヤーいっぱい！でも中身はただの転送。

```php
// NG: Serviceがただの転送
class UserService
{
    public function create(array $data): User
    {
        return $this->userRepository->create($data);
    }
    public function update(int $id, array $data): User
    {
        return $this->userRepository->update($id, $data);
    }
}

// OK: Serviceにドメインロジックがある
class OrderService
{
    public function place(Cart $cart, PaymentMethod $payment): Order
    {
        $this->validateStock($cart);
        $payment = $this->processPayment($cart, $payment);
        $order = $this->createOrder($cart, $payment);
        $this->notifyCustomer($order);
        return $order;
    }
}
```

### 11. 型安全性

| 評価 | 基準 |
|------|------|
| **A** | 完全な型指定、`mixed`なし |
| **B** | 基本的な型指定あり、一部`mixed` |
| **C** | `array<string, mixed>`多用、`@psalm-suppress`多数 |
| **D** | 型指定なし、`mixed`だらけ |

### 12. ファイルサイズ

| 行数 | 評価 |
|------|------|
| 1-200 | 良好 |
| 201-400 | 分割を検討 |
| 401+ | 責務過多 |

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
| 変数名の長さ | OK/問題あり | |
| 曖昧な命名 | OK/問題あり | |
| ブール変数命名 | OK/問題あり | |
| メソッド名 | OK/問題あり | |

### コード構造

| 項目 | 評価 | コメント |
|------|------|----------|
| ネスト深度 | OK/問題あり | |
| 早期リターン | OK/問題あり | |
| メソッド長 | OK/問題あり | |
| 条件式の複雑さ | OK/問題あり | |

### エラーハンドリング

| 項目 | 評価 | コメント |
|------|------|----------|
| 例外設計 | OK/問題あり | |
| 空のcatch | OK/問題あり | |
| 例外チェーン | OK/問題あり | |

### 型安全性

| 項目 | 評価 | コメント |
|------|------|----------|
| 型指定 | A/B/C/D | |
| mixed使用 | OK/問題あり | |

### 総合評価: [A/B/C/D]

**評価基準:**
- A: 問題なし、模範的なコード
- B: 軽微な問題あり、許容範囲
- C: 改善が必要、リファクタリング推奨
- D: 重大な問題あり、即時対応必須

### 改善提案（優先度順）

#### 高優先度
1. ...

#### 中優先度
1. ...

#### 低優先度
1. ...
```

## 参考資料

- [PHPMD Code Size Rules](https://phpmd.org/rules/codesize.html)
- [PHP: The Right Way](https://phptherightway.com/)
- [Clean Code PHP](https://github.com/jupeter/clean-code-php)
