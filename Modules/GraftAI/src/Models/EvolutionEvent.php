<?php

namespace GraftAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EvolutionEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'type', 'operator_name', 'promoted_from_signature',
        'dsl_version_before', 'dsl_version_after',
        'contributing_tenant_count', 'promoted_by', 'notes', 'promoted_at',
    ];

    protected $casts = [
        'promoted_at' => 'datetime',
    ];

    // Immutable — no updates or deletes
    public static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('EvolutionEvent is immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('EvolutionEvent is immutable.');
        });
    }

    public static function record(array $data): self
    {
        return static::create(array_merge($data, ['promoted_at' => now()]));
    }
}
