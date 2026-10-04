<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class PredictiveAnalyticsEndpointTest extends TestCase
{
    public function test_churn_endpoint_uses_a_mocked_provider_contract(): void
    {
        $completions = Mockery::mock();
        $completions
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['model'] === 'gpt-3.5-turbo'
                    && $payload['max_tokens'] === 100
                    && $payload['temperature'] === 0.7
                    && str_contains($payload['prompt'], '- Age: 30')
                    && str_contains($payload['prompt'], '- Average Monthly Activity: 15')
                    && str_contains($payload['prompt'], '- Payment History Score: 9.2')
                    && str_contains($payload['prompt'], '- Feature 4: 12.3')
                    && str_contains($payload['prompt'], '- Feature 5: 7.8');
            }))
            ->andReturn([
                'choices' => [
                    ['text' => " 78%\n"],
                ],
            ]);

        $client = Mockery::mock();
        $client
            ->shouldReceive('completions')
            ->once()
            ->andReturn($completions);

        Mockery::mock('alias:OpenAI')
            ->shouldReceive('client')
            ->once()
            ->with('test-key')
            ->andReturn($client);

        $previousKey = getenv('OPENAI_API_KEY');
        $hadEnvKey = array_key_exists('OPENAI_API_KEY', $_ENV);
        $previousEnvKey = $_ENV['OPENAI_API_KEY'] ?? null;
        $hadServerKey = array_key_exists('OPENAI_API_KEY', $_SERVER);
        $previousServerKey = $_SERVER['OPENAI_API_KEY'] ?? null;

        putenv('OPENAI_API_KEY=test-key');
        $_ENV['OPENAI_API_KEY'] = 'test-key';
        $_SERVER['OPENAI_API_KEY'] = 'test-key';

        try {
            $response = $this->postJson('/api/predict-churn', [
                'age' => 30,
                'avg_monthly_activity' => 15,
                'payment_history_score' => 9.2,
                'feature_4' => 12.3,
                'feature_5' => 7.8,
            ]);
        } finally {
            if ($previousKey === false) {
                putenv('OPENAI_API_KEY');
            } else {
                putenv('OPENAI_API_KEY=' . $previousKey);
            }

            if ($hadEnvKey) {
                $_ENV['OPENAI_API_KEY'] = $previousEnvKey;
            } else {
                unset($_ENV['OPENAI_API_KEY']);
            }

            if ($hadServerKey) {
                $_SERVER['OPENAI_API_KEY'] = $previousServerKey;
            } else {
                unset($_SERVER['OPENAI_API_KEY']);
            }
        }

        $response
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'churn_probability' => '78%',
            ]);
    }
}
