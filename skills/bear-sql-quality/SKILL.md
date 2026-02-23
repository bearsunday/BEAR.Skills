---
user-invocable: true
name: bear-sql-quality
description: Detect and fix SQL query performance issues using Koriym.SqlQuality. Identifies full table scans, inefficient JOINs, and index invalidation.
---

# SQL Quality Improvement Skill

## Overview

Analyze MySQL queries using [Koriym.SqlQuality](https://github.com/koriym/Koriym.SqlQuality) to detect and fix performance issues.

## Detectable Issues

| Issue | Description |
|-------|-------------|
| Full table scan | Scans all rows without using an index |
| Inefficient JOIN | JOIN without a proper index |
| Index invalidation | Index disabled by function usage |

## Usage

### 1. Installation

```bash
composer require koriym/sql-quality --dev
```

### 2. Analyze SQL Files

```bash
vendor/bin/sql-quality analyze var/sql/
```

### 3. Review Output

**Query analysis list:**
- Cost of each query
- Performance level
- Detected issues

**Optimizer impact analysis:**
- Comparison with optimizer enabled/disabled
- Cost reduction rate

## Improvement Patterns

### Avoiding Index Invalidation

```sql
-- ❌ Problem: Index invalidated by function
SELECT * FROM articles WHERE YEAR(created_at) = 2024;

-- ✅ Recommended: Use range condition
SELECT * FROM articles
WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01';
```

### LIKE with Prefix Matching

```sql
-- ❌ Problem: Leading wildcard
SELECT * FROM users WHERE name LIKE '%田中';

-- ✅ Recommended: Trailing wildcard (allows index usage)
SELECT * FROM users WHERE name LIKE '田中%';
```

### JOIN Optimization

```sql
-- ❌ Problem: JOIN without index
SELECT * FROM orders o
JOIN order_items oi ON o.id = oi.order_id;  -- No index on order_id

-- ✅ Recommended: Add index
ALTER TABLE order_items ADD INDEX idx_order_id (order_id);
```

### Avoiding SELECT *

```sql
-- ❌ Problem: Fetching all columns
SELECT * FROM articles WHERE id = 1;

-- ✅ Recommended: Select only required columns
SELECT id, title, body FROM articles WHERE id = 1;
```

## Integration with BEAR.Sunday

### SQL File Layout

```text
var/
└── sql/
    └── Article/
        ├── item.sql
        ├── list.sql
        └── search.sql
```

### Usage in Query Classes

```php
interface ArticleQueryInterface
{
    #[Query('Article/item.sql')]
    public function item(int $id): ?array;
}
```

### Automated Check in CI/CD

```yaml
# .github/workflows/sql-quality.yml
- name: SQL Quality Check
  run: vendor/bin/sql-quality analyze var/sql/
```

## References

- [Koriym.SqlQuality](https://github.com/koriym/Koriym.SqlQuality)
- [MySQL EXPLAIN](https://dev.mysql.com/doc/refman/8.0/en/explain.html)
