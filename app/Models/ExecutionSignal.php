<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExecutionSignal extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'feature_id', 'pipeline_signature', 'dsl_version', 'data_source',
        'execution_outcome', 'action_triggered', 'execution_ms',
        'rows_scanned', 'user_feedback', 'emitted_at',
    ];

    protected $casts = [
        'action_triggered' => 'boolean',
        'emitted_at'       => 'datetime',
    ];
}
