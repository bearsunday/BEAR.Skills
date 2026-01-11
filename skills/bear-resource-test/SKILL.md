---
user-invocable: true
name: bear-resource-test
description: リソースクラスを読んでスモークテストのdataProviderを生成する。全リソースを1つのテストクラスでテスト。
---

# BEAR.Sunday リソーステスト生成スキル

## 目的

リソースクラスを分析し、スモークテスト用のdataProviderを生成する。

## テスト構造

```php
class ResourceTest extends TestCase
{
    use ResourceTestTrait;

    /**
     * @dataProvider resourceProvider
     */
    public function testResource(string $method, string $uri, array $query, int $expectedCode): void
    {
        $response = $this->resource->{$method}($uri, $query);
        $this->assertSame($expectedCode, $response->code);
    }

    public static function resourceProvider(): array
    {
        return [
            // 生成されたテストケース
            'GET /article' => ['get', 'app://self/article', ['id' => 1], 200],
            'GET /articles' => ['get', 'app://self/articles', [], 200],
            'POST /article' => ['post', 'app://self/article', ['title' => 'Test', 'body' => 'Content'], 201],
            // ...
        ];
    }
}
```

## 生成手順

### 1. リソースクラスを分析

```php
// 入力: src/Resource/App/Article.php
public function onGet(int $id): static
public function onPost(string $title, string $body): static
public function onDelete(int $id): static
```

### 2. テストケースを抽出

| メソッド | URI | 必須パラメータ | 期待コード |
|---------|-----|---------------|-----------|
| GET | app://self/article | id | 200 |
| POST | app://self/article | title, body | 201 |
| DELETE | app://self/article | id | 204 |

### 3. dataProviderに追加

```php
'GET /article' => ['get', 'app://self/article', ['id' => 1], 200],
'POST /article' => ['post', 'app://self/article', ['title' => 'Test', 'body' => 'Body'], 201],
'DELETE /article' => ['delete', 'app://self/article', ['id' => 1], 204],
```

## パラメータのデフォルト値

| 型 | デフォルト値 |
|----|-------------|
| int | 1 |
| string | 'test' |
| bool | true |
| array | [] |
| ?type | null |

## 期待コードの判定

| メソッド | デフォルトコード |
|---------|-----------------|
| onGet | 200 |
| onPost | 201 |
| onPut | 200 |
| onPatch | 200 |
| onDelete | 204 |

## 使い方

1. リソースディレクトリを指定
2. スキルがリソースクラスを走査
3. dataProviderのPHPコードを生成
4. 既存テストに追加または新規作成

## 出力例

```php
public static function resourceProvider(): array
{
    return [
        // App resources
        'GET app://self/article (id)' => ['get', 'app://self/article', ['id' => 1], 200],
        'GET app://self/articles' => ['get', 'app://self/articles', [], 200],
        'GET app://self/articles (limit)' => ['get', 'app://self/articles', ['limit' => 10], 200],
        'POST app://self/article' => ['post', 'app://self/article', ['title' => 'test', 'body' => 'test'], 201],
        'DELETE app://self/article' => ['delete', 'app://self/article', ['id' => 1], 204],

        // Page resources
        'GET page://self/index' => ['get', 'page://self/index', [], 200],
        'GET page://self/article' => ['get', 'page://self/article', ['id' => 1], 200],
    ];
}
```

## 注意事項

- 認証が必要なリソースは別途設定が必要
- 外部依存のあるリソースはモックが必要な場合あり
- 生成後に手動でパラメータ値を調整
