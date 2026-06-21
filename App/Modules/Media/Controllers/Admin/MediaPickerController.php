<?php

declare(strict_types=1);

namespace App\Modules\Media\Controllers\Admin;

use App\Controllers\Admin\BaseController;

/**
 * Returns a picker partial (HTML grid) for use inside the CRUD form modal
 * by other modules (e.g. Blog featured image).
 *
 * GET /admin/media/picker?selected=ID&context=any_string
 *
 * On click, the selected image calls:
 *   window.__vtxMediaPickerCallback(url, id)
 * and the caller closes the modal.
 */
class MediaPickerController extends BaseController
{
    protected string $module = 'media';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('media.view');

        $search     = trim($this->input->get('search') ?? '');
        $page       = max(1, (int) ($this->input->get('page') ?? 1));
        $selectedId = (int) ($this->input->get('selected') ?? 0);
        $perPage    = 20;
        $offset     = ($page - 1) * $perPage;

        $q  = $this->db('media_files')
            ->select('id, filename, original_name, alt_text, width, height, created_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        $qc = $this->db('media_files');

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('original_name ILIKE :s', $binds);
            $qc->whereRaw('original_name ILIKE :s', $binds);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $files = $q->get() ?: [];

        foreach ($files as &$f) {
            $f['url'] = $this->baseUrl . '/uploads/media/' . $f['filename'];
        }
        unset($f);

        $this->renderPartial('modules/media/admin/media/picker', [
            'files'      => $files,
            'total'      => $total,
            'page'       => $page,
            'pages'      => max(1, (int) ceil($total / $perPage)),
            'search'     => $search,
            'selectedId' => $selectedId,
            'baseUrl'    => $this->baseUrl,
        ]);
    }
}
