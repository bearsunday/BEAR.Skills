---
name: bear-cleancode-review
description: BEAR.SundayプロジェクトのPHPコード品質を評価する。一般的なPHP評価基準（php-cleancode-reviewスキル参照）に加え、BEAR.Sunday固有の基準（リソース設計, DI, 型安全性）で評価。コードレビュー、品質チェック、リファクタリング検討時に使用。
---

# BEAR.Sunday コードレビュースキル

このスキルはBEAR.Sunday固有の評価基準を定義します。
一般的なPHPコード品質（命名規則、コード構造、エラーハンドリング等）については `php-cleancode-review` スキルを参照してください。

## 評価手順

### 1. 一般的なPHP評価

まず `php-cleancode-review` スキルの基準で評価:
- PHPMDメトリクス（CC, NPath, パラメータ数, フィールド数）
- 命名規則
- コード構造
- エラーハンドリング
- 型安全性

### 2. BEAR.Sunday固有の評価

以下の基準を追加で評価。

## BEAR.Sunday固有の評価基準

### リソース設計

#### 評価ランク

| 評価 | 基準 |
|------|------|
| **A** | `#[Embed]`を適切に使用、単一責任、適切なHTTPメソッド |
| **B** | 基本的なリソースパターンに従っている |
| **C** | ロジックが肥大化、責務が曖昧 |
| **D** | リソースでないコード（コントローラー的実装） |

#### bodyへの代入パターン

逐次代入ではなく、最後にまとめて構造を明示すべき。

```php
// NG: 逐次代入（構造が見えにくい）
$this['contentTags'] = $tags;
$this['article'] = $article;
$this['blogger'] = $blogger;
$this['meta'] = $meta;

// OK: 最後にまとめて構造を明示
$this->body = [
    'article' => $article,
    'blogger' => $blogger,
    'contentTags' => $tags,
    'meta' => $meta,
];
```

#### privateメソッドへの引数渡しパターン

同じ引数を複数のprivateメソッドに渡すパターンは、リソースの責務過多を示す。

```php
// NG: 同じ引数を何度も渡す、リソースが肥大化
$this->setTdParams($article, $blogger->displayName ?? '', $tags);
$this->setStructuredData($article, $meta, $blogger, $tags);
$this->setSurrogateKey($article, $blogger);

// OK: サービスに委譲、リソースは「何を返すか」のみ
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
// NG: リソース内に複雑なループ
foreach (Ranking::CATEGORIES as $key => $categorySlugArray) {
    if ($key === self::ALL) {
        $result = $this->article->rankingAllArticleList(...);
    } else {
        $result = $this->article->rankingCategoryArticleList(...);
    }
    foreach ($result as $item) {
        $articleIds[] = $item['id'];
    }
    $list[$key] = new ValidRankingArticleList($result, ...);
}

// OK: ドメインに委譲
$rankingCollection = $this->rankingAggregator->aggregate($limit);
$this->body = [
    'rankings' => $rankingCollection->lists,
    'articleIds' => $rankingCollection->articleIds,
];
```

#### Embed未使用の検出

`$this->resource->get()` で他リソースを取得して `$this->body` にセットしている場合、
正当な理由がなければ `#[Embed]` を使用すべき。

```php
// NG: 手続き的なリソース取得
$user = $this->resource->get('app://self/user', ['id' => $id]);
$this->body['user'] = $user->body;

// OK: 宣言的なEmbed
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
// OK
public function onGet(int $id): static

// 推奨（staticへの変更を推奨）
public function onGet(int $id): ResourceObject
public function onGet(int $id): self
```

| 戻り値型 | 評価 |
|----------|------|
| `static` | OK |
| `ResourceObject` / `self` | 推奨（staticへの変更を推奨） |

### 依存性注入（Ray.Di）

#### 評価ランク

| 評価 | 基準 |
|------|------|
| **A** | コンストラクタ注入のみ、インターフェース依存 |
| **B** | 具象クラス依存が一部 |
| **C** | トレイトによるセッターインジェクション、サービスロケーター混在 |
| **D** | グローバル状態、静的メソッド依存 |

#### セッターインジェクションの判定

**原則**: コンストラクタインジェクションを推奨。PHP 8のコンストラクタプロモーションにより、従来のインジェクショントレイトは不要。

```php
// 非推奨: トレイトでセッターインジェクション（必須依存）
trait MetaTag
{
    protected Article $articleMeta;

    #[Inject]
    public function setArticleMeta(Article $articleMeta): void
    {
        $this->articleMeta = $articleMeta;
    }
}

// OK: コンストラクタインジェクション
public function __construct(
    private readonly Article $articleMeta,
)

// OK: オプショナルな依存
#[Inject(optional: true)]
public function setDebugger(?DebuggerInterface $debugger): void
{
    $this->debugger = $debugger;
}
```

| パターン | 評価 |
|----------|------|
| コンストラクタインジェクション | OK |
| トレイトによる `#[Inject]` セッター（必須依存） | 非推奨 |
| `#[Inject(optional: true)]` セッター | OK（オプショナル依存） |
| `use ResourceInject` | 非推奨（コンストラクタ注入を推奨） |
| `use AInject` 系トレイト | 非推奨 |
| ResourceObject固有のセッター（`setRenderer`等） | OK（フレームワーク用） |

#### `new` の使用判定

**重要**: `new` の使用が問題かどうかは、生成対象の種類で判断する。

| 種類 | `new` 使用 | 判定基準 |
|------|-----------|----------|
| ドメインオブジェクト | OK | データを保持、状態を表現（Entity, ValueObject） |
| 値オブジェクト | OK | イミュータブル、データ表現（DateTime, Money等） |
| DTO | OK | データ転送用オブジェクト |
| サービス | NG → DI | 振る舞いを持つ、外部依存がある |
| リポジトリ | NG → DI | データアクセス層 |
| HTTPクライアント | NG → DI | 外部通信 |

```php
// OK: ドメイン/値オブジェクト
$article = new ArticleDomain($data);
$dateTime = new DateTimeImmutable();
$thumbnail = new Thumbnail($data);

// 警告: ミュータブルなDateTime
$date = new DateTime();  // → DateTimeImmutableを使用すべき

// NG: サービスはDIすべき
$client = new HttpClient();
$logger = new FileLogger();
```

#### 定数と設定値

**環境依存の設定値**はクラス定数ではなく注入すべき。**アプリケーション構造の定義**はクラス定数でOK。

```php
// NG: 環境依存の設定値をクラス定数に
private const API_URL = 'https://api.example.com';
private const TIMEOUT = 30;
private const API_KEY = 'xxx';

// OK: NamedModuleでバインド、#[Named]で注入
// Module:
$this->bind()->annotatedWith('API_URL')->toInstance($apiUrl);

// Resource:
public function __construct(
    #[Named('API_URL')] private readonly string $apiUrl,
    #[Named('TIMEOUT')] private readonly int $timeout,
)

// OK: アプリケーション構造の定義（環境非依存）
private const RESOURCE_URI_LIST = [
    ['list' => 'app://self/article/publishable', 'update' => 'app://self/article/publish'],
];
private const SUPPORTED_CONTENT_TYPES = ['article', 'blog', 'news'];

// OK: ドメイン不変値はEnum
enum ContentStatus: string {
    case Draft = 'draft';
    case Published = 'published';
}
```

| 種類 | クラス定数 | 注入 |
|------|-----------|------|
| URL、パス、APIキー | NG | OK |
| タイムアウト、認証情報 | NG | OK |
| 環境依存のID | NG | OK |
| **アプリ構造の定義（URIリスト等）** | **OK** | - |
| ステータス、型識別子 | Enum推奨 | - |

#### Providerの過剰使用

`Provider` は複雑な生成ロジックが必要な場合のみ使用。単純な `new` だけなら `toConstructor` を使用すべき。

```php
// NG: Providerで単純にnewしているだけ
class FooProvider implements ProviderInterface
{
    public function get(): Foo
    {
        return new Foo($this->bar, $this->config['timeout']);
    }
}

// OK: toConstructor束縛
$this->bind(Foo::class)->toConstructor(
    Foo::class,
    ['timeout' => 'foo_timeout']
);
```

| パターン | 評価 |
|----------|------|
| `toConstructor` で済む | OK |
| 単純な `new` だけの Provider | 過剰（toConstructorを使用） |
| 条件分岐のある Provider | OK |
| ファクトリ的な Provider | OK |

### リソースメソッドの設計

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
// NG: 作成しているのに200のまま、Locationもない
public function onPost(string $title): static
{
    $id = $this->command->create($title);
    $this->body = ['id' => $id];
    return $this;
}

// OK: 201 + Location ヘッダー
public function onPost(string $title): static
{
    $id = $this->command->create($title);

    $this->code = 201;
    $this->headers['Location'] = "/article?id={$id}";
    $this->body = ['id' => $id];

    return $this;
}
```

| パターン | 評価 |
|----------|------|
| 201 + Location あり | OK |
| 201 あり、Location なし | 警告（Locationも追加推奨） |
| 200のまま（作成処理あり） | 問題 |

#### リソースメソッドの引数

`array<string, mixed>` ではなく、明示的な引数またはInputクラスを使用。

```php
// NG: マジックバッグ
public function onGet(array $conditions): static

// OK: 明示的なスカラー引数
public function onGet(
    ?int $categoryId = null,
    ?string $keyword = null,
): static

// OK: Inputクラス（複雑なデータ）
public function onPost(UserInput $user): static
```

| パラメータ数 | 推奨 |
|-------------|------|
| 1-10 | スカラー引数 |
| 11+ | `#[Input]` + DTOクラスを検討 |

#### Pageリソースの制限

Pageリソースは `onGet` と `onPost` のみ使用。

```php
// NG: PageでonPut/onDelete
class UserPage extends ResourceObject {
    public function onDelete(int $id): static  // NG
}

// OK: AppリソースでCRUD、PageはGET/POSTのみ
```

### Webコンテキストの取得

スーパーグローバル直接アクセスは禁止。属性で取得する。

```php
// NG: スーパーグローバル直接アクセス
$id = $_GET['id'];
$token = $_COOKIE['token'];

// OK: 属性で取得（テスト容易）
public function onGet(
    #[QueryParam('id')] string $userId,
    #[CookieParam('token')] string $token = '',
): static
```

### ResourceParam（リソース間依存）

他リソースの結果を引数として注入。手続き的な取得より宣言的で推奨。

```php
// NG: 手続き的に取得
public function onPut(array $data): static
{
    $userId = $this->resource->get('app://self/user/me')['id'];
}

// OK: 宣言的に注入
#[ResourceParam(uri: 'app://self/user/me#id', param: 'userId')]
public function onPut(int $userId, array $data): static
```

### バリデーション（JsonSchema）

入力バリデーションはJsonSchemaで宣言的に行う。

```php
// NG: 手動バリデーション
public function onPost(array $data): static
{
    if (empty($data['title'])) {
        throw new InvalidArgumentException();
    }
}

// OK: JsonSchemaで宣言
#[JsonSchema(schema: 'article.post.json')]
public function onPost(string $title, string $body): static
```

### AOP（インターセプター）

横断的関心事はインターセプターで分離。リソースに直接書かない。

```php
// NG: リソースに横断的関心事
public function onPost(array $data): static
{
    $this->logger->info('Creating article');
    $start = microtime(true);
    // ビジネスロジック
    $this->logger->info('Created', ['time' => microtime(true) - $start]);
}

// OK: インターセプターで分離
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

### 認証・認可

認証はインターセプターまたはミドルウェアで。リソース内に認証ロジックを書かない。

```php
// NG: リソース内で認証チェック
public function onGet(int $id): static
{
    if (!$this->auth->isLoggedIn()) {
        $this->code = 401;
        return $this;
    }
}

// OK: アトリビュート + インターセプター
#[RequireLogin]
public function onGet(int $id): static

// OK: ロールベース
#[RequireRole('admin')]
public function onDelete(int $id): static
```

### リソース内のDB直接アクセス

リソースでトランザクションやSQL実行は禁止。Query層に委譲する。

```php
// NG: リソース内でDB操作
$this->pdo->beginTransaction();
$this->pdo->exec($sql);
$this->pdo->commit();

// OK: Query層に委譲
$this->articleQuery->createWithTransaction($data);
```

### デバッグコード

`error_log()`, `var_dump()`, `print_r()` は禁止。LoggerInterfaceを使用。

```php
// NG
error_log('Error: ' . $e->getMessage());

// OK
$this->logger->error('Error', ['exception' => $e]);
```

### PHP8属性への移行

PHP 8ではDoctrineアノテーションではなくネイティブ属性を使用。

| パターン | 評価 |
|----------|------|
| `#[Embed]`, `#[Inject]`, `#[Named]` | OK |
| `/** @Embed */`, `/** @Inject */` | レガシー |

### リソース内のtry-catch（ポケモンキャッチ問題）

リソース内に巨大なtry-catchブロックを書かない。

```php
// NG: 巨大なtry-catch、Throwableキャッチ
public function onGet(int $id): static
{
    try {
        // 100行以上のロジック...
    } catch (Throwable $e) {
        $this->logger->error('エラー', ['exception' => $e]);
        throw $e;
    }
}

// OK: フレームワークに任せる、ロジックは委譲
public function onGet(int $id): static
{
    $articleView = $this->articleViewFactory->create($id);
    $this->body = [
        'article' => $articleView->article,
        'blogger' => $articleView->blogger,
    ];
    return $this;
}
```

| パターン | 評価 |
|----------|------|
| try-catchなし（フレームワーク任せ） | OK |
| 特定例外の小さなcatch | OK |
| 巨大try + `catch (Throwable)` | 問題 |
| 巨大try + `catch (Exception)` | 問題 |

### 継承より合成

トレイトや親クラスメソッドより依存性注入で組み合わせる。

```php
// NG: トレイトで機能追加
use MetaTagTrait;
use SurrogateKeyTrait;

// OK: 依存性注入
public function __construct(
    private readonly MetaTagService $metaTag,
    private readonly SurrogateKeyService $surrogateKey,
)
```

### グローバル参照禁止

`define`定数、staticメソッド直接呼び出しは禁止。

```php
// NG
$value = SOME_CONSTANT;
$result = SomeClass::staticMethod();

// OK: 注入
public function __construct(
    #[Named('SOME_VALUE')] private readonly string $value,
    private readonly SomeService $service,
)
```

## 出力フォーマット

```
## ファイル評価: [ファイルパス]

### 一般的なPHP評価（php-cleancode-review）

[php-cleancode-reviewスキルの評価結果を含める]

### BEAR.Sunday固有評価

| 項目 | 評価 | コメント |
|------|------|----------|
| リソース設計 | A/B/C/D | |
| body代入 | OK/問題あり | |
| Embed使用 | OK/問題あり | |
| 戻り値型 | OK/推奨 | |
| 依存性注入 | A/B/C/D | |
| HTTPステータス | OK/問題あり | |
| JsonSchemaバリデーション | OK/問題あり | |

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

- [BEAR.Sunday 1ページ版](https://bearsunday.github.io/llms-full.txt)
- [リソース](https://bearsunday.github.io/manuals/1.0/ja/resource.html)
- [リソースパラメーター](https://bearsunday.github.io/manuals/1.0/ja/resource_param.html)
- [DI](https://bearsunday.github.io/manuals/1.0/ja/di.html)
- [AOP](https://bearsunday.github.io/manuals/1.0/ja/aop.html)
- [バリデーション](https://bearsunday.github.io/manuals/1.0/ja/validation.html)
- [データベース](https://bearsunday.github.io/manuals/1.0/ja/database.html)
- [コーディングガイド](https://bearsunday.github.io/manuals/1.0/ja/coding-guide.html)
