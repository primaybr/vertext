<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class RoleModel extends Model
{
    public function __construct()
    {
        parent::__construct('roles');
    }

    /** Get all roles with permission count */
    public function allWithCounts(): array
    {
        return $this->get() ?: [];
    }
}
