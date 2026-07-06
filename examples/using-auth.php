<?php
/**
 * Example: Authentication & Authorization
 *
 * Shows how to use the Auth helper and BaseController methods
 * to protect routes and check permissions in Vertext.
 */

// -- Checking authentication ---------------------------------------------------

use App\CMS\Auth;

// Is someone logged in?
if (!Auth::check()) {
    // Redirect to login (or return 401)
    header('Location: /admin/login');
    exit;
}

// Get the current user object
$user = Auth::user();
echo $user->name;   // string
echo $user->email;  // string
echo $user->id;     // int

// Get just the ID
$userId = Auth::id();

// -- Checking permissions ------------------------------------------------------

// Returns bool - reads from session (no DB query)
if (Auth::can('posts.publish')) {
    echo '<button>Publish</button>';
}

// Check role
if (Auth::hasRole('administrator')) {
    echo 'Full access granted';
}

// -- In a Controller (recommended approach) ------------------------------------

/*
class PostsController extends BaseController
{
    public function index(): void
    {
        // Aborts with HTTP 403 if user lacks this permission
        $this->requirePermission('posts.view');

        $posts = $this->db->table('posts')->select('*')->get();
        $this->adminRender('admin/posts/index', compact('posts'), 'Posts', 'blog');
    }

    public function store(): void
    {
        $this->requirePermission('posts.create');
        $this->validateCsrf();   // abort 419 if token invalid

        $title = $this->input->post('title');   // sanitized
        $body  = $this->input->post('body', false); // raw (for Quill HTML)

        $this->db->table('posts')->insert([
            'title'     => $title,
            'body'      => $body,
            'author_id' => Auth::id(),
            'status'    => 'draft',
        ])->run();

        $this->audit('post.created', 'post', null, ['title' => $title]);
        $this->flash('success', 'Post created.');
        $this->redirect('/admin/blog/posts');
    }

    public function delete(int $id): void
    {
        $this->requirePermission('posts.delete');
        $this->validateCsrf();

        $post = $this->db->table('posts')->where('id', $id)->first();
        if (!$post) {
            $this->notFound();
        }

        $this->db->table('posts')->where('id', $id)->delete()->run();
        $this->audit('post.deleted', 'post', $id, ['title' => $post->title]);
        $this->flash('success', 'Post deleted.');
        $this->redirect('/admin/blog/posts');
    }
}
*/

// -- Protecting a public route -------------------------------------------------

/*
class ProfileController extends BaseController
{
    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }

        $this->render('default/profile', ['user' => Auth::user()]);
    }
}
*/

// -- Logout --------------------------------------------------------------------

// Auth::logout() destroys the session and redirects to /admin/login
// It is called by Admin\AuthController::processLogout() - you don't need to call it directly
// unless building a custom flow.
Auth::logout(); // destroys session, does NOT redirect automatically
header('Location: /admin/login');
exit;
