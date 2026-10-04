<?php

namespace App\Http\Controllers;

use App\Services\ChurnPredictionProvider;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictiveAnalyticsController extends Controller
{
    public function __construct(
        private readonly ChurnPredictionProvider $predictionProvider,
    ) {
    }

    /**
     * Predict customer churn using the configured provider.
     */
    public function predictChurn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'age' => $this->boundedNumericRules('between:0,120'),
            'avg_monthly_activity' => $this->boundedNumericRules('between:0,1000000'),
            'payment_history_score' => $this->boundedNumericRules('between:0,10'),
            'feature_4' => $this->boundedNumericRules('between:0,1000000'),
            'feature_5' => $this->boundedNumericRules('between:0,1000000'),
        ]);

        $prediction = $this->predictionProvider->predict($validated);

        if ($prediction === null) {
            return response()->json([
                'success' => false,
                'message' => 'Prediction provider is not configured.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'churn_probability' => $prediction,
        ]);
    }

    /**
     * Keep numeric input finite and bounded before Laravel's range rules run.
     *
     * This rejects exponent notation, oversized decimal strings, and
     * non-finite JSON numbers before min/between can perform arithmetic.
     *
     * @return array<int, string|Closure>
     */
    private function boundedNumericRules(string $range): array
    {
        return [
            'bail',
            'required',
            static function (string $attribute, mixed $value, Closure $fail): void {
                $valid = match (true) {
                    is_int($value) => strlen(ltrim((string) $value, '-')) <= 15,
                    is_float($value) => is_finite($value) && abs($value) <= 1_000_000_000_000_000,
                    is_string($value) => preg_match('/\A-?\d{1,15}(?:\.\d{1,6})?\z/D', $value) === 1,
                    default => false,
                };

                if (!$valid) {
                    $fail("The {$attribute} must be a finite decimal value within the supported input size.");
                }
            },
            'numeric',
            $range,
        ];
    }
}
