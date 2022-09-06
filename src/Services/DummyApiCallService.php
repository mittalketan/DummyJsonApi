<?php

namespace App\Services;


use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DummyApiCallService
{
    private $client;
    private $params;

    public function __construct(HttpClientInterface $client, ParameterBagInterface $params)
    {
        $this->client = $client;
        $this->params = $params;
    }

    public function fetchGitHubInformation(string $type, $limit = 0, $skip = 0): array
    {
        try {
            $apiURL =  $this->params->get('dummyapi');
            $apiURL = $apiURL . $type;

            if ($limit != 0) {
                $apiURL = $apiURL . '?limit=' . $limit;

                if ($skip != 0) {
                    $apiURL = $apiURL . '&skip=' . $skip;
                }
            }

            $response = $this->client->request(
                'GET',
                $apiURL
            );

            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                throw new \Exception('Error');
            }
            return $response->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
