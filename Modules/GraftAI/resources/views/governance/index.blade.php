@extends('layouts.app')
@section('title', 'Governance')

@section('content')

{{-- Page header --}}
<div class="mb-8">
    <h1 class="text-2xl font-semibold text-white">Governance Dashboard</h1>
    <p class="mt-1 text-sm text-gray-400">
        Review promotion candidates, approve operators, and track how the platform evolves.
    </p>
</div>

{{-- Stats bar --}}
<div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
    @php
        $statCards = [
            ['label' => 'Pending candidates', 'value' => $stats['pending_candidates'],   'color' => 'text-violet-400'],
            ['label' => 'Promoted this month', 'value' => $stats['promoted_this_month'],  'color' => 'text-emerald-400'],
            ['label' => 'Current DSL version', 'value' => $dslVersion,                   'color' => 'text-sky-400'],
            ['label' => 'Total capabilities',  'value' => $stats['total_capabilities'],   'color' => 'text-amber-400'],
        ];
    @endphp
    @foreach($statCards as $card)
    <div class="rounded-2xl border border-white/8 bg-white/3 px-5 py-4">
        <p class="text-xs text-gray-500">{{ $card['label'] }}</p>
        <p class="mt-1 text-2xl font-semibold {{ $card['color'] }}">{{ $card['value'] }}</p>
    </div>
    @endforeach
</div>

{{-- Promotion candidates --}}
<section class="mb-10">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-gray-500">
        Promotion Candidates
        @if($candidates->where('status','pending')->count())
        <span class="ml-2 rounded-full bg-violet-500/15 px-2 py-0.5 text-xs text-violet-400">
            {{ $candidates->where('status','pending')->count() }} awaiting review
        </span>
        @endif
    </h2>

    @if($candidates->isEmpty())
        <div class="rounded-2xl border border-dashed border-white/10 py-12 text-center text-sm text-gray-600">
            No candidates yet. The pattern detector runs daily at 02:00.
        </div>
    @else
    <div class="overflow-hidden rounded-2xl border border-white/8">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-white/8 text-xs font-semibold uppercase tracking-wider text-gray-600">
                    <th class="w-8 px-3 py-3"></th>
                    <th class="px-4 py-3 text-left">What it does</th>
                    <th class="px-4 py-3 text-right">Tenants</th>
                    <th class="px-4 py-3 text-right">
                        <span title="Number of independently-created automations with this exact pipeline shape. High count = tenants are repeatedly inventing the same thing.">
                            Ind. features ⓘ
                        </span>
                    </th>
                    <th class="px-4 py-3 text-right">Score</th>
                    <th class="px-4 py-3 text-right">Success</th>
                    <th class="px-4 py-3 text-center">Risk</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($candidates as $candidate)
                @php $detail = $candidateDetails[$candidate->id] ?? []; @endphp
                {{-- Each candidate gets its own <tbody> so x-data scope covers both the summary and detail rows --}}
                <tbody
                    x-data="candidateRow('{{ $candidate->id }}', '{{ csrf_token() }}', '{{ $candidate->status }}')"
                    class="border-t border-white/5"
                >
                <tr class="group transition hover:bg-white/2">
                    {{-- Expand toggle --}}
                    <td class="px-3 py-3.5">
                        <button @click="open = !open"
                                class="flex h-5 w-5 items-center justify-center rounded text-gray-600 transition hover:text-gray-300">
                            <svg :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </td>

                    {{-- Human-readable label --}}
                    <td class="px-4 py-3.5">
                        <button @click="open = !open" class="text-left">
                            <p class="text-sm font-medium text-gray-100">
                                {{ $detail['label'] ?? '—' }}
                            </p>
                            <p class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                @if(!empty($detail['action_label']))
                                    <span>{{ $detail['action_label'] }}</span>
                                    <span class="text-white/15">·</span>
                                @endif
                                <span class="font-mono">{{ substr($candidate->pipeline_signature, 0, 10) }}…</span>
                            </p>
                        </button>
                    </td>

                    <td class="px-4 py-3.5 text-right text-gray-300">{{ $candidate->distinct_tenants }}</td>
                    <td class="px-4 py-3.5 text-right text-gray-300">{{ $candidate->distinct_features }}</td>
                    <td class="px-4 py-3.5 text-right font-medium text-white">{{ number_format($candidate->weighted_exec_score) }}</td>
                    <td class="px-4 py-3.5 text-right text-gray-300">{{ round($candidate->success_rate * 100) }}%</td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="rounded-full px-2 py-0.5 text-xs ring-1
                            {{ $candidate->risk_tier === 'low'      ? 'bg-green-500/10 text-green-400 ring-green-500/20' : '' }}
                            {{ $candidate->risk_tier === 'medium'   ? 'bg-amber-500/10 text-amber-400 ring-amber-500/20' : '' }}
                            {{ $candidate->risk_tier === 'high'     ? 'bg-red-500/10 text-red-400 ring-red-500/20' : '' }}
                            {{ $candidate->risk_tier === 'critical' ? 'bg-red-900/30 text-red-300 ring-red-400/30' : '' }}
                        ">{{ $candidate->risk_tier }}</span>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <span
                            :class="{
                                'bg-violet-500/10 text-violet-400 ring-violet-500/20': currentStatus === 'pending',
                                'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20': currentStatus === 'approved',
                                'bg-sky-500/10 text-sky-400 ring-sky-500/20': currentStatus === 'promoted',
                                'bg-red-500/10 text-red-400 ring-red-500/20': currentStatus === 'rejected',
                                'bg-gray-500/10 text-gray-400 ring-gray-500/20': currentStatus === 'promoted_then_reverted',
                            }"
                            class="rounded-full px-2 py-0.5 text-xs ring-1"
                            x-text="currentStatus"
                        ></span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">

                            {{-- pending → approve --}}
                            <button x-show="currentStatus === 'pending'" @click="approve()" :disabled="busy"
                                class="rounded-lg bg-emerald-600/20 px-3 py-1 text-xs text-emerald-400 ring-1 ring-emerald-500/20 transition hover:bg-emerald-600/40 disabled:opacity-40"
                            >Approve</button>

                            {{-- approved → promote --}}
                            <button x-show="currentStatus === 'approved'" @click="showPromote = true" :disabled="busy"
                                class="rounded-lg bg-sky-600/20 px-3 py-1 text-xs text-sky-400 ring-1 ring-sky-500/20 transition hover:bg-sky-600/40 disabled:opacity-40"
                            >Promote →</button>

                            {{-- pending | approved → reject --}}
                            <button x-show="['pending','approved'].includes(currentStatus)" @click="reject()" :disabled="busy"
                                class="rounded-lg px-3 py-1 text-xs text-gray-600 ring-1 ring-white/8 transition hover:text-red-400 disabled:opacity-40"
                            >Reject</button>

                            {{-- approved | rejected → revert to pending --}}
                            <button x-show="['approved','rejected'].includes(currentStatus)" @click="revertToPending()" :disabled="busy"
                                class="rounded-lg px-3 py-1 text-xs text-gray-600 ring-1 ring-white/8 transition hover:text-amber-400 disabled:opacity-40"
                                title="Revert back to pending review"
                            >↩ Revert</button>

                            {{-- promoted → rollback --}}
                            <button x-show="currentStatus === 'promoted'" @click="showRollback = true" :disabled="busy"
                                class="rounded-lg bg-red-600/15 px-3 py-1 text-xs text-red-400 ring-1 ring-red-500/20 transition hover:bg-red-600/30 disabled:opacity-40"
                                title="Deprecate the promoted capability and mark as rolled back"
                            >↩ Roll back</button>

                            {{-- Promote modal --}}
                            <div x-show="showPromote" x-transition
                                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
                                <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-gray-900 p-6 shadow-2xl">
                                    <h3 class="mb-4 text-sm font-semibold text-white">Promote to core operator</h3>
                                    <p class="mb-4 text-xs text-gray-400">
                                        Choose a canonical name for this operator. It will be registered in the
                                        Capability Registry and the DSL version will be bumped.
                                    </p>
                                    <input x-model="operatorName" placeholder="e.g. price_drop_alert"
                                        class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 font-mono text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500/50 focus:outline-none focus:ring-1 focus:ring-emerald-500/30">
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button @click="showPromote = false; operatorName = ''"
                                            class="rounded-lg px-4 py-2 text-xs text-gray-400 hover:text-white">Cancel</button>
                                        <button @click="promote()" :disabled="!operatorName.trim() || busy"
                                            class="rounded-lg bg-sky-600 px-4 py-2 text-xs font-medium text-white hover:bg-sky-500 disabled:opacity-40">
                                            <span x-show="!busy">Promote</span>
                                            <span x-show="busy">Promoting…</span>
                                        </button>
                                    </div>
                                    <div x-show="promoteError" x-text="promoteError" class="mt-3 text-xs text-red-400"></div>
                                </div>
                            </div>

                            {{-- Rollback modal --}}
                            <div x-show="showRollback" x-transition
                                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
                                <div class="w-full max-w-sm rounded-2xl border border-red-500/20 bg-gray-900 p-6 shadow-2xl">
                                    <h3 class="mb-2 text-sm font-semibold text-white">Roll back promoted operator</h3>
                                    <p class="mb-4 text-xs text-gray-400 leading-relaxed">
                                        This will <strong class="text-red-400">deprecate the capability</strong> in the registry,
                                        bump the DSL patch version, and mark this candidate as
                                        <span class="font-mono text-gray-300">promoted_then_reverted</span>.
                                        Existing tenant configs continue to work — no forced migration.
                                    </p>
                                    <label class="mb-1 block text-xs text-gray-500">Reason (optional)</label>
                                    <textarea x-model="rollbackNotes" rows="2" placeholder="e.g. caused regression in aggregate queries…"
                                        class="w-full resize-none rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-gray-100 placeholder-gray-600 focus:border-red-500/40 focus:outline-none focus:ring-1 focus:ring-red-500/20"></textarea>
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button @click="showRollback = false; rollbackNotes = ''"
                                            class="rounded-lg px-4 py-2 text-xs text-gray-400 hover:text-white">Cancel</button>
                                        <button @click="rollback()" :disabled="busy"
                                            class="rounded-lg bg-red-600 px-4 py-2 text-xs font-medium text-white hover:bg-red-500 disabled:opacity-40">
                                            <span x-show="!busy">Confirm rollback</span>
                                            <span x-show="busy">Rolling back…</span>
                                        </button>
                                    </div>
                                    <div x-show="rollbackError" x-text="rollbackError" class="mt-3 text-xs text-red-400"></div>
                                </div>
                            </div>

                        </div>
                    </td>
                </tr>

                {{-- ── Detail panel row ──────────────────────────────────────────── --}}
                <tr x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    class="bg-white/[0.015]">
                    <td colspan="9" class="px-6 pb-6 pt-2">
                        <div class="grid gap-6 sm:grid-cols-3">

                            {{-- Col 1: Score breakdown --}}
                            <div>
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-600">Score breakdown</p>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <dt class="text-xs text-gray-500">Weighted exec score</dt>
                                        <dd class="text-sm font-semibold text-white">{{ number_format($candidate->weighted_exec_score) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-xs text-gray-500">Distinct tenants</dt>
                                        <dd class="flex items-center gap-1.5 text-sm text-gray-200">
                                            {{ $candidate->distinct_tenants }}
                                            <span class="text-xs {{ $candidate->distinct_tenants >= 3 ? 'text-emerald-400' : 'text-amber-400' }}">
                                                {{ $candidate->distinct_tenants >= 3 ? '✓ threshold met' : '✗ need ≥3' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <dt class="text-xs text-gray-500 leading-relaxed">
                                            Independently created<br>
                                            <span class="text-gray-600">(different tenants built the same thing separately)</span>
                                        </dt>
                                        <dd class="flex shrink-0 items-center gap-1.5 text-sm text-gray-200">
                                            {{ $candidate->distinct_features }}
                                            <span class="text-xs {{ $candidate->distinct_features >= 5 ? 'text-emerald-400' : 'text-amber-400' }}">
                                                {{ $candidate->distinct_features >= 5 ? '✓' : '✗ need ≥5' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-xs text-gray-500">Success rate</dt>
                                        <dd class="flex items-center gap-1.5 text-sm text-gray-200">
                                            {{ round($candidate->success_rate * 100) }}%
                                            <span class="text-xs {{ $candidate->success_rate >= 0.9 ? 'text-emerald-400' : 'text-amber-400' }}">
                                                {{ $candidate->success_rate >= 0.9 ? '✓ threshold met' : '✗ need ≥90%' }}
                                            </span>
                                        </dd>
                                    </div>
                                    @if($candidate->avg_feedback_score)
                                    <div class="flex items-center justify-between">
                                        <dt class="text-xs text-gray-500">Avg feedback</dt>
                                        <dd class="text-sm text-gray-200">{{ number_format($candidate->avg_feedback_score, 1) }} / 5.0</dd>
                                    </div>
                                    @endif
                                    @if($candidate->reviewed_by)
                                    <div class="flex items-center justify-between pt-1 border-t border-white/6">
                                        <dt class="text-xs text-gray-500">Reviewed by</dt>
                                        <dd class="text-xs text-gray-300">{{ $candidate->reviewed_by }}</dd>
                                    </div>
                                    @endif
                                    @if($candidate->reviewed_at)
                                    <div class="flex items-center justify-between">
                                        <dt class="text-xs text-gray-500">Reviewed at</dt>
                                        <dd class="text-xs text-gray-300">{{ $candidate->reviewed_at->diffForHumans() }}</dd>
                                    </div>
                                    @endif
                                </dl>

                                {{-- 30-day activity sparkline --}}
                                @if(!empty($detail['signal_series']) && $detail['signal_series']->count())
                                <div class="mt-4">
                                    <p class="mb-2 text-xs text-gray-600">Signal activity (30d)</p>
                                    <div class="flex items-end gap-0.5 h-8">
                                        @php $maxExec = $detail['signal_series']->max('executions') ?: 1; @endphp
                                        @foreach($detail['signal_series'] as $point)
                                        <div title="{{ $point->day }}: {{ $point->executions }} executions"
                                             style="height: {{ round($point->executions / $maxExec * 100) }}%"
                                             class="flex-1 min-h-[2px] rounded-sm {{ $point->success_rate >= 0.9 ? 'bg-emerald-500/60' : 'bg-amber-500/60' }}">
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>

                            {{-- Col 2: Example pipeline --}}
                            <div>
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-600">Example pipeline</p>
                                @if(!empty($detail['example_features']) && $detail['example_features']->count())
                                @php $exampleFeature = $detail['example_features']->first(); @endphp
                                <ol class="space-y-2">
                                    @foreach($exampleFeature->pipeline as $i => $step)
                                    <li class="flex items-start gap-2">
                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded bg-white/8 text-xs text-gray-500">{{ $i + 1 }}</span>
                                        <div class="text-xs">
                                            <span class="font-mono font-medium text-gray-200">{{ $step['op'] }}</span>
                                            @if(isset($step['field']))
                                                <span class="text-gray-500"> · field:</span>
                                                <span class="font-mono text-gray-400">{{ $step['field'] }}</span>
                                            @endif
                                            @if(isset($step['op_type']))
                                                <span class="text-gray-500"> {{ $step['op_type'] }}</span>
                                                <span class="font-mono text-gray-400"> {{ is_array($step['value'] ?? null) ? implode(',', $step['value']) : ($step['value'] ?? '') }}</span>
                                            @endif
                                            @if(isset($step['metric']))
                                                <span class="text-gray-500"> · metric:</span>
                                                <span class="font-mono text-gray-400">{{ $step['metric'] }}</span>
                                            @endif
                                            @if(isset($step['window']))
                                                <span class="font-mono text-sky-400"> {{ $step['window'] }}</span>
                                            @endif
                                            @if(isset($step['type']))
                                                <span class="text-gray-500"> · {{ $step['type'] }}</span>
                                                <span class="text-gray-400"> ≥{{ $step['threshold'] ?? '' }}%</span>
                                            @endif
                                            @if(isset($step['function']))
                                                <span class="text-gray-500"> · fn:</span>
                                                <span class="font-mono text-gray-400">{{ $step['function'] }}</span>
                                            @endif
                                            @if(isset($step['direction']))
                                                <span class="text-gray-500"> · {{ $step['direction'] }}</span>
                                            @endif
                                        </div>
                                    </li>
                                    @endforeach
                                </ol>
                                <p class="mt-3 text-xs text-gray-600">
                                    Full signature:
                                    <span class="font-mono text-gray-500 break-all">{{ $candidate->pipeline_signature }}</span>
                                </p>
                                @else
                                <p class="text-xs text-gray-600">No example features available.</p>
                                @endif
                            </div>

                            {{-- Col 3: Tenants using this pattern --}}
                            <div>
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-600">
                                    Tenants using this pattern
                                </p>
                                @if(!empty($detail['example_features']) && $detail['example_features']->count())
                                <ul class="space-y-2">
                                    @foreach($detail['example_features'] as $ef)
                                    <li class="flex items-center gap-2 rounded-lg bg-white/4 px-3 py-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-xs font-semibold text-emerald-400">
                                            {{ strtoupper(substr($ef->tenant->name ?? '?', 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-medium text-gray-200">{{ $ef->tenant->name ?? 'Unknown' }}</p>
                                            <p class="text-xs text-gray-600">
                                                {{ $ef->executions()->count() }} executions ·
                                                v{{ $ef->feature_version }}
                                                @if($ef->promoted_to_core)
                                                · <span class="text-sky-400">promoted</span>
                                                @endif
                                            </p>
                                        </div>
                                    </li>
                                    @endforeach
                                    @if($candidate->distinct_tenants > $detail['example_features']->count())
                                    <li class="text-xs text-gray-600 px-3">
                                        + {{ $candidate->distinct_tenants - $detail['example_features']->count() }} more tenant(s)
                                    </li>
                                    @endif
                                </ul>
                                @else
                                <p class="text-xs text-gray-600">No tenant data available.</p>
                                @endif

                                {{-- Risk tier explanation --}}
                                <div class="mt-4 rounded-lg border border-white/6 bg-white/3 px-3 py-3">
                                    <p class="text-xs font-medium text-gray-400 mb-1">Risk assessment</p>
                                    <p class="text-xs text-gray-500 leading-relaxed">
                                        @switch($candidate->risk_tier)
                                            @case('low')    New shortcut for existing operators. Auto-approved. @break
                                            @case('medium') New operator composed of existing primitives. Requires dev review (48h SLA). @break
                                            @case('high')   New data source or external integration. Dev review + security audit. @break
                                            @case('critical') Change to existing operator behavior. Dev review + migration plan required. @break
                                        @endswitch
                                    </p>
                                </div>
                            </div>

                        </div>
                    </td>
                </tr>
                </tbody>{{-- end per-candidate tbody --}}
                @endforeach
            </tbody>{{-- end outer tbody wrapper --}}
        </table>
    </div>
    @endif
</section>

{{-- Capability Registry --}}
<section class="mb-10">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-gray-500">
        Capability Registry — DSL {{ $dslVersion }}
    </h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($capabilities as $cap)
        <div class="rounded-2xl border border-white/8 bg-white/3 px-5 py-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-mono text-sm font-medium text-white">{{ $cap->name }}</p>
                    @if($cap->description)
                    <p class="mt-0.5 text-xs text-gray-500 leading-relaxed">{{ $cap->description }}</p>
                    @endif
                </div>
                <span class="ml-3 shrink-0 rounded-full px-2 py-0.5 text-xs ring-1
                    {{ $cap->status === 'active'     ? 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' : '' }}
                    {{ $cap->status === 'deprecated' ? 'bg-gray-500/10 text-gray-400 ring-gray-500/20' : '' }}
                ">{{ $cap->status }}</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-1">
                @foreach($cap->fields as $field)
                <span class="rounded bg-white/6 px-1.5 py-0.5 font-mono text-xs text-gray-400">{{ $field }}</span>
                @endforeach
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-gray-600">
                <span>DSL {{ $cap->introduced_in_dsl }}</span>
                <span>{{ $cap->introduced_by === 'core' ? 'Core' : 'Promoted' }}</span>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- Evolution Log --}}
<section>
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-gray-500">
        Evolution Log
        <span class="ml-2 text-xs font-normal text-gray-600">(immutable)</span>
    </h2>

    @if($evolutionLog->isEmpty())
        <div class="rounded-2xl border border-dashed border-white/10 py-12 text-center text-sm text-gray-600">
            No evolution events yet. Promote your first candidate to see the log grow.
        </div>
    @else
    <div class="relative">
        {{-- Timeline line --}}
        <div class="absolute left-6 top-0 h-full w-px bg-white/6"></div>

        <div class="space-y-4">
            @foreach($evolutionLog as $event)
            <div class="flex items-start gap-4 pl-14 relative">
                {{-- Node --}}
                <div class="absolute left-4 top-1 flex h-5 w-5 items-center justify-center rounded-full border border-white/10
                    {{ $event->type === 'operator_promoted' ? 'bg-emerald-500/20' : '' }}
                    {{ $event->type === 'system_rollback'   ? 'bg-red-500/20' : '' }}
                    {{ $event->type === 'capability_deprecated' ? 'bg-gray-500/20' : '' }}
                    {{ !in_array($event->type, ['operator_promoted','system_rollback','capability_deprecated']) ? 'bg-sky-500/20' : '' }}
                ">
                    <span class="h-2 w-2 rounded-full
                        {{ $event->type === 'operator_promoted' ? 'bg-emerald-400' : '' }}
                        {{ $event->type === 'system_rollback'   ? 'bg-red-400' : '' }}
                        {{ $event->type === 'capability_deprecated' ? 'bg-gray-400' : '' }}
                        {{ !in_array($event->type, ['operator_promoted','system_rollback','capability_deprecated']) ? 'bg-sky-400' : '' }}
                    "></span>
                </div>

                <div class="flex-1 rounded-xl border border-white/8 bg-white/3 px-4 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-xs font-semibold text-gray-200">
                                {{ str_replace('_', ' ', ucfirst($event->type)) }}
                                @if($event->operator_name)
                                    — <span class="font-mono text-emerald-400">{{ $event->operator_name }}</span>
                                @endif
                            </span>
                            <p class="mt-0.5 text-xs text-gray-500">
                                DSL {{ $event->dsl_version_before }} → {{ $event->dsl_version_after }}
                                · by <strong class="text-gray-400">{{ $event->promoted_by }}</strong>
                                @if($event->contributing_tenant_count)
                                · {{ $event->contributing_tenant_count }} contributing tenants
                                @endif
                            </p>
                            @if($event->notes)
                            <p class="mt-1 text-xs text-gray-600">{{ $event->notes }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs text-gray-600">
                            {{ $event->promoted_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</section>

<script>
function candidateRow(candidateId, csrfToken, initialStatus) {
    return {
        currentStatus: initialStatus || 'pending',
        open:          false,
        busy:          false,
        showPromote:   false,
        operatorName:  '',
        promoteError:  null,
        showRollback:  false,
        rollbackNotes: '',
        rollbackError: null,

        async approve() {
            this.busy = true;
            try {
                const res = await fetch(`/api/governance/candidates/${candidateId}/approve`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ reviewer: 'dev_review' }),
                });
                if (res.ok) { const d = await res.json(); this.currentStatus = d.status; }
            } finally { this.busy = false; }
        },

        async reject() {
            this.busy = true;
            try {
                const res = await fetch(`/api/governance/candidates/${candidateId}/reject`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ reviewer: 'dev_review' }),
                });
                if (res.ok) { const d = await res.json(); this.currentStatus = d.status; }
            } finally { this.busy = false; }
        },

        async promote() {
            if (!this.operatorName.trim()) return;
            this.busy         = true;
            this.promoteError = null;
            try {
                const res = await fetch(`/api/governance/candidates/${candidateId}/promote`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ operator_name: this.operatorName, promoted_by: 'dev_review' }),
                });
                const d = await res.json();
                if (res.ok) {
                    this.currentStatus = 'promoted';
                    this.showPromote   = false;
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.promoteError = d.message || d.error || 'Promotion failed.';
                }
            } catch(e) {
                this.promoteError = 'Network error.';
            } finally {
                this.busy = false;
            }
        },

        async revertToPending() {
            this.busy = true;
            try {
                const res = await fetch(`/api/governance/candidates/${candidateId}/revert`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({}),
                });
                if (res.ok) { const d = await res.json(); this.currentStatus = d.status; }
            } finally { this.busy = false; }
        },

        async rollback() {
            this.busy          = true;
            this.rollbackError = null;
            try {
                const res = await fetch(`/api/governance/candidates/${candidateId}/rollback`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ notes: this.rollbackNotes, rolled_back_by: 'dev_review' }),
                });
                const d = await res.json();
                if (res.ok) {
                    this.currentStatus = 'promoted_then_reverted';
                    this.showRollback  = false;
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.rollbackError = d.error || 'Rollback failed.';
                }
            } catch(e) {
                this.rollbackError = 'Network error.';
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>


@endsection
