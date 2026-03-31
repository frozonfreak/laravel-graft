<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CapabilityRegistry extends Model
{
    use HasUuids;

    protected $table = 'capability_registry';

    protected $fillable = [
        'name', 'ops', 'fields', 'introduced_in_dsl',
        'introduced_by', 'status', 'deprecated_in_dsl', 'description',
    ];

    protected $casts = [
        'ops'    => 'array',
        'fields' => 'array',
    ];

    // Append-only — no delete method exposed via the API
    public static function boot(): void
    {
        parent::boot();

        static::deleting(function () {
            throw new \RuntimeException('CapabilityRegistry is append-only and cannot be deleted.');
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function activeCapabilityNames(): array
    {
        return static::where('status', 'active')->pluck('name')->all();
    }

    public static function fieldAllowlistFor(string $dataSource): array
    {
        $cap = static::where('name', $dataSource)->where('status', 'active')->first();

        return $cap ? $cap->fields : [];
    }
}
