<?php

namespace App\Services;

class ChurnPredictionProvider
{
    public function __construct(
        private readonly OpenAiClientFactory $clientFactory,
    ) {
    }

    /**
     * Call the configured provider for a validated set of churn signals.
     *
     * A missing key disables the external call instead of attempting a
     * credential-less request. The caller can surface that as a service
     * configuration response.
     *
     * @param array<string, int|float|string> $signals
     */
    public function predict(array $signals): ?string
    {
        $apiKey = config('services.openai.key');

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $prompt = "
        You are an expert data analyst. Predict customer churn based on the following data:
        - Age: {$signals['age']}
        - Average Monthly Activity: {$signals['avg_monthly_activity']}
        - Payment History Score: {$signals['payment_history_score']}
        - Feature 4: {$signals['feature_4']}
        - Feature 5: {$signals['feature_5']}

        Return the churn probability as a percentage (e.g., 78%).
        ";

        $response = $this->clientFactory
            ->make($apiKey)
            ->completions()
            ->create([
                'model' => 'gpt-3.5-turbo',
                'prompt' => $prompt,
                'max_tokens' => 100,
                'temperature' => 0.7,
            ]);

        $prediction = trim((string) ($response['choices'][0]['text'] ?? ''));

        return $prediction !== '' ? $prediction : 'Unable to generate prediction';
    }
}
