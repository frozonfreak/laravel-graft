<?php

namespace GraftAI\Http\Controllers;

use GraftAI\Dsl\DslDefinition;
use GraftAI\Models\CapabilityRegistry;
use GraftAI\Models\EvolutionEvent;
use GraftAI\Models\ExecutionSignal;
use GraftAI\Models\FeatureConfig;
use GraftAI\Models\PromotionCandidate;
use GraftAI\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GovernanceDemoController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::orderBy('name')->get();

        $candidates = PromotionCandidate::orderByDesc('weighted_exec_score')->get();

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
                'signal_series' => $signalSeries,
                'data_source' => $firstFeature?->data_source ?? '—',
                'label' => $firstFeature
                    ? $this->summarizePipeline($firstFeature->data_source, $firstFeature->pipeline)
                    : 'Unknown pattern',
                'action_label' => $firstFeature
                    ? $this->summarizeAction($firstFeature->action ?? [])
                    : null,
            ];
        });

        $capabilities = CapabilityRegistry::orderBy('introduced_in_dsl')->get();

        $evolutionLog = EvolutionEvent::orderByDesc('promoted_at')->limit(20)->get();

        $dslVersion = $capabilities->last()?->introduced_in_dsl ?? DslDefinition::CURRENT_VERSION;

        $stats = [
            'pending_candidates' => $candidates->where('status', 'pending')->count(),
            'promoted_this_month' => EvolutionEvent::where('type', 'operator_promoted')
                ->whereMonth('promoted_at', now()->month)
                ->count(),
            'total_capabilities' => $capabilities->where('status', 'active')->count(),
        ];

        return view('graftai::governance.index', compact(
            'tenants', 'candidates', 'candidateDetails', 'capabilities', 'evolutionLog', 'dslVersion', 'stats',
        ));
    }

    private function summarizePipeline(string $dataSource, array $pipeline): string
    {
        $parts = [];
        $filterValues = [];
        $hasMovingAvg = false;
        $movingAvgWindow = null;
        $compareType = null;
        $compareThreshold = null;
        $aggregateFn = null;
        $metric = null;

        foreach ($pipeline as $step) {
            switch ($step['op']) {
                case 'filter':
                    $val = $step['value'] ?? '';
                    if (is_array($val)) {
                        $val = implode(', ', $val);
                    }
                    $filterValues[] = $val;
                    break;
                case 'moving_avg':
                    $hasMovingAvg = true;
                    $movingAvgWindow = $step['window'] ?? null;
                    $metric = $step['metric'] ?? null;
                    break;
                case 'aggregate':
                    $aggregateFn = $step['function'] ?? null;
                    $metric = $step['metric'] ?? null;
                    break;
                case 'compare':
                    $compareType = $step['type'] ?? null;
                    $compareThreshold = $step['threshold'] ?? null;
                    break;
            }
        }

        if ($hasMovingAvg && $compareType) {
            $direction = str_contains($compareType, 'drop') ? 'drop' : 'rise';
            $parts[] = "{$movingAvgWindow} moving avg";
            $parts[] = "{$direction} >{$compareThreshold}%";
            $parts[] = 'alert';
        } elseif ($aggregateFn && $metric) {
            $parts[] = "{$aggregateFn} of {$metric}";
        } elseif (count($pipeline) === 2 && $pipeline[1]['op'] === 'sort') {
            $dir = $pipeline[1]['direction'] ?? 'desc';
            $field = $pipeline[1]['field'] ?? '';
            $limit = $pipeline[1]['limit'] ?? '';
            $parts[] = 'top'.($limit ? " {$limit}" : '')." by {$field} ({$dir})";
        } else {
            $parts[] = implode(' → ', array_column($pipeline, 'op'));
        }

        $label = implode(' ', $parts).' on '.str_replace('_', ' ', $dataSource);

        if ($filterValues) {
            $label .= ' ('.implode(', ', array_unique($filterValues)).')';
        }

        return ucfirst($label);
    }

    private function summarizeAction(array $action): ?string
    {
        if (empty($action)) {
            return null;
        }

        $type = $action['type'] ?? 'notification';
        $channel = $action['channel'] ?? '';

        if ($type === 'notification' && $channel) {
            return 'Sends '.strtoupper($channel).' notification';
        }

        return ucfirst($type);
    }
}
