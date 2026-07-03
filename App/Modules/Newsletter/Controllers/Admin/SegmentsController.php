<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Modules\Newsletter\NewsletterHelper;

/**
 * Audience segments: saved subscriber filters campaigns can target.
 *
 * GET  /admin/newsletter/segments               → index()
 * GET  /admin/newsletter/segments/form          → createForm()   (AJAX modal)
 * POST /admin/newsletter/segments/store         → store()
 * GET  /admin/newsletter/segments/{id}/form     → editForm($id)  (AJAX modal)
 * POST /admin/newsletter/segments/{id}/update   → update($id)
 * POST /admin/newsletter/segments/{id}/delete   → delete($id)
 */
class SegmentsController extends BaseController
{
    protected string $module = 'newsletter';

    public function index(): void
    {
        $this->requirePermission('newsletter.view');
        NewsletterHelper::ensureSchema();

        $segments = $this->db('newsletter_segments')
            ->whereNull('deleted_at')
            ->orderBy('name', 'ASC')
            ->get() ?: [];

        foreach ($segments as &$segment) {
            $segment['rules_decoded'] = json_decode($segment['rules'] ?: '{}', true) ?: [];
            $segment['match_count']   = count(NewsletterHelper::resolveRecipients((string) $segment['id']));
        }
        unset($segment);

        // Distinct sources for the rule builder dropdown
        $sources = array_column(
            $this->db('newsletter_subscribers')->select('source')->distinct()->whereNull('deleted_at')->get() ?: [],
            'source'
        );

        $this->adminRender('modules/newsletter/admin/segments', [
            'segments' => $segments,
            'sources'  => array_values(array_filter($sources)),
        ], 'Segments', 'newsletter');
    }

    public function createForm(): void
    {
        $this->requirePermission('newsletter.manage');
        NewsletterHelper::ensureSchema();
        $this->renderPartial('modules/newsletter/admin/_segment_form', [
            'segment' => null,
            'sources' => $this->distinctSources(),
            'action'  => $this->baseUrl . '/admin/newsletter/segments/store',
        ]);
    }

    public function editForm(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $segment = $this->db('newsletter_segments')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$segment) {
            $this->json(['success' => false, 'message' => 'Segment not found.'], 404);
        }
        $segment['rules_decoded'] = json_decode($segment['rules'] ?: '{}', true) ?: [];

        $this->renderPartial('modules/newsletter/admin/_segment_form', [
            'segment' => $segment,
            'sources' => $this->distinctSources(),
            'action'  => $this->baseUrl . "/admin/newsletter/segments/{$id}/update",
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();
        NewsletterHelper::ensureSchema();

        [$name, $rules, $error] = $this->readInput();
        if ($error) {
            $this->json(['success' => false, 'message' => $error]);
        }

        $id = (string) $this->db('newsletter_segments')->save([
            'name'       => $name,
            'rules'      => json_encode($rules),
            'created_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('newsletter.segment_created', 'newsletter_segments', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => "Segment \"{$name}\" created."]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $segment = $this->db('newsletter_segments')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$segment) {
            $this->json(['success' => false, 'message' => 'Segment not found.'], 404);
        }

        [$name, $rules, $error] = $this->readInput();
        if ($error) {
            $this->json(['success' => false, 'message' => $error]);
        }

        $this->db('newsletter_segments')->where('id', $id)->update([
            'name'       => $name,
            'rules'      => json_encode($rules),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('newsletter.segment_updated', 'newsletter_segments', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => 'Segment updated.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $segment = $this->db('newsletter_segments')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$segment) {
            $this->json(['success' => false, 'message' => 'Segment not found.'], 404);
        }

        $this->db('newsletter_segments')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        // Campaigns pointing at this segment fall back to "all active"
        try {
            $this->db('newsletter_campaigns')->where('segment_id', $id)->update(['segment_id' => null]);
        } catch (\Throwable) {
        }

        Auth::audit('newsletter.segment_deleted', 'newsletter_segments', $id, ['name' => $segment['name']]);
        $this->json(['success' => true, 'message' => "Segment \"{$segment['name']}\" deleted."]);
    }

    /** @return array{0:string,1:array,2:?string} [name, rules, error] */
    private function readInput(): array
    {
        $name = trim($this->input->post('name', false) ?? '');
        if ($name === '' || mb_strlen($name) > 150) {
            return ['', [], 'A segment name (max 150 characters) is required.'];
        }

        $rules = [];
        $source = trim($this->input->post('rule_source', false) ?? '');
        if ($source !== '') {
            $rules['source'] = substr($source, 0, 100);
        }
        foreach (['subscribed_after', 'subscribed_before'] as $key) {
            $value = trim($this->input->post('rule_' . $key, false) ?? '');
            if ($value !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return ['', [], 'Dates must use the YYYY-MM-DD format.'];
                }
                $rules[$key] = $value;
            }
        }

        return [$name, $rules, null];
    }

    private function distinctSources(): array
    {
        $sources = array_column(
            $this->db('newsletter_subscribers')->select('source')->distinct()->whereNull('deleted_at')->get() ?: [],
            'source'
        );
        return array_values(array_filter($sources));
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
