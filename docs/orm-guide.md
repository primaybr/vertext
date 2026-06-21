# ORM Guide

Vertext uses an Active Record–style ORM built on PDO. Models extend `Core\Model` and map to database tables.

## Defining a Model

```php
<?php
namespace App\Models;

use Core\Model;

class ProjectModel extends Model
{
    protected string $table = 'portfolio_projects';
    protected string $primaryKey = 'id';
}
```

## Basic CRUD

### Fetch All

```php
$model = new ProjectModel();
$projects = $model->all(); // returns array of stdObjects
```

### Find by Primary Key

```php
$project = $model->find(5);
// or
$project = $model->where('id', 5)->first();
```

### Insert

```php
$model->create([
    'title'  => 'My Project',
    'slug'   => 'my-project',
    'status' => 'draft',
]);
```

### Update

```php
$model->where('id', 5)->update([
    'title'  => 'Updated Title',
    'status' => 'published',
])->run();
```

### Delete

```php
$model->where('id', 5)->delete()->run();
```

## Query Builder

Access the fluent query builder directly via `$model->db->table('...')` or by chaining methods on the model instance.

### WHERE Conditions

```php
// Equality
$model->where('status', 'published');

// Operator
$model->where('created_at', '2024-01-01', '>');

// OR condition
$model->where('status', 'published')->orWhere('status', '=', 'featured');

// IN clause
$model->whereIn(['status' => ['published', 'featured']]);

// Raw WHERE (use sparingly — no binding)
$model->whereQuery("title ILIKE '%php%'");

// Multiple conditions at once
$model->whereArray(['status' => 'published', 'author_id' => 3]);
```

### SELECT and Ordering

```php
$model
    ->select(['id', 'title', 'slug', 'created_at'])
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(20)
    ->get();
```

### Aggregates

```php
$model->select('')->count('*')->from('portfolio_projects')->first();
$model->select('')->max('views', 'max_views')->from('portfolio_projects')->first();
$model->select('')->sum('views', 'total_views')->from('portfolio_projects')->first();
```

### JOINs

```php
$this->db
    ->table('posts')
    ->select(['posts.*', 'users.name AS author_name'])
    ->join('users', 'posts.author_id = users.id', 'INNER')
    ->where('posts.status', 'published')
    ->orderBy('posts.created_at', 'DESC')
    ->get();
```

### GROUP BY and HAVING

```php
$this->db
    ->table('blog_comments')
    ->select(['post_id', 'COUNT(*) AS comment_count'])
    ->groupBy('post_id')
    ->having('COUNT(*) > 5')
    ->get();
```

### Pagination

```php
$perPage = 20;
$page    = (int) ($this->input->get('page') ?: 1);
$offset  = ($page - 1) * $perPage;

$total = $model->count('*')->first()->count ?? 0;
$items = $model->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->limit($perPage)
    ->offset($offset)
    ->get();

// Build paginator
$pager = new \Core\Utilities\Pagination\Pager($total, $perPage, $page, '/admin/portfolio');
```

## Executing Queries

After building a query, call one of:

| Method | Returns |
|--------|---------|
| `->get()` | All matching rows as array of `stdObject` |
| `->first()` | First matching row as `stdObject`, or `null` |
| `->run()` | Executes INSERT/UPDATE/DELETE; returns `true`/`false` |
| `->count()` | Used in SELECT, returns count as scalar |

## Direct DB Access

Access the database connection directly for raw queries:

```php
// Prepared statement
$this->db->query(
    "SELECT * FROM posts WHERE author_id = :id AND status = :status",
    [':id' => $userId, ':status' => 'published']
);
$results = $this->db->results(); // array of stdObjects
$single  = $this->db->result();  // first row

// Execute statement (INSERT/UPDATE/DELETE without result)
$this->db->statement("TRUNCATE my_cache_table");
```

## PostgreSQL-Specific Methods

Since Vertext uses PostgreSQL, you have access to these query builder methods:

```php
// Case-insensitive LIKE
->ilike('title', '%laravel%')

// Full-text search
->fullTextSearch('content', 'php framework')

// JSON field extraction
->jsonExtract('meta', 'seo_title')
->jsonExtractPath('meta', 'social.og_title')

// JSON containment
->jsonContains('tags', 'php')

// Array containment
->arrayContains('category_ids', 3)

// Date formatting
->dateFormat('created_at', 'YYYY-MM-DD')

// CASE WHEN
->caseWhen('status', ['draft' => 'Draft', 'published' => 'Live'], 'Unknown')

// Random ordering
->orderByRandom()

// PostgreSQL-specific DISTINCT ON
->distinctOn(['author_id'])
```

## Query Caching

Wrap expensive queries in the query cache:

```php
use Core\Cache\QueryCache;

$cache = new QueryCache();
$key   = 'featured_projects';

$projects = $cache->get($key);
if ($projects === null) {
    $projects = $model->where('featured', true)->get();
    $cache->set($key, $projects, 300); // cache 5 minutes
}
```

Invalidate on write:

```php
$cache->forget('featured_projects');
// or clear all query cache
$cache->flush();
```
