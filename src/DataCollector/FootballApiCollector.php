<?php

namespace App\DataCollector;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsDataCollector;


class FootballApiCollector extends AbstractDataCollector
{
    private FootballApiService $apiService;

    public function __construct(FootballApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data = $this->apiService->getApiUsage();
    }

    public function getUsed(): int
    {
        return $this->data['used'] ?? 0;
    }

    public function getLimit(): int
    {
        return $this->data['limit'] ?? 100;
    }

    public function getRemaining(): int
    {
        return $this->data['remaining'] ?? 0;
    }

    public function getUpdatedAt(): string
    {
        return $this->data['updated_at'] ?? 'Never';
    }

    public function getName(): string
    {
        return 'football_api';
    }
}
