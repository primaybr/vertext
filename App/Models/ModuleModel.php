<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class ModuleModel extends Model
{
    public function __construct()
    {
        parent::__construct('modules');
    }

    /** Get all modules ordered by name */
    public function allModules(): array
    {
        return $this->orderBy('name')->get() ?: [];
    }

    /** Find module by slug */
    public function findBySlug(string $slug): ?array
    {
        $result = $this->where('slug', $slug)->get(1);
        return $result ?: null;
    }

    /** Toggle module status (enabled/disabled) - cannot disable core modules */
    public function toggleStatus(string $id): array
    {
        $module = $this->where('id', $id)->get(1);
        if (!$module) {
            return ['success' => false, 'message' => 'Module not found'];
        }

        if ($module['is_core']) {
            return ['success' => false, 'message' => 'Core modules cannot be disabled'];
        }

        $newStatus = $module['status'] === 'enabled' ? 'disabled' : 'enabled';
        $this->where('id', $id)->update(['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]);

        return ['success' => true, 'status' => $newStatus];
    }
}
