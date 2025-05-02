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
            $elasticsearchHost = config('services.elasticsearch.host');
            $elasticsearchApiKey = config('services.elasticsearch.api_key');

            if (!$elasticsearchHost || !$elasticsearchApiKey) {
                throw new \Exception('Elasticsearch configuration missing');
            }

            $elasticsearch = ClientBuilder::create()
                ->setHosts([$elasticsearchHost])
                ->setApiKey($elasticsearchApiKey)
                ->build();

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