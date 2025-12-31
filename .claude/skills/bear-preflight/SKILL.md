---
name: bear-preflight
description: デプロイ前総合チェック。Compile、セキュリティ、パフォーマンス、品質の各レポートを生成し、デプロイ可否を判定する。
---

# BEAR.Sunday Preflight Check

デプロイ前の総合チェックを実行し、レポートを生成する。

## チェック項目

### 1. Compile Check

#### 静的バインディング

```bash
./vendor/bin/bear.compile 'App\Name' prod-app-context
```

- DIコンテナのコンパイル成功
- 未バインドの依存関係検出
- AOP織り込みエラー

#### ランタイムバインディング

静的コンパイルでは検出できないバインディングを確認：

| パターン | 検証方法 |
|----------|----------|
| Ray.MediaQuery Entity | クエリ実行 → Entityマッピング確認 |
| FactoryInterface | ファクトリ経由の生成テスト |
| AssistedInject | 引数付き生成のテスト |
| Provider条件分岐 | 各条件パスの実行確認 |

```php
// Ray.MediaQuery Entityの検証例
// SQLの戻り値がEntityクラスにマッピングできるか確認
interface ArticleQueryInterface
{
    #[DbQuery('article_item')]
    public function item(string $id): Article|null;  // ← Articleのプロパティとカラム名の一致確認
}
```

### 2. Security Check

#### SAST（静的解析）

```bash
./vendor/bin/bear.security-scan src
```

- SQLインジェクション
- XSS
- パストラバーサル
- その他OWASP Top 10

#### 機密情報検出

```bash
# ハードコードされた機密情報を検索
grep -r "password\s*=" src/ --include="*.php"
grep -r "api_key\s*=" src/ --include="*.php"
grep -r "secret" src/ --include="*.php"
```

#### 環境設定

| 項目 | 本番設定 |
|------|----------|
| APP_DEBUG | false |
| APP_ENV | production |
| エラー表示 | 無効 |

### 3. Performance Check

#### キャッシュ設定

```bash
# キャッシュ属性がないリソースを検出
grep -rL "#\[Cacheable\]" src/Resource/App/ --include="*.php"
```

| リソースタイプ | 推奨キャッシュ |
|----------------|----------------|
| 参照系（onGet） | `#[Cacheable]` または `#[DonutCache]` |
| 更新系 | キャッシュなし |
| 静的コンテンツ | 長TTL |

#### SQLパフォーマンス

```bash
# Koriym.SqlQualityでEXPLAIN解析
./vendor/bin/sql-quality var/sql/
```

- フルテーブルスキャン
- 非効率なJOIN
- インデックス未使用

#### N+1検出

Embedリソースのループ内クエリを検出。

### 4. Quality Check

#### 静的解析

```bash
./vendor/bin/phpstan analyse -c phpstan.neon
./vendor/bin/psalm
./vendor/bin/phpmd src text codesize,design
```

#### テスト

```bash
./vendor/bin/phpunit
```

| メトリクス | 閾値 |
|-----------|------|
| テスト成功率 | 100% |
| カバレッジ | 80%以上（推奨） |

#### コーディング規約

```bash
./vendor/bin/phpcs
# または
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

### 5. Dependency Check

#### composer.lock

```bash
# lockファイルの存在確認
test -f composer.lock && echo "OK" || echo "MISSING"

# dev依存が本番に含まれていないか
composer install --no-dev --dry-run
```

#### セキュリティアドバイザリ

```bash
composer audit
```

### 6. Configuration Check

#### コンテキスト確認

```php
// 本番コンテキストの確認
// prod-app, prod-html-app など
```

#### 環境変数

必須環境変数の存在確認：

```bash
# .env.example と実際の設定を比較
diff <(grep -oP '^[A-Z_]+=' .env.example | sort) <(grep -oP '^[A-Z_]+=' .env | sort)
```

## 出力フォーマット

```markdown
# Preflight Check Report

Generated: 2024-01-15 10:30:00
Project: MyApp
Context: prod-app

## Summary

| Category | Status | Issues |
|----------|--------|--------|
| Compile | ✅ Pass | 0 |
| Security | ⚠️ Warn | 2 |
| Performance | ✅ Pass | 0 |
| Quality | ✅ Pass | 0 |
| Dependencies | ✅ Pass | 0 |
| Configuration | ⚠️ Warn | 1 |

**Result: ⚠️ Review Required**

## Details

### Compile
✅ bear.compile succeeded
✅ All dependencies bound
✅ Ray.MediaQuery entities verified

### Security
✅ SAST: 0 vulnerabilities
⚠️ .env: APP_DEBUG=true (should be false)
⚠️ Hardcoded credential found: src/Module/ApiModule.php:42

### Performance
✅ Cache attributes configured
✅ SQL quality: No issues
✅ No N+1 patterns detected

### Quality
✅ PHPStan: 0 errors
✅ Psalm: 0 errors
✅ Tests: 156 passed, 0 failed
✅ Coverage: 85%

### Dependencies
✅ composer.lock exists
✅ No dev dependencies in production
✅ No security advisories

### Configuration
✅ Context: prod-app
⚠️ LOG_LEVEL=debug (recommend: warning or error)

## Action Items

1. [ ] Set APP_DEBUG=false in production .env
2. [ ] Remove hardcoded credential in ApiModule.php
3. [ ] Change LOG_LEVEL to warning or error

## Recommendation

Address 3 warnings before deployment.
```

## 判定基準

| ステータス | 条件 | アクション |
|-----------|------|-----------|
| ✅ Pass | 全項目クリア | デプロイ可 |
| ⚠️ Warn | 警告あり、ブロッカーなし | レビュー後デプロイ可 |
| ❌ Fail | ブロッカーあり | デプロイ不可 |

### ブロッカー（デプロイ不可）

- Compileエラー
- 未バインド依存
- テスト失敗
- 重大なセキュリティ脆弱性
- セキュリティアドバイザリ（Critical/High）

### 警告（レビュー必要）

- カバレッジ閾値未達
- キャッシュ未設定リソース
- デバッグ設定有効
- パフォーマンス警告

## 実行タイミング

- デプロイ前（手動）
- CI/CDパイプライン（自動）
- 定期監査（週次/月次）

## CI/CD統合例

```yaml
# GitHub Actions
- name: Preflight Check
  run: |
    composer install --no-dev
    ./vendor/bin/bear.compile 'App\Name' prod-app
    ./vendor/bin/phpunit
    ./vendor/bin/bear.security-scan src
    composer audit
```