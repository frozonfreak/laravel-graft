@extends('layouts.app')
@section('title', 'Smart Automations')

@section('content')

{{-- Page header --}}
<div class="mb-8 flex items-start justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-white">Smart Automations</h1>
        <p class="mt-1 text-sm text-gray-400">
            Describe what you want to automate in plain English. The AI will build it for you.
        </p>
    </div>
    <div class="flex items-center gap-3 text-sm text-gray-400">
        <span class="flex items-center gap-1.5">
            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
            Budget: <strong class="text-white">{{ number_format($budget->consumed) }}</strong> / {{ number_format($budget->monthly_limit) }} pts
        </span>
        @if($budget->status === 'warning')
            <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs text-amber-400 ring-1 ring-amber-500/20">Budget Warning</span>
        @elseif($budget->status === 'halted')
            <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-xs text-red-400 ring-1 ring-red-500/20">Budget Halted</span>
        @endif
    </div>
</div>

{{-- AI Generator --}}
<div
    x-data="featureGenerator('{{ $activeTenant->id }}', '{{ csrf_token() }}')"
    class="mb-10 rounded-2xl border border-white/8 bg-white/3 p-6"
>
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-gray-500">New Automation</h2>

    {{-- Step 1: Prompt --}}
    <div x-show="step === 'prompt'" x-transition>
        <div class="flex gap-3">
            <textarea
                x-model="prompt"
                @keydown.meta.enter="generate()"
                placeholder="e.g. Alert me via SMS every morning if clove prices drop more than 10% over the past week…"
                rows="3"
                class="flex-1 resize-none rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500/50 focus:outline-none focus:ring-1 focus:ring-emerald-500/30"
            ></textarea>
        </div>
        <div class="mt-3 flex items-center gap-3">
            <button
                @click="generate()"
                :disabled="!prompt.trim() || loading"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40"
            >
                <span x-show="!loading">Generate automation</span>
                <span x-show="loading" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Generating…
                </span>
            </button>
            <span class="text-xs text-gray-600">⌘ Enter</span>
        </div>

        {{-- Error --}}
        <div x-show="error" x-text="error" class="mt-3 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-400 ring-1 ring-red-500/20"></div>
    </div>

    {{-- Step 2: Confirm --}}
    <div x-show="step === 'confirm'" x-transition>

        {{-- Semantic summary --}}
        <div class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4">
            <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-emerald-500">What this automation will do</p>
            <p x-text="summary" class="text-sm leading-relaxed text-gray-200"></p>
        </div>

        {{-- Meta badges --}}
        <div class="mb-5 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full px-2.5 py-1 ring-1"
                :class="{
                    'bg-green-500/10 text-green-400 ring-green-500/20':  policy.cost_estimate?.tier === 'low',
                    'bg-amber-500/10 text-amber-400 ring-amber-500/20':  policy.cost_estimate?.tier === 'medium',
                    'bg-red-500/10   text-red-400   ring-red-500/20':    policy.cost_estimate?.tier === 'high',
                }">
                Cost: <strong x-text="policy.cost_estimate?.tier"></strong>
                (<span x-text="policy.cost_estimate?.score"></span> pts)
            </span>
            <span class="rounded-full px-2.5 py-1 ring-1"
                :class="{
                    'bg-sky-500/10 text-sky-400 ring-sky-500/20':    policy.trust_tier === 1,
                    'bg-violet-500/10 text-violet-400 ring-violet-500/20': policy.trust_tier === 2,
                    'bg-orange-500/10 text-orange-400 ring-orange-500/20': policy.trust_tier === 3,
                }">
                Trust tier <span x-text="policy.trust_tier"></span>
                <span x-show="policy.trust_tier === 3"> — requires approval</span>
            </span>
        </div>

        {{-- Policy errors --}}
        <template x-if="policy.errors && policy.errors.length">
            <div class="mb-5 rounded-xl bg-red-500/10 p-4 ring-1 ring-red-500/20">
                <p class="mb-2 text-xs font-semibold text-red-400">Policy issues:</p>
                <ul class="list-inside list-disc space-y-1 text-xs text-red-300">
                    <template x-for="err in policy.errors" :key="err">
                        <li x-text="err"></li>
                    </template>
                </ul>
            </div>
        </template>

        {{-- Cost acknowledgment for high-cost --}}
        <div x-show="policy.cost_estimate?.tier === 'high'" class="mb-5">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-amber-500/5 p-4 ring-1 ring-amber-500/20">
                <input type="checkbox" x-model="costAcknowledged" class="mt-0.5 accent-amber-500">
                <span class="text-xs text-amber-300">
                    I understand this automation has a <strong>high cost score</strong> and will consume
                    <strong x-text="policy.cost_estimate?.score"></strong> pts per execution.
                </span>
            </label>
        </div>

        {{-- Raw DSL (collapsible) --}}
        <details class="mb-5 rounded-xl border border-white/8 bg-black/20">
            <summary class="cursor-pointer px-4 py-3 text-xs font-medium text-gray-500 hover:text-gray-300">
                View generated DSL config
            </summary>
            <pre class="overflow-x-auto px-4 pb-4 text-xs text-gray-400" x-text="JSON.stringify(config, null, 2)"></pre>
        </details>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <button
                @click="save()"
                :disabled="saving || (policy.trust_tier >= 3 && policy.errors?.length) || (policy.cost_estimate?.tier === 'high' && !costAcknowledged)"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500 disabled:opacity-40"
            >
                <span x-show="!saving">Confirm & save</span>
                <span x-show="saving" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <button
                @click="reset()"
                class="rounded-xl px-4 py-2.5 text-sm text-gray-400 transition hover:text-white"
            >
                Start over
            </button>
        </div>

        <div x-show="saveError" x-text="saveError" class="mt-3 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-400 ring-1 ring-red-500/20"></div>
    </div>

    {{-- Step 3: Saved --}}
    <div x-show="step === 'saved'" x-transition class="text-center py-6">
        <div class="mb-3 flex justify-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </span>
        </div>
        <p class="text-sm font-medium text-white" x-text="savedStatus === 'pending_approval' ? 'Automation submitted for approval' : 'Automation saved and scheduled'"></p>
        <p class="mt-1 text-xs text-gray-500" x-text="savedStatus === 'pending_approval' ? 'Trust tier 3 features require a dev review before activation.' : 'It will run on its next scheduled time.'"></p>
        <button @click="reset()" class="mt-4 text-xs text-emerald-400 hover:underline">Create another</button>
    </div>
</div>

{{-- Feature list --}}
<div>
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-gray-500">
        Active Automations
        <span class="ml-2 rounded-full bg-white/8 px-2 py-0.5 text-xs font-normal text-gray-400">{{ $features->count() }}</span>
    </h2>

    @if($features->isEmpty())
        <div class="rounded-2xl border border-dashed border-white/10 py-16 text-center text-sm text-gray-600">
            No automations yet. Describe what you want above to get started.
        </div>
    @else
    <div class="grid gap-3">
        @foreach($features as $feature)
        <div
            x-data="{ open: false }"
            class="rounded-2xl border border-white/8 bg-white/3 transition hover:border-white/12"
        >
            <div class="flex cursor-pointer items-center gap-4 px-5 py-4" @click="open = !open">

                {{-- Status dot --}}
                <span class="h-2 w-2 shrink-0 rounded-full
                    {{ $feature->status === 'active'           ? 'bg-emerald-400' : '' }}
                    {{ $feature->status === 'suspended'        ? 'bg-red-400' : '' }}
                    {{ $feature->status === 'degraded'         ? 'bg-amber-400' : '' }}
                    {{ $feature->status === 'pending_approval' ? 'bg-violet-400' : '' }}
                    {{ $feature->status === 'archived'         ? 'bg-gray-600' : '' }}
                    {{ $feature->status === 'promoted'         ? 'bg-sky-400' : '' }}
                "></span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium text-white">
                            {{ ucfirst($feature->data_source) }} — {{ ucfirst($feature->type) }}
                        </p>
                        {{-- Lifecycle badge --}}
                        @if($feature->lifecycle_stage === 'promoted')
                            <span class="rounded-full bg-sky-500/10 px-2 py-0.5 text-xs text-sky-400 ring-1 ring-sky-500/20">
                                ↑ Core capability
                            </span>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-xs text-gray-500">
                        {{ collect($feature->pipeline)->pluck('op')->implode(' → ') }}
                        @if($feature->last_executed_at)
                            · Last run {{ $feature->last_executed_at->diffForHumans() }}
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    {{-- Cost badge --}}
                    <span class="hidden text-xs sm:block
                        {{ ($feature->cost_estimate['tier'] ?? '') === 'low'    ? 'text-green-400' : '' }}
                        {{ ($feature->cost_estimate['tier'] ?? '') === 'medium' ? 'text-amber-400' : '' }}
                        {{ ($feature->cost_estimate['tier'] ?? '') === 'high'   ? 'text-red-400' : '' }}
                    ">{{ $feature->cost_estimate['score'] ?? 0 }} pts</span>

                    {{-- Status label --}}
                    <span class="rounded-full px-2.5 py-1 text-xs ring-1
                        {{ $feature->status === 'active'           ? 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' : '' }}
                        {{ $feature->status === 'suspended'        ? 'bg-red-500/10 text-red-400 ring-red-500/20' : '' }}
                        {{ $feature->status === 'degraded'         ? 'bg-amber-500/10 text-amber-400 ring-amber-500/20' : '' }}
                        {{ $feature->status === 'pending_approval' ? 'bg-violet-500/10 text-violet-400 ring-violet-500/20' : '' }}
                        {{ $feature->status === 'archived'         ? 'bg-gray-500/10 text-gray-400 ring-gray-500/20' : '' }}
                        {{ $feature->status === 'promoted'         ? 'bg-sky-500/10 text-sky-400 ring-sky-500/20' : '' }}
                    ">{{ $feature->status }}</span>

                    {{-- Chevron --}}
                    <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 shrink-0 text-gray-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            {{-- Expanded detail --}}
            <div x-show="open" x-collapse class="border-t border-white/6 px-5 py-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

                    {{-- Pipeline steps --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-600">Pipeline</p>
                        <ol class="space-y-1.5">
                            @foreach($feature->pipeline as $i => $step)
                            <li class="flex items-start gap-2 text-xs">
                                <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded bg-white/8 text-gray-500">{{ $i + 1 }}</span>
                                <span class="text-gray-300">
                                    <strong class="text-gray-200">{{ $step['op'] }}</strong>
                                    @if(isset($step['field']))· {{ $step['field'] }}@endif
                                    @if(isset($step['op_type']))· {{ $step['op_type'] }} {{ is_array($step['value'] ?? null) ? implode(', ', $step['value']) : ($step['value'] ?? '') }}@endif
                                    @if(isset($step['metric']))· {{ $step['metric'] }}@endif
                                    @if(isset($step['window']))· {{ $step['window'] }}@endif
                                    @if(isset($step['type']))· {{ $step['type'] }} @{{ $step['threshold'] ?? '' }}%@endif
                                    @if(isset($step['function']))· {{ $step['function'] }}@endif
                                    @if(isset($step['direction']))· {{ $step['direction'] }}@endif
                                </span>
                            </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Schedule + action --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-600">Schedule</p>
                        @if($feature->schedule)
                        <p class="text-xs text-gray-300">
                            <span class="font-mono">{{ $feature->schedule['expression'] ?? '' }}</span>
                            <span class="text-gray-500"> · {{ $feature->schedule['timezone'] ?? 'UTC' }}</span>
                        </p>
                        @else
                        <p class="text-xs text-gray-600">No schedule (manual)</p>
                        @endif

                        <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-gray-600">Action</p>
                        <p class="text-xs text-gray-300">
                            {{ $feature->action['type'] ?? '' }} via <strong>{{ $feature->action['channel'] ?? '' }}</strong>
                            → {{ $feature->action['recipients'] ?? '' }}
                        </p>
                    </div>

                    {{-- Stats --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-600">Stats</p>
                        @php $executions = $feature->executions; @endphp
                        <dl class="space-y-1 text-xs">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Total executions</dt>
                                <dd class="text-gray-200">{{ $executions->count() }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Success rate</dt>
                                <dd class="text-gray-200">
                                    @php $success = $executions->where('status', 'success')->count(); @endphp
                                    {{ $executions->count() ? round($success / $executions->count() * 100) : 0 }}%
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">DSL version</dt>
                                <dd class="font-mono text-gray-200">{{ $feature->dsl_version }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Contributes to evolution</dt>
                                <dd class="{{ $feature->contributes_to_evolution ? 'text-emerald-400' : 'text-gray-600' }}">
                                    {{ $feature->contributes_to_evolution ? 'Yes' : 'No' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Execution recent history --}}
                @if($executions->count())
                <div class="mt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-600">Recent executions</p>
                    <div class="flex gap-1">
                        @foreach($executions->sortByDesc('started_at')->take(20) as $exec)
                        <div title="{{ $exec->started_at->format('M j') }} — {{ $exec->status }} ({{ $exec->execution_ms }}ms)"
                             class="h-4 w-2 rounded-sm
                            {{ $exec->status === 'success' ? 'bg-emerald-500' : '' }}
                            {{ $exec->status === 'failure' ? 'bg-red-500' : '' }}
                            {{ $exec->status === 'timeout' ? 'bg-amber-500' : '' }}
                        "></div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Archive button --}}
                @if($feature->status !== 'archived')
                <div class="mt-4 flex justify-end">
                    <form method="POST" action="/features/{{ $feature->id }}/archive">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="tenant_id" value="{{ $activeTenant->id }}">
                        <button type="submit" class="text-xs text-gray-600 hover:text-red-400 transition">
                            Archive automation
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

<script>
function featureGenerator(tenantId, csrfToken) {
    return {
        step: 'prompt',
        prompt: '',
        loading: false,
        saving: false,
        error: null,
        saveError: null,
        config: null,
        summary: '',
        policy: {},
        costAcknowledged: false,
        savedStatus: null,

        async generate() {
            if (!this.prompt.trim()) return;
            this.loading = true;
            this.error   = null;

            try {
                const res = await fetch('/api/features/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Tenant-ID':  tenantId,
                    },
                    body: JSON.stringify({ prompt: this.prompt }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.error = data.error || 'Generation failed.';
                    return;
                }

                this.config  = data.config;
                this.summary = data.semantic_summary;
                this.policy  = data.policy;
                this.step    = 'confirm';

            } catch (e) {
                this.error = 'Network error. Is the server running?';
            } finally {
                this.loading = false;
            }
        },

        async save() {
            this.saving    = true;
            this.saveError = null;

            try {
                const res = await fetch('/api/features', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Tenant-ID':  tenantId,
                    },
                    body: JSON.stringify({
                        config:            this.config,
                        confirmed:         true,
                        cost_acknowledged: this.costAcknowledged,
                    }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.saveError = data.errors ? Object.values(data.errors).flat().join(', ') : (data.error || 'Save failed.');
                    return;
                }

                this.savedStatus = data.status;
                this.step        = 'saved';

                // Refresh feature list
                setTimeout(() => window.location.reload(), 1800);

            } catch (e) {
                this.saveError = 'Network error.';
            } finally {
                this.saving = false;
            }
        },

        reset() {
            this.step             = 'prompt';
            this.prompt           = '';
            this.error            = null;
            this.saveError        = null;
            this.config           = null;
            this.summary          = '';
            this.policy           = {};
            this.costAcknowledged = false;
            this.savedStatus      = null;
        },
    };
}
</script>

@endsection
