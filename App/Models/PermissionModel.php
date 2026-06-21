<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class PermissionModel extends Model
{
    public function __construct()
    {
        parent::__construct('permissions');
    }

    /** Get all permissions grouped by module */
    public function groupedByModule(): array
    {
        $all = $this->orderBy('module')->get() ?: [];
        $grouped = [];
        foreach ($all as $perm) {
            $grouped[$perm['module']][] = $perm;
        }
        return $grouped;
    }
}
