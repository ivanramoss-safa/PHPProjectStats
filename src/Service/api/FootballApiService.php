<?php

namespace App\Service\api;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FootballApiService
{
    private $client;
    private $apiKey;
    private $cache;

    // Inyectamos el HttpClient (para hacer peticiones) y el Cache (para guardar datos)
    // El $apiKey lo traemos del archivo .env usando inyección de dependencias (ver paso 2.2)
    public function __construct(HttpClientInterface $client, CacheInterface $cache, string $apiKey)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->apiKey = $apiKey;
    }

    // Función genérica para hacer peticiones con caché
    public function getCachedData(string $endpoint, array $params = [], int $cacheSeconds = 3600): array
    {
        // Creamos una clave única para la caché basada en el endpoint y los parámetros
        // Ejemplo de clave: "api_leagues_2023"
        $cacheKey = 'api_' . str_replace('/', '_', $endpoint) . '_' . md5(json_encode($params));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $params, $cacheSeconds) {

            // Si no está en caché, este código se ejecuta:
            $item->expiresAfter($cacheSeconds); // Tiempo de vida de la caché

            // Hacemos la petición real a la API
            $response = $this->client->request('GET', 'https://v3.football.api-sports.io/' . $endpoint, [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => $params,
            ]);

            // Convertimos la respuesta JSON a un array PHP
            return $response->toArray();
        });
    }

    // --- MÉTODOS ESPECÍFICOS PARA TU PROYECTO ---

    // Obtener ligas (RF6)
    public function getLeagues(): array
    {
        // Cacheamos esto por 24 horas (86400 segundos) porque las ligas no cambian cada minuto
        return $this->getCachedData('leagues', [], 86400);
    }

    // Obtener partidos en vivo (Home)
    public function getLiveFixtures(): array
    {
        // Esto cambia rápido, caché corta de 5 minutos (300 segundos)
        return $this->getCachedData('fixtures', ['live' => 'all'], 300);
    }
}
