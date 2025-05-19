<?php

namespace App\Providers;

use App\Services\RagService;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use OpenAI\Client as OpenAIClient;
use OpenAI\Factory;

class RagServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register OpenAI Client
        $this->app->singleton(OpenAIClient::class, function ($app) {
            $apiKey = config('services.openai.api_key');

            if (!$apiKey) {
                throw new \Exception('OpenAI API key not configured');
            }

            return (new Factory())->withApiKey($apiKey)->make();
        });

        // Register RagService
        $this->app->singleton(RagService::class, function ($app) {
            $elasticsearchHost = config('services.elasticsearch.host') ?? 'http://localhost:9200';
            $elasticsearchApiKey = config('services.elasticsearch.api_key');

            $clientBuilder = ClientBuilder::create()
                ->setHosts([$elasticsearchHost]);

            // Only set API key if it exists (for secured ES)
            if (!empty($elasticsearchApiKey)) {
                $clientBuilder->setApiKey($elasticsearchApiKey);
            } else {
                logger()->info('Elasticsearch API key not set — skipping authentication (likely in dev mode).');
            }

            $elasticsearch = $clientBuilder->build();

            return new RagService(
                $app->make(OpenAIClient::class),
                $elasticsearch
            );
        });
    }

    public function boot()
    {
        //
    }
}
