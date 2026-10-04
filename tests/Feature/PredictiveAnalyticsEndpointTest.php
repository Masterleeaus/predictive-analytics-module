<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChurnPredictionProvider;
use App\Services\OpenAiClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PredictiveAnalyticsEndpointTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

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
        $this->preventProviderInvocation();

        $response = $this->postJson('/api/v1/predict-churn', $this->validPayload());

        $response->assertUnauthorized();
    }

    #[DataProvider('nonJsonAcceptHeaders')]
    public function test_api_auth_failures_return_json_without_json_accept_header(array $headers): void
    {
        $this->preventProviderInvocation();

        $response = $this
            ->withHeaders($headers)
            ->post('/api/v1/predict-churn', json_encode($this->validPayload(), JSON_THROW_ON_ERROR));

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public static function nonJsonAcceptHeaders(): array
    {
        return [
            'missing Accept header' => [
                ['Content-Type' => 'application/json'],
            ],
            'wildcard Accept header' => [
                [
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json',
                ],
            ],
            'HTML Accept header' => [
                [
                    'Accept' => 'text/html',
                    'Content-Type' => 'application/json',
                ],
            ],
        ];
    }

    public function test_authenticated_bearer_token_is_accepted_until_revoked(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('regression-token');

        $provider = Mockery::mock(ChurnPredictionProvider::class);
        $provider
            ->shouldReceive('predict')
            ->once()
            ->andReturn('78%');
        $this->app->instance(ChurnPredictionProvider::class, $provider);

        $accepted = $this
            ->withToken($accessToken->plainTextToken)
            ->postJson('/api/v1/predict-churn', $this->validPayload());

        $accepted
            ->assertOk()
            ->assertJson([
                'success' => true,
                'churn_probability' => '78%',
            ]);

        $accessToken->accessToken->delete();

        $revoked = $this
            ->withToken($accessToken->plainTextToken)
            ->postJson('/api/v1/predict-churn', $this->validPayload());

        $revoked->assertUnauthorized();
    }

    public function test_malformed_payload_is_rejected_before_provider_invocation(): void
    {
        Sanctum::actingAs(User::factory()->make());
        $this->preventProviderInvocation();

        $response = $this->postJson('/api/v1/predict-churn', [
            ...$this->validPayload(),
            'age' => 'thirty',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['age']);
    }

    public function test_extreme_exponent_is_rejected_before_range_validation_or_provider_invocation(): void
    {
        Sanctum::actingAs(User::factory()->make());
        $this->preventProviderInvocation();

        $response = $this->postJson('/api/v1/predict-churn', [
            ...$this->validPayload(),
            'age' => '1e1001',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['age']);
    }

    public function test_oversized_numeric_string_is_rejected_before_range_validation_or_provider_invocation(): void
    {
        Sanctum::actingAs(User::factory()->make());
        $this->preventProviderInvocation();

        $response = $this->postJson('/api/v1/predict-churn', [
            ...$this->validPayload(),
            'avg_monthly_activity' => str_repeat('9', 16),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avg_monthly_activity']);
    }

    public function test_out_of_bounds_payload_is_rejected_before_provider_invocation(): void
    {
        Sanctum::actingAs(User::factory()->make());
        $this->preventProviderInvocation();

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

    private function preventProviderInvocation(): void
    {
        $provider = Mockery::mock(ChurnPredictionProvider::class);
        $provider->shouldReceive('predict')->never();
        $this->app->instance(ChurnPredictionProvider::class, $provider);
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
