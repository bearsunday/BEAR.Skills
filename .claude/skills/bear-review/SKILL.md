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

### 3. BEAR.Sunday固有の評価

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

**原則**: コンストラクタインジェクションを使用すべき。

```php
// ❌ 問題: トレイトでセッターインジェクション
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
```

| パターン | 評価 |
|----------|------|
| コンストラクタインジェクション | ✅ 推奨 |
| トレイトによる `#[Inject]` セッター | ❌ 問題 |
| `use ResourceInject` | ❌ 問題（セッターインジェクション） |
| `use AInject` 系トレイト | ❌ 問題 |
| ResourceObject固有のセッター（`setRenderer`等） | ✅ OK（フレームワーク用） |

```php
// ❌ 問題: トレイトでリソース注入
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
$dateTime = new DateTimeImmutable();
$thumbnail = new Thumbnail($data);

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

設定値はクラス定数ではなく注入すべき。ドメイン不変値はEnumを使用。

```php
// ❌ 問題: 設定値をクラス定数に
private const API_URL = 'https://api.example.com';
private const LIMIT = 100;
private const USER_ID = 456;

// ✅ 推奨: NamedModuleでバインド、#[Named]で注入
// Module:
$this->bind()->annotatedWith('API_URL')->toInstance($apiUrl);

// Resource:
public function __construct(
    #[Named('API_URL')] private readonly string $apiUrl,
    #[Named('DEFAULT_LIMIT')] private readonly int $limit,
)

// ✅ ドメイン不変値はEnum
enum ContentStatus: string {
    case Draft = 'draft';
    case Published = 'published';
}
```

| 種類 | クラス定数 | 注入 |
|------|-----------|------|
| URL、パス、APIキー | ❌ | ✅ |
| 制限値、タイムアウト | ❌ | ✅ |
| ID、マジックナンバー | ❌ | ✅ |
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

#### Providerの回避

`Provider` より `toConstructor` 束縛を優先。

```php
// ❌ 問題: Provider経由
$this->bind(Foo::class)->toProvider(FooProvider::class);

// ✅ 推奨: toConstructor束縛
$this->bind(Foo::class)->toConstructor(Foo::class, ['arg' => 'value']);
```

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

### BEAR.Sunday固有評価

| 項目 | 評価 | コメント |
|------|------|----------|
| リソース設計 | A/B/C/D | ... |
| body代入 | OK/問題あり | 逐次代入ではなくまとめて構造を明示 |
| Embed使用 | OK/問題あり | resource->get()でbodyにセットしていないか |
| 戻り値型 | OK/推奨 | static を使用しているか |
| 依存性注入 | A/B/C/D | ... |
| 例外設計 | OK/問題あり | @throws Exception は問題、ドメイン例外を使用 |
| try-catch | OK/問題あり | 巨大try-catch、Throwable/Exceptionキャッチは問題 |
| 型安全性 | A/B/C/D | ... |

### 総合評価: [A/B/C/D]

### 改善提案

1. ...
2. ...
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
