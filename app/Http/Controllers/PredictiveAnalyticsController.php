<?php

namespace App\Http\Controllers;

use App\Services\ChurnPredictionProvider;
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
            'age' => ['required', 'numeric', 'between:0,120'],
            'avg_monthly_activity' => ['required', 'numeric', 'min:0'],
            'payment_history_score' => ['required', 'numeric', 'between:0,10'],
            'feature_4' => ['required', 'numeric', 'min:0'],
            'feature_5' => ['required', 'numeric', 'min:0'],
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
}
