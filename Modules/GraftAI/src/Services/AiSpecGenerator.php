<?php

namespace GraftAI\Services;

use GraftAI\Dsl\DslDefinition;
use GraftAI\Models\CapabilityRegistry;
use GraftAI\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Spec Generator — converts natural language → DSL config.
 *
 * Security invariants (spec §9):
 *  - System prompt exposes ONLY the tenant's granted operators and fields.
 *  - AI output is treated as untrusted input until it passes the syntactic
 *    parser and Stage 1 policy engine.
 *  - filter.value must be a literal; never a formula or field reference.
 *  - Cross-tenant requests are rejected by the prompt and re-checked at policy.
 */
class AiSpecGenerator
{
    private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';

    private const MODEL = 'claude-haiku-4-5-20251001';

    public function __construct(
        private readonly string $apiKey = '',
    ) {}

    /**
     * @return array{config: array|null, error: string|null}
     */
    public function generate(string $prompt, Tenant $tenant): array
    {
        $systemPrompt = $this->buildSystemPrompt($tenant);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey ?: config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post(self::ANTHROPIC_API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('AiSpecGenerator: API error', ['status' => $response->status()]);

                return ['config' => null, 'error' => 'AI service unavailable.'];
            }

            $text = $response->json('content.0.text') ?? '';

            return $this->parseOutput($text);

        } catch (\Throwable $e) {
            Log::error('AiSpecGenerator: exception', ['message' => $e->getMessage()]);

            return ['config' => null, 'error' => 'AI service error: '.$e->getMessage()];
        }
    }

    private function buildSystemPrompt(Tenant $tenant): string
    {
        $capabilities = CapabilityRegistry::activeCapabilityNames();
        $operatorList = implode(', ', DslDefinition::OPERATORS);
        $fieldAllowlist = [];

        foreach ($capabilities as $cap) {
            $fields = CapabilityRegistry::fieldAllowlistFor($cap);
            $fieldAllowlist[$cap] = $fields;
        }

        $fieldAllowlistJson = json_encode($fieldAllowlist, JSON_PRETTY_PRINT);
        $capabilityList = implode(', ', $capabilities);

        return <<<PROMPT
        You are a configuration generator for a farm analytics platform.
        Convert the user's request into a JSON pipeline config.

        Rules:
        - Return ONLY valid JSON. No explanation. No markdown fences.
        - Use only operators from this list: {$operatorList}
        - Use only these data sources: {$capabilityList}
        - Use only these fields per data source: {$fieldAllowlistJson}
        - filter.value must be a literal string or number only.
          Never a formula, expression, or field reference.
        - If the request cannot be expressed with available operators, return:
          {"error": "unsupported_request", "reason": "<brief reason>"}
        - If the request would require cross-tenant data, return:
          {"error": "scope_violation", "reason": "cross-tenant access not permitted"}

        The pipeline must conform to this structure:
        {
          "type": "<alert|report>",
          "data_source": "<one of the available data sources>",
          "pipeline": [
            {"op": "<operator>", ...operator fields}
          ],
          "action": {
            "type": "notification",
            "channel": "<sms|email|push|in_app>",
            "recipients": "tenant_owner"
          },
          "schedule": {
            "type": "cron",
            "expression": "<cron expression>",
            "timezone": "<IANA timezone>"
          }
        }
        PROMPT;
    }

    /**
     * Parse and validate AI output as untrusted input.
     *
     * @return array{config: array|null, error: string|null}
     */
    private function parseOutput(string $text): array
    {
        $text = trim($text);

        // Strip markdown fences if AI ignored the instruction
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['config' => null, 'error' => 'AI returned invalid JSON.'];
        }

        // AI returned an explicit error
        if (isset($decoded['error'])) {
            return [
                'config' => null,
                'error' => $decoded['reason'] ?? $decoded['error'],
            ];
        }

        return ['config' => $decoded, 'error' => null];
    }
}
