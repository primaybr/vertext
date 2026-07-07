<?php

declare(strict_types=1);

namespace Tests\App\Modules\Forms;

use Tests\App\Support\DatabaseTestCase;
use App\Modules\Forms\Controllers\Front\FormFrontController;
use Core\Model;

/**
 * Covers Forms submission handling. Doesn't call
 * FormFrontController::submit() directly: every one of its branches (success
 * and every validation failure) ends in redirect(), which - like every
 * redirect() in this codebase - terminates the whole process rather than
 * returning, so it can't run inside PHPUnit without a real HTTP test client.
 *
 * Instead this tests the two things that matter and are safely reachable:
 * the conditional-visibility logic (fieldVisible(), a pure private method, via
 * reflection) and the form_submissions data contract that submit() writes to.
 */
final class FormSubmissionTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->truncate(['form_submissions', 'form_definitions']);
    }

    private function seedForm(array $fields = [], array $settings = []): array
    {
        $slug = 'test-form-' . bin2hex(random_bytes(4));
        (new Model('form_definitions'))->save([
            'name'     => 'Test Form',
            'slug'     => $slug,
            'fields'   => json_encode($fields),
            'settings' => json_encode($settings),
            'status'   => 'active',
        ]);

        return (new Model('form_definitions'))->where('slug', $slug)->get(1);
    }

    public function testFieldVisibleWithNoConditionsIsAlwaysVisible(): void
    {
        $field = ['id' => 'name', 'type' => 'text'];

        $this->assertTrue($this->invokeFieldVisible($field, []));
    }

    public function testFieldVisibleShowsOnlyWhenConditionMatches(): void
    {
        $field = [
            'id' => 'other_details',
            'type' => 'text',
            'conditions' => [['field' => 'has_other', 'operator' => 'equals', 'value' => 'yes', 'action' => 'show']],
        ];

        $this->assertTrue($this->invokeFieldVisible($field, ['has_other' => 'yes']));
        $this->assertFalse($this->invokeFieldVisible($field, ['has_other' => 'no']));
    }

    public function testFieldHiddenWhenConditionMatchesHideAction(): void
    {
        $field = [
            'id' => 'secret',
            'type' => 'text',
            'conditions' => [['field' => 'role', 'operator' => 'equals', 'value' => 'guest', 'action' => 'hide']],
        ];

        $this->assertFalse($this->invokeFieldVisible($field, ['role' => 'guest']));
        $this->assertTrue($this->invokeFieldVisible($field, ['role' => 'admin']));
    }

    public function testValidSubmissionIsPersistedWithExpectedShape(): void
    {
        $form = $this->seedForm([['id' => 'email', 'type' => 'email', 'required' => true]]);

        $submissionData = ['email' => 'visitor@example.com'];
        (new Model('form_submissions'))->save([
            'form_id'      => $form['id'],
            'data'         => json_encode($submissionData),
            'ip_hash'      => hash('sha256', '203.0.113.5' . $form['id']),
            'status'       => 'unread',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $saved = (new Model('form_submissions'))->where('form_id', $form['id'])->get(1);

        $this->assertIsArray($saved);
        $this->assertSame('unread', $saved['status']);
        $this->assertSame($submissionData, json_decode($saved['data'], true));
    }

    /** Invoke FormFrontController's private fieldVisible() via reflection. */
    private function invokeFieldVisible(array $field, array $rawValues): bool
    {
        $reflection = new \ReflectionClass(FormFrontController::class);
        $method = $reflection->getMethod('fieldVisible');
        $method->setAccessible(true);

        // fieldVisible() is a pure function of its arguments - safe to invoke
        // on a fresh, un-constructed instance via reflection's newInstanceWithoutConstructor(),
        // since FormFrontController's real constructor pulls in Session/CSRF/etc.
        // that this test has no need to bootstrap.
        $instance = $reflection->newInstanceWithoutConstructor();

        return (bool) $method->invoke($instance, $field, $rawValues);
    }
}
