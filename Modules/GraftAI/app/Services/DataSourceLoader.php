<?php

namespace Modules\GraftAI\Services;

use Modules\GraftAI\Models\CapabilityRegistry;
use Illuminate\Support\Collection;

/**
 * Loads data for a given data_source scoped to a tenant.
 *
 * Security invariant: tenant_id comes from the execution context (never from config).
 * Field names are validated against the capability allowlist before this is called,
 * so only allowlisted fields are ever queried.
 */
class DataSourceLoader
{
    /**
     * Returns the raw dataset for a data source, scoped to tenant.
     *
     * @param  string  $dataSource  Active capability name (e.g. 'crop_prices')
     * @param  string  $tenantId    From execution context — never from config
     */
    public function load(string $dataSource, string $tenantId): Collection
    {
        $capability = CapabilityRegistry::where('name', $dataSource)
            ->where('status', 'active')
            ->firstOrFail();

        $method = 'load' . str_replace('_', '', ucwords($dataSource, '_'));

        if (method_exists($this, $method)) {
            return $this->{$method}($tenantId, $capability->fields);
        }

        throw new \RuntimeException("No loader registered for data source: {$dataSource}");
    }

    /**
     * Example loader for 'crop_prices' data source.
     *
     * SECURITY: Always filter by tenant_id from execution context.
     * SECURITY: Only select fields in the capability allowlist.
     */
    protected function loadCropPrices(string $tenantId, array $allowedFields): Collection
    {
        // Example: CropPrice::where('tenant_id', $tenantId)->get($allowedFields)->toBase()
        return collect([]);
    }
}
