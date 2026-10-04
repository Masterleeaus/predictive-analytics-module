<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChurnPredictionProvider;
use App\Services\OpenAiClientFactory;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PredictiveAnalyticsEndpointTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_churn_endpoint_uses_a_mocked_provider_contract(): void
    {
        Sanctum::actingAs(User::factory()->make());

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

        $factory = Mockery::mock(OpenAiClientFactory::class);
        $factory
            ->shouldReceive('make')
            ->once()
            ->with('test-key')
            ->andReturn($client);

        $this->app->instance(OpenAiClientFactory::class, $factory);
        config(['services.openai.key' => 'test-key']);

        $response = $this->postJson('/api/v1/predict-churn', $this->validPayload());

        $response
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'churn_probability' => '78%',
            ]);
    }

    public function test_unauthenticated_requests_are_rejected_before_provider_invocation(): void
    {
        $provider = Mockery::mock(ChurnPredictionProvider::class);
        $provider->shouldReceive('predict')->never();
        $this->app->instance(ChurnPredictionProvider::class, $provider);

        $response = $this->postJson('/api/v1/predict-churn', $this->validPayload());

        $response->assertUnauthorized();
    }

    public function test_malformed_payload_is_rejected_before_provider_invocation(): void
    {
        Sanctum::actingAs(User::factory()->make());

        $provider = Mockery::mock(ChurnPredictionProvider::class);
        $provider->shouldReceive('predict')->never();
        $this->app->instance(ChurnPredictionProvider::class, $provider);

        $response = $this->postJson('/api/v1/predict-churn', [
            ...$this->validPayload(),
            'age' => 'thirty',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['age']);
    }

    public function test_out_of_bounds_payload_is_rejected_before_provider_invocation(): void
    {
        Sanctum::actingAs(User::factory()->make());

        $provider = Mockery::mock(ChurnPredictionProvider::class);
        $provider->shouldReceive('predict')->never();
        $this->app->instance(ChurnPredictionProvider::class, $provider);

        $response = $this->postJson('/api/v1/predict-churn', [
            ...$this->validPayload(),
            'payment_history_score' => -1,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_history_score']);
    }

    public function test_provider_is_disabled_when_api_key_is_missing(): void
    {
        Sanctum::actingAs(User::factory()->make());
        config(['services.openai.key' => null]);

        $response = $this->postJson('/api/v1/predict-churn', $this->validPayload());

        $response
            ->assertStatus(503)
            ->assertExactJson([
                'success' => false,
                'message' => 'Prediction provider is not configured.',
            ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function validPayload(): array
    {
        return [
            'age' => 30,
            'avg_monthly_activity' => 15,
            'payment_history_score' => 9.2,
            'feature_4' => 12.3,
            'feature_5' => 7.8,
        ];
    }
}
