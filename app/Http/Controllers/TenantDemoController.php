<?php

namespace App\Http\Controllers;

use App\Dsl\DslDefinition;
use App\Models\CapabilityRegistry;
use App\Models\FeatureConfig;
use App\Models\Tenant;
use App\Models\TenantBudget;
use Illuminate\Http\Request;

class TenantDemoController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            return view('tenant.empty');
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

        return view('tenant.index', compact(
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
