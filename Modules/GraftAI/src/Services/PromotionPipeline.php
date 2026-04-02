<?php

namespace GraftAI\Services;

use GraftAI\Dsl\DslDefinition;
use GraftAI\Models\AuditEvent;
use GraftAI\Models\CapabilityRegistry;
use GraftAI\Models\EvolutionEvent;
use GraftAI\Models\FeatureConfig;
use GraftAI\Models\FeatureSnapshot;
use GraftAI\Models\PromotionCandidate;
use Illuminate\Support\Facades\DB;

/**
 * Promotion Pipeline — Stage 4 of the evolution loop.
 *
 * Steps:
 *  1. Write new operator/capability to DSL spec
 *  2. Increment DSL minor version
 *  3. Register new capability in CapabilityRegistry
 *  4. Run Migration Engine: update sandbox configs using old pattern
 *  5. Notify opted-in tenants
 *  6. Mark sandbox features as status=promoted
 *  7. Original sandbox configs remain executable (no forced migration)
 *
 * System snapshot is created before every promotion.
 */
class PromotionPipeline
{
    public function promote(PromotionCandidate $candidate, string $operatorName, string $promotedBy): void
    {
        if ($candidate->status !== 'approved') {
            throw new \RuntimeException('Cannot promote a candidate that is not approved.');
        }

        DB::transaction(function () use ($candidate, $operatorName, $promotedBy) {
            $currentDslVersion = $this->currentDslVersion();
            $nextDslVersion    = $this->incrementMinorVersion($currentDslVersion);

            // System snapshot before promotion
            FeatureSnapshot::system(
                "pre-dsl-{$nextDslVersion}-promotion",
                $currentDslVersion,
            );

            // Register new capability
            $capability = CapabilityRegistry::create([
                'name'              => $operatorName,
                'ops'               => ['shortcut'],
                'fields'            => [],
                'introduced_in_dsl' => $nextDslVersion,
                'introduced_by'     => "promotion:{$candidate->id}",
                'status'            => 'active',
                'description'       => "Promoted from sandbox pattern: {$candidate->pipeline_signature}",
            ]);

            // Mark sandbox features that used this pattern as promoted
            FeatureConfig::where('pipeline_signature', $candidate->pipeline_signature)
                ->update(['promoted_to_core' => true, 'lifecycle_stage' => 'promoted']);

            // Record evolution event (immutable)
            EvolutionEvent::record([
                'type'                    => 'operator_promoted',
                'operator_name'           => $operatorName,
                'promoted_from_signature' => $candidate->pipeline_signature,
                'dsl_version_before'      => $currentDslVersion,
                'dsl_version_after'       => $nextDslVersion,
                'contributing_tenant_count' => $candidate->distinct_tenants,
                'promoted_by'             => $promotedBy,
                'notes'                   => "Candidate {$candidate->id} promoted via governance review.",
            ]);

            // Update candidate status
            $candidate->update([
                'status'           => 'promoted',
                'promoted_at'      => now(),
                'dsl_version_after' => $nextDslVersion,
            ]);

            AuditEvent::log(null, null, 'operator_promoted', $promotedBy, [
                'operator_name'      => $operatorName,
                'dsl_version_before' => $currentDslVersion,
                'dsl_version_after'  => $nextDslVersion,
                'candidate_id'       => $candidate->id,
            ]);
        });
    }

    /**
     * Un-evolution: roll back a promoted capability.
     */
    public function rollback(CapabilityRegistry $capability, string $rollbackBy, string $notes = ''): void
    {
        DB::transaction(function () use ($capability, $rollbackBy, $notes) {
            $currentDslVersion = $this->currentDslVersion();
            $patchVersion      = $this->patchVersion($currentDslVersion);

            // Deprecate the capability
            $capability->update([
                'status'           => 'deprecated',
                'deprecated_in_dsl' => $patchVersion,
            ]);

            // Mark previously-promoted features as active again (no forced migration)
            FeatureConfig::where('pipeline_signature', $this->signatureForCapability($capability))
                ->where('lifecycle_stage', 'promoted')
                ->update(['lifecycle_stage' => 'sandbox', 'promoted_to_core' => false]);

            // Record un-evolution event
            EvolutionEvent::record([
                'type'               => 'system_rollback',
                'operator_name'      => $capability->name,
                'dsl_version_before' => $currentDslVersion,
                'dsl_version_after'  => $patchVersion,
                'promoted_by'        => $rollbackBy,
                'notes'              => $notes,
            ]);

            // Mark candidate as promoted_then_reverted
            PromotionCandidate::where('pipeline_signature', $this->signatureForCapability($capability))
                ->update(['status' => 'promoted_then_reverted']);

            AuditEvent::log(null, null, 'capability_rolled_back', $rollbackBy, [
                'capability_name'    => $capability->name,
                'dsl_version_patch'  => $patchVersion,
                'notes'              => $notes,
            ]);
        });
    }

    private function currentDslVersion(): string
    {
        $latest = CapabilityRegistry::orderByDesc('introduced_in_dsl')->value('introduced_in_dsl');

        return $latest ?? DslDefinition::CURRENT_VERSION;
    }

    private function incrementMinorVersion(string $version): string
    {
        $parts    = explode('.', $version);
        $parts[1] = (int) ($parts[1] ?? 0) + 1;

        return implode('.', $parts);
    }

    private function patchVersion(string $version): string
    {
        $parts    = explode('.', $version);
        $parts[2] = (int) ($parts[2] ?? 0) + 1;

        return implode('.', $parts);
    }

    private function signatureForCapability(CapabilityRegistry $capability): ?string
    {
        $event = EvolutionEvent::where('operator_name', $capability->name)
            ->whereNotNull('promoted_from_signature')
            ->first();

        return $event?->promoted_from_signature;
    }
}
