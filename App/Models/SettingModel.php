<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class SettingModel extends Model
{
    public function __construct()
    {
        parent::__construct('settings');
    }

    /** Get a setting value by key */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $row = $this->where('key', $key)->get(1);
        if (!$row) return $default;
        return $this->castValue($row['value'], $row['type'] ?? 'string');
    }

    /** Get all settings as a keyed array */
    public function getAll(): array
    {
        $rows = $this->orderBy('grp')->get() ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $this->castValue($row['value'], $row['type'] ?? 'string');
        }
        return $result;
    }

    /** Get settings grouped by group name */
    public function getGrouped(): array
    {
        $rows = $this->orderBy('grp')->get() ?: [];
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['grp']][] = $row;
        }
        return $grouped;
    }

    /** Set a setting value */
    public function setValue(string $key, mixed $value): void
    {
        $existing = $this->where('key', $key)->get(1);
        $strValue = is_array($value) ? json_encode($value) : (string) $value;

        if ($existing) {
            $this->where('key', $key)->update(['value' => $strValue, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->save(['key' => $key, 'value' => $strValue]);
        }
    }

    /** Set multiple values */
    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->setValue($key, $value);
        }
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'    => (int)    $value,
            'bool'   => (bool)   $value,
            'float'  => (float)  $value,
            'json'   => json_decode((string)$value, true),
            default  => (string) $value,
        };
    }
}
