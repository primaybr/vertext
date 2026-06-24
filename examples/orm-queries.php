<?php
/**
 * Example: Common ORM Query Patterns
 *
 * Demonstrates the most common query builder patterns used in Vertext modules.
 * All queries use parameterized statements - no raw string concatenation needed.
 */

// Assume $db is available via $this->db in a controller, or:
// $db = \Core\Database\Connection::getInstance();

// ── Basic SELECT ──────────────────────────────────────────────────────────────

// All rows
$posts = $db->table('posts')->select('*')->get();

// Specific columns
$posts = $db->table('posts')
    ->select(['id', 'title', 'slug', 'status', 'created_at'])
    ->get();

// Single row by ID
$post = $db->table('posts')->where('id', 42)->first();

// First published post
$post = $db->table('posts')
    ->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->first();

// ── WHERE conditions ──────────────────────────────────────────────────────────

// Equality (default operator)
$db->table('posts')->where('status', 'published');

// Custom operator
$db->table('posts')->where('views', 100, '>');

// Multiple AND conditions (chain)
$db->table('posts')
    ->where('status', 'published')
    ->where('author_id', 3);

// OR condition
$db->table('posts')
    ->where('status', 'published')
    ->orWhere('status', '=', 'featured');

// IN list
$db->table('posts')->whereIn(['status' => ['published', 'featured']]);

// Multiple conditions from array
$db->table('posts')->whereArray([
    'status'    => 'published',
    'author_id' => 5,
]);

// ── JOINs ────────────────────────────────────────────────────────────────────

// Posts with author name
$posts = $db->table('posts')
    ->select(['posts.id', 'posts.title', 'posts.status', 'u.name AS author'])
    ->join('users u', 'posts.author_id = u.id', 'LEFT')
    ->where('posts.status', 'published')
    ->orderBy('posts.created_at', 'DESC')
    ->get();

// Posts with category (via pivot)
$posts = $db->table('posts p')
    ->select(['p.*', 'pc.name AS category_name'])
    ->join('post_category_pivot pcp', 'p.id = pcp.post_id', 'LEFT')
    ->join('post_categories pc', 'pcp.category_id = pc.id', 'LEFT')
    ->where('p.status', 'published')
    ->get();

// ── Pagination ────────────────────────────────────────────────────────────────

$perPage = 15;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total = $db->table('posts')
    ->select('COUNT(*) AS total')
    ->where('status', 'published')
    ->first()->total ?? 0;

$posts = $db->table('posts')
    ->select(['id', 'title', 'slug', 'created_at'])
    ->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->limit($perPage)
    ->offset($offset)
    ->get();

$pager = new \Core\Utilities\Pagination\Pager($total, $perPage, $page, '/blog');

// ── Aggregates ────────────────────────────────────────────────────────────────

$total    = $db->table('posts')->select('COUNT(*) AS n')->first()->n;
$maxViews = $db->table('posts')->select('MAX(views) AS m')->where('status', 'published')->first()->m;

// Group by category - count posts per category
$counts = $db->table('post_category_pivot pcp')
    ->select(['pc.name', 'COUNT(*) AS post_count'])
    ->join('post_categories pc', 'pcp.category_id = pc.id', 'INNER')
    ->groupBy('pc.name')
    ->orderBy('post_count', 'DESC')
    ->get();

// ── INSERT / UPDATE / DELETE ──────────────────────────────────────────────────

// Insert
$db->table('posts')->insert([
    'title'     => 'New Post',
    'slug'      => 'new-post',
    'body'      => '<p>Content</p>',
    'status'    => 'draft',
    'author_id' => 1,
])->run();

// Batch insert (tags, pivots, etc.)
$db->table('post_tag_pivot')->insertBatch([
    ['post_id' => 10, 'tag_id' => 1],
    ['post_id' => 10, 'tag_id' => 3],
    ['post_id' => 10, 'tag_id' => 7],
])->run();

// Update
$db->table('posts')->where('id', 10)->update([
    'title'  => 'Updated Title',
    'status' => 'published',
])->run();

// Delete
$db->table('posts')->where('id', 10)->delete()->run();

// Delete with IN
$db->table('post_tag_pivot')->whereIn(['post_id' => [10]])->delete()->run();

// ── PostgreSQL-specific ───────────────────────────────────────────────────────

// Case-insensitive search
$results = $db->table('posts')
    ->select(['id', 'title'])
    ->ilike('title', '%laravel%')
    ->get();

// Full-text search
$results = $db->table('posts')
    ->select(['id', 'title'])
    ->fullTextSearch('body', 'php framework')
    ->get();

// Random row
$randomPost = $db->table('posts')
    ->select('*')
    ->where('status', 'published')
    ->orderByRandom()
    ->limit(1)
    ->first();

// ── Raw query (when ORM is not enough) ───────────────────────────────────────

$results = $db->query(
    "SELECT p.*, COUNT(c.id) AS comment_count
     FROM posts p
     LEFT JOIN blog_comments c ON p.id = c.post_id AND c.status = :status
     WHERE p.status = :pstatus
     GROUP BY p.id
     ORDER BY p.created_at DESC
     LIMIT :lim",
    [':status' => 'approved', ':pstatus' => 'published', ':lim' => 10]
);
$posts = $db->results();
