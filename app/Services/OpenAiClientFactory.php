<?php

namespace App\Services;

use OpenAI;

class OpenAiClientFactory
{
    /**
     * Build the configured OpenAI client at the provider boundary.
     */
    public function make(string $apiKey): mixed
    {
        return OpenAI::client($apiKey);
    }
}
