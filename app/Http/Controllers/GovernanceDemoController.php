<?php

namespace App\Http\Controllers;

use App\Dsl\DslDefinition;
use App\Models\CapabilityRegistry;
use App\Models\EvolutionEvent;
use App\Models\ExecutionSignal;
use App\Models\FeatureConfig;
use App\Models\PromotionCandidate;
use App\Models\Tenant;
use Illuminate\Http\Request;

class GovernanceDemoController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::orderBy('name')->get();

        $candidates = PromotionCandidate::orderByDesc('weighted_exec_score')->get();

        // For each candidate, attach example feature configs sharing the same signature
        // and a daily signal series for the sparkline (last 30 days)
        $candidateDetails = $candidates->keyBy('id')->map(function ($candidate) {
            $exampleFeatures = FeatureConfig::where('pipeline_signature', $candidate->pipeline_signature)
                ->with('tenant')
                ->limit(3)
                ->get();

            $signalSeries = ExecutionSignal::where('pipeline_signature', $candidate->pipeline_signature)
                ->where('emitted_at', '>=', now()->subDays(30))
                ->selectRaw("date(emitted_at) as day, count(*) as executions, avg(case when execution_outcome='success' then 1.0 else 0.0 end) as success_rate")
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $firstFeature = $exampleFeatures->first();

            return [
                'example_features' => $exampleFeatures,
                'signal_series'    => $signalSeries,
                'data_source'      => $firstFeature?->data_source ?? '—',
                'label'            => $firstFeature
                    ? $this->summarizePipeline($firstFeature->data_source, $firstFeature->pipeline)
                    : 'Unknown pattern',
                'action_label'     => $firstFeature
                    ? $this->summarizeAction($firstFeature->action ?? [])
                    : null,
            ];
        });

        $capabilities = CapabilityRegistry::orderBy('introduced_in_dsl')->get();

        $evolutionLog = EvolutionEvent::orderByDesc('promoted_at')->limit(20)->get();

        $dslVersion = $capabilities->last()?->introduced_in_dsl ?? DslDefinition::CURRENT_VERSION;

        $stats = [
            'pending_candidates'  => $candidates->where('status', 'pending')->count(),
            'promoted_this_month' => EvolutionEvent::where('type', 'operator_promoted')
                ->whereMonth('promoted_at', now()->month)
                ->count(),
            'total_capabilities'  => $capabilities->where('status', 'active')->count(),
        ];

        return view('governance.index', compact(
            'tenants', 'candidates', 'candidateDetails', 'capabilities', 'evolutionLog', 'dslVersion', 'stats',
        ));
    }

    /**
     * Produce a plain-English one-liner from a pipeline config.
     * e.g. "7d moving avg price drop >10% alert on crop_prices (clove)"
     */
    private function summarizePipeline(string $dataSource, array $pipeline): string
    {
        $parts       = [];
        $filterValues = [];
        $filterFields = [];
        $hasMovingAvg = false;
        $movingAvgWindow = null;
        $compareType  = null;
        $compareThreshold = null;
        $aggregateFn  = null;
        $metric       = null;

        foreach ($pipeline as $step) {
            switch ($step['op']) {
                case 'filter':
                    $val = $step['value'] ?? '';
                    if (is_array($val)) $val = implode(', ', $val);
                    $filterValues[] = $val;
                    $filterFields[] = $step['field'] ?? '';
                    break;
                case 'moving_avg':
                    $hasMovingAvg    = true;
                    $movingAvgWindow = $step['window'] ?? null;
                    $metric          = $step['metric'] ?? null;
                    break;
                case 'aggregate':
                    $aggregateFn = $step['function'] ?? null;
                    $metric      = $step['metric'] ?? null;
                    break;
                case 'compare':
                    $compareType      = $step['type'] ?? null;
                    $compareThreshold = $step['threshold'] ?? null;
                    break;
            }
        }

        // Build label from most meaningful operators
        if ($hasMovingAvg && $compareType) {
            $direction = str_contains($compareType, 'drop') ? 'drop' : 'rise';
            $parts[]   = "{$movingAvgWindow} moving avg";
            $parts[]   = "{$direction} >{$compareThreshold}%";
            $parts[]   = "alert";
        } elseif ($aggregateFn && $metric) {
            $parts[] = "{$aggregateFn} of {$metric}";
        } elseif (count($pipeline) === 2 && $pipeline[1]['op'] === 'sort') {
            $dir     = $pipeline[1]['direction'] ?? 'desc';
            $field   = $pipeline[1]['field'] ?? '';
            $limit   = $pipeline[1]['limit'] ?? '';
            $parts[] = "top" . ($limit ? " {$limit}" : '') . " by {$field} ({$dir})";
        } else {
            $parts[] = implode(' → ', array_column($pipeline, 'op'));
        }

        // Append data source
        $label = implode(' ', $parts) . ' on ' . str_replace('_', ' ', $dataSource);

        // Append filter values for context (e.g. "clove, turmeric")
        if ($filterValues) {
            $label .= ' (' . implode(', ', array_unique($filterValues)) . ')';
        }

        return ucfirst($label);
    }

    private function summarizeAction(array $action): ?string
    {
        if (empty($action)) return null;

        $type    = $action['type'] ?? 'notification';
        $channel = $action['channel'] ?? '';

        if ($type === 'notification' && $channel) {
            return 'Sends ' . strtoupper($channel) . ' notification';
        }

        return ucfirst($type);
    }
}
