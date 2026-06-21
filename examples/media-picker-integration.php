<?php
/**
 * Example: Integrating the Media Picker in a Module
 *
 * Shows how to add a featured image field to a module form,
 * using the vtx-media-picker component and the Media module API.
 */

// ── 1. Store a featured_image_id column in your module table ──────────────────

/*
// In Module::install(Connection $db):
$db->statement("ALTER TABLE portfolio_projects ADD COLUMN featured_image_id BIGINT REFERENCES media_files(id) ON DELETE SET NULL");
*/

// ── 2. In the controller: load the selected image for edit forms ──────────────

/*
class ProjectsController extends BaseController
{
    protected string $module = 'portfolio';

    public function editForm(int $id): void
    {
        $this->requirePermission('projects.edit');

        // Join with media_files to get the URL for the current featured image
        $project = $this->db
            ->table('portfolio_projects p')
            ->select(['p.*', 'mf.url AS featured_image_url', 'mf.alt AS featured_image_alt'])
            ->join('media_files mf', 'p.featured_image_id = mf.id', 'LEFT')
            ->where('p.id', $id)
            ->first();

        if (!$project) {
            $this->notFound();
        }

        echo $this->renderPartial(
            'modules/portfolio/admin/projects/_form',
            compact('project')
        );
    }

    public function update(int $id): void
    {
        $this->requirePermission('projects.edit');
        $this->validateCsrf();

        // featured_image_id comes from the hidden input set by vtx-media-picker
        $imageId = $this->input->post('featured_image_id') ?: null;

        $this->db->table('portfolio_projects')->where('id', $id)->update([
            'title'              => $this->input->post('title'),
            'featured_image_id'  => $imageId ? (int) $imageId : null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ])->run();

        $this->flash('success', 'Project updated.');
        $this->redirect('/admin/portfolio');
    }
}
*/

// ── 3. In the view (_form.php): add the vtx-media-picker component ────────────

/*
<form method="POST" action="...">
    <?= csrf_field() ?>

    <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" class="form-control" value="{{ $project->title ?? '' }}">
    </div>

    <!-- Featured Image Field -->
    <div class="form-group">
        <label>Featured Image</label>

        <!-- Hidden input receives the selected media file ID -->
        <input
            type="hidden"
            name="featured_image_id"
            id="featured-image-id"
            value="{{ $project->featured_image_id ?? '' }}"
        >

        <!-- Image preview (shows current selection) -->
        <div id="featured-image-preview" class="mb-2">
            <?php if (!empty($project->featured_image_url)): ?>
                <img
                    src="{{ $project->featured_image_url }}"
                    alt="{{ $project->featured_image_alt ?? '' }}"
                    style="max-width: 200px; border-radius: 4px;"
                >
            <?php endif; ?>
        </div>

        <!-- vtx-media-picker component -->
        <div
            data-component="vtx-media-picker"
            data-target="#featured-image-id"
            data-preview="#featured-image-preview"
            data-url="/admin/media/picker"
        >
            <button type="button" class="btn btn-secondary btn-sm">
                <span class="pi pi-image"></span>
                <?= empty($project->featured_image_id) ? 'Choose Image' : 'Change Image' ?>
            </button>
            <?php if (!empty($project->featured_image_id)): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" data-clear>
                    Remove Image
                </button>
            <?php endif; ?>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Project</button>
</form>
*/

// ── 4. Displaying the image on the public frontend ────────────────────────────

/*
// Controller: load project with image
$project = $this->db
    ->table('portfolio_projects p')
    ->select(['p.*', 'mf.url AS image_url', 'mf.alt AS image_alt', 'mf.width', 'mf.height'])
    ->join('media_files mf', 'p.featured_image_id = mf.id', 'LEFT')
    ->where('p.slug', $slug)
    ->first();

// In the view:
<?php if (!empty($project->image_url)): ?>
    <figure>
        <img
            src="{{ $project->image_url }}"
            alt="{{ $project->image_alt }}"
            width="{{ $project->width }}"
            height="{{ $project->height }}"
            loading="lazy"
        >
    </figure>
<?php endif; ?>
*/
