<?php

namespace Modules\GraftAI\Http\Controllers;

use Modules\GraftAI\Dsl\DslDefinition;
use Modules\GraftAI\Models\CapabilityRegistry;
use Modules\GraftAI\Models\FeatureConfig;
use Modules\GraftAI\Models\Tenant;
use Modules\GraftAI\Models\TenantBudget;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TenantDemoController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            return view('graftai::tenant.empty');
        }

        $activeTenantId = $request->input('tenant_id', $tenants->first()->id);
        $activeTenant   = $tenants->firstWhere('id', $activeTenantId) ?? $tenants->first();

        $features = FeatureConfig::where('tenant_id', $activeTenant->id)
            ->with(['executions' => fn($q) => $q->latest('started_at')->limit(20)])
            ->orderByDesc('created_at')
            ->get();

        $budget = TenantBudget::getOrCreate($activeTenant->id);

        $dslVersion = CapabilityRegistry::orderByDesc('introduced_in_dsl')
            ->value('introduced_in_dsl') ?? DslDefinition::CURRENT_VERSION;

        return view('graftai::tenant.index', compact(
            'tenants', 'activeTenant', 'features', 'budget', 'dslVersion',
        ));
    }

    public function archive(Request $request, string $id)
    {
        $tenant  = Tenant::findOrFail($request->input('tenant_id', ''));
        $feature = FeatureConfig::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $feature->update(['status' => 'archived']);

        return redirect()->back()->with('success', 'Automation archived.');
    }
}
