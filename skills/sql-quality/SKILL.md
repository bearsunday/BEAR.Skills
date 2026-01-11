---
user-invocable: true
name: sql-quality
description: Koriym.SqlQualityを使用してSQLクエリの性能問題を検出・改善する。フルテーブルスキャン、非効率なJOIN、インデックス無効化を検出。
---

# SQL品質改善スキル

## 概要

[Koriym.SqlQuality](https://github.com/koriym/Koriym.SqlQuality)を使用してMySQLクエリを分析し、性能問題を検出・改善する。

## 検出可能な問題

| 問題 | 説明 |
|------|------|
| フルテーブルスキャン | インデックスを使用せず全行走査 |
| 非効率なJOIN | 適切なインデックスがないJOIN |
| インデックス無効化 | 関数使用によるインデックス無効化 |

## 使い方

### 1. インストール

```bash
composer require koriym/sql-quality --dev
```

### 2. SQLファイルの分析

```bash
vendor/bin/sql-quality analyze var/sql/
```

### 3. 出力の確認

**クエリ分析リスト:**
- 各クエリのコスト
- パフォーマンスレベル
- 検出された問題

**オプティマイザー影響分析:**
- オプティマイザー有効/無効時の比較
- コスト削減率

## 改善パターン

### インデックス無効化の回避

```sql
-- ❌ 問題: 関数でインデックス無効化
SELECT * FROM articles WHERE YEAR(created_at) = 2024;

-- ✅ 推奨: 範囲指定
SELECT * FROM articles
WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01';
```

### LIKEの前方一致

```sql
-- ❌ 問題: 前方ワイルドカード
SELECT * FROM users WHERE name LIKE '%田中';

-- ✅ 推奨: 後方ワイルドカード（インデックス使用可能）
SELECT * FROM users WHERE name LIKE '田中%';
```

### JOINの最適化

```sql
-- ❌ 問題: インデックスなしのJOIN
SELECT * FROM orders o
JOIN order_items oi ON o.id = oi.order_id;  -- order_idにインデックスがない

-- ✅ 推奨: インデックス追加
ALTER TABLE order_items ADD INDEX idx_order_id (order_id);
```

### SELECT *の回避

```sql
-- ❌ 問題: 全カラム取得
SELECT * FROM articles WHERE id = 1;

-- ✅ 推奨: 必要なカラムのみ
SELECT id, title, body FROM articles WHERE id = 1;
```

## BEAR.Sundayとの連携

### SQLファイルの配置

```
var/
└── sql/
    └── Article/
        ├── item.sql
        ├── list.sql
        └── search.sql
```

### Queryクラスでの使用

```php
interface ArticleQueryInterface
{
    #[Query('Article/item.sql')]
    public function item(int $id): ?array;
}
```

### CI/CDでの自動チェック

```yaml
# .github/workflows/sql-quality.yml
- name: SQL Quality Check
  run: vendor/bin/sql-quality analyze var/sql/
```

## 参考資料

- [Koriym.SqlQuality](https://github.com/koriym/Koriym.SqlQuality)
- [MySQL EXPLAIN](https://dev.mysql.com/doc/refman/8.0/en/explain.html)
