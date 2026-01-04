<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Validator;

/**
 * Validation Collector
 * 
 * Tracks validation-related events:
 * - Validation failures by field
 * - Rules usage statistics
 * - Custom rules tracking
 * - Validation performance
 */
class ValidationCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $validations = [];
    protected float $startTime;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.validation.enabled', true)) {
            return;
        }

        $this->registerValidatorHook();

        app()->terminating(function () {
            $this->sendCollectedData();
        });
    }

    protected function registerValidatorHook(): void
    {
        // Extend validator to track all validations
        \Illuminate\Support\Facades\Validator::resolver(function ($translator, $data, $rules, $messages, $attributes) {
            $validator = new Validator($translator, $data, $rules, $messages, $attributes);
            
            $this->trackValidation($validator, $rules);
            
            return $validator;
        });
    }

    protected function trackValidation(Validator $validator, array $rules): void
    {
        $startTime = microtime(true);
        
        // Store reference for after validation
        $validator->after(function ($validator) use ($rules, $startTime) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            $validation = [
                'field_count' => count($rules),
                'rule_count' => $this->countRules($rules),
                'rules_used' => $this->extractRuleNames($rules),
                'duration_ms' => round($duration, 2),
                'passed' => !$validator->fails(),
            ];

            if ($validator->fails()) {
                $errors = $validator->errors()->toArray();
                $failedRules = $validator->failed();
                
                $validation['failed_fields'] = array_keys($errors);
                $validation['failed_rules'] = $this->formatFailedRules($failedRules);
                $validation['error_count'] = count($errors);
                
                // Track which rules fail most often
                foreach ($failedRules as $field => $rules) {
                    foreach (array_keys($rules) as $ruleName) {
                        $validation['failed_rule_names'][] = $ruleName;
                    }
                }
            }

            $this->validations[] = $validation;
        });
    }

    protected function countRules(array $rules): int
    {
        $count = 0;
        foreach ($rules as $fieldRules) {
            if (is_string($fieldRules)) {
                $count += count(explode('|', $fieldRules));
            } elseif (is_array($fieldRules)) {
                $count += count($fieldRules);
            }
        }
        return $count;
    }

    protected function extractRuleNames(array $rules): array
    {
        $ruleNames = [];
        
        foreach ($rules as $fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }
            
            foreach ($fieldRules as $rule) {
                if (is_string($rule)) {
                    $name = explode(':', $rule)[0];
                    $ruleNames[$name] = ($ruleNames[$name] ?? 0) + 1;
                } elseif (is_object($rule)) {
                    $name = get_class($rule);
                    $ruleNames[$name] = ($ruleNames[$name] ?? 0) + 1;
                }
            }
        }

        return $ruleNames;
    }

    protected function formatFailedRules(array $failedRules): array
    {
        $formatted = [];
        
        foreach ($failedRules as $field => $rules) {
            $formatted[$field] = array_keys($rules);
        }

        return $formatted;
    }

    protected function sendCollectedData(): void
    {
        if (empty($this->validations)) {
            return;
        }

        $summary = [
            'total_validations' => count($this->validations),
            'passed' => count(array_filter($this->validations, fn($v) => $v['passed'])),
            'failed' => count(array_filter($this->validations, fn($v) => !$v['passed'])),
            'avg_duration_ms' => round(array_sum(array_column($this->validations, 'duration_ms')) / count($this->validations), 2),
            'total_fields_validated' => array_sum(array_column($this->validations, 'field_count')),
            'url' => request()->fullUrl(),
            'route' => optional(request()->route())->getName(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Aggregate most used rules
        $allRules = [];
        foreach ($this->validations as $v) {
            foreach ($v['rules_used'] as $rule => $count) {
                $allRules[$rule] = ($allRules[$rule] ?? 0) + $count;
            }
        }
        arsort($allRules);
        $summary['most_used_rules'] = array_slice($allRules, 0, 10, true);

        // Aggregate most failed fields
        $failedFields = [];
        foreach ($this->validations as $v) {
            if (isset($v['failed_fields'])) {
                foreach ($v['failed_fields'] as $field) {
                    $failedFields[$field] = ($failedFields[$field] ?? 0) + 1;
                }
            }
        }
        if (!empty($failedFields)) {
            arsort($failedFields);
            $summary['most_failed_fields'] = array_slice($failedFields, 0, 10, true);
        }

        $this->baddybugs->record('validation', 'summary', $summary);
    }
}
