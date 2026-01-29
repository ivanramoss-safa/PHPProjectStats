<?php

namespace App\Service\api;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FootballApiService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private CacheInterface $cache;

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

    // ========================================
    // PARTIDOS (FIXTURES)
    // ========================================

    public function getLiveFixtures(): array
    {
        return $this->getCachedData('fixtures', ['live' => 'all'], 60);
    }

    public function getFixturesByDate(string $date): array
    {
        return $this->getCachedData('fixtures', ['date' => $date], 3600);
    }

    public function getFixturesByLeague(int $leagueId, int $season): array
    {
        return $this->getCachedData('fixtures', ['league' => $leagueId, 'season' => $season], 86400);
    }

    public function getFixtureById(int $fixtureId): array
    {
        $data = $this->getCachedData('fixtures', ['id' => $fixtureId], 300);

        if(!empty($data['response'][0]['fixture']['status']['short'])
            && in_array($data['response'][0]['fixture']['status']['short'], ['FT', 'AET', 'PEN'])) {
            return $this->getCachedData('fixtures', ['id' => $fixtureId], 86400);
        }

        return $data;
    }

    public function getFixtureStatistics(int $fixtureId): array
    {
        return $this->getCachedData('fixtures/statistics', ['fixture' => $fixtureId], 86400);
    }

    public function getFixtureLineups(int $fixtureId): array
    {
        return $this->getCachedData('fixtures/lineups', ['fixture' => $fixtureId], 86400);
    }

    public function getFixtureEvents(int $fixtureId): array
    {
        return $this->getCachedData('fixtures/events', ['fixture' => $fixtureId], 86400);
    }

    // ========================================
    // LIGAS
    // ========================================

    public function getLeagues(): array
    {
        return $this->getCachedData('leagues', [], 604800);
    }

    public function getLeaguesByCountry(string $country): array
    {
        return $this->getCachedData('leagues', ['country' => $country], 604800);
    }

    public function getLeagueById(int $leagueId): array
    {
        return $this->getCachedData('leagues', ['id' => $leagueId], 604800);
    }

    public function getStandings(int $leagueId, int $season): array
    {
        return $this->getCachedData('standings', ['league' => $leagueId, 'season' => $season], 3600);
    }

    // ========================================
    // EQUIPOS
    // ========================================

    public function getTeamsByLeague(int $leagueId, int $season): array
    {
        return $this->getCachedData('teams', ['league' => $leagueId, 'season' => $season], 86400);
    }

    public function getTeamById(int $teamId): array
    {
        return $this->getCachedData('teams', ['id' => $teamId], 604800);
    }

    public function getTeamStatistics(int $teamId, int $leagueId, int $season): array
    {
        return $this->getCachedData('teams/statistics', ['team' => $teamId, 'league' => $leagueId, 'season' => $season], 86400);
    }

    public function getTeamTrophies(int $teamId): array
    {
        return $this->getCachedData('leagues', ['team' => $teamId], 86400);
    }

    // ========================================
    // JUGADORES
    // ========================================

    public function getSquad(int $teamId): array
    {
        return $this->getCachedData('players/squads', ['team' => $teamId], 86400);
    }

    public function getPlayerStatistics(int $playerId, int $season): array
    {
        return $this->getCachedData('players', ['id' => $playerId, 'season' => $season], 86400);
    }

    public function getTopScorers(int $leagueId, int $season): array
    {
        return $this->getCachedData('players/topscorers', ['league' => $leagueId, 'season' => $season], 3600);
    }

    public function getTopAssists(int $leagueId, int $season): array
    {
        return $this->getCachedData('players/topassists', ['league' => $leagueId, 'season' => $season], 3600);
    }

    public function getTopYellowCards(int $leagueId, int $season): array
    {
        return $this->getCachedData('players/topyellowcards', ['league' => $leagueId, 'season' => $season], 3600);
    }

    public function getTopRedCards(int $leagueId, int $season): array
    {
        return $this->getCachedData('players/topredcards', ['league' => $leagueId, 'season' => $season], 3600);
    }

    // ========================================
    // LESIONES Y SANCIONES
    // ========================================

    public function getInjuriesByTeam(int $teamId, int $season): array
    {
        return $this->getCachedData('injuries', ['team' => $teamId, 'season' => $season], 3600);
    }

    public function getInjuriesByFixture(int $fixtureId): array
    {
        return $this->getCachedData('injuries', ['fixture' => $fixtureId], 86400);
    }

    // ========================================
    // ENTRENADORES
    // ========================================

    public function getCoachById(int $coachId): array
    {
        return $this->getCachedData('coachs', ['id' => $coachId], 604800);
    }

    public function searchCoach(string $name): array
    {
        return $this->getCachedData('coachs', ['search' => $name], 2592000);
    }

    // ========================================
    // ESTADIOS
    // ========================================

    public function getVenueById(int $venueId): array
    {
        return $this->getCachedData('venues', ['id' => $venueId], 2592000);
    }

    public function searchVenue(string $name): array
    {
        return $this->getCachedData('venues', ['search' => $name], 2592000);
    }

    // ========================================
    // PALMARÉS
    // ========================================

    public function getPlayerTrophies(int $playerId): array
    {
        return $this->getCachedData('trophies', ['player' => $playerId], 604800);
    }

    public function getCoachTrophies(int $coachId): array
    {
        return $this->getCachedData('trophies', ['coach' => $coachId], 604800);
    }

    // ========================================
    // FICHAJES
    // ========================================

    public function getPlayerTransfers(int $playerId): array
    {
        return $this->getCachedData('transfers', ['player' => $playerId], 86400);
    }

    public function getTeamTransfers(int $teamId): array
    {
        return $this->getCachedData('transfers', ['team' => $teamId], 86400);
    }

    // ========================================
    // 🔍 BUSCADORES (¡NUEVO E IMPRESCINDIBLE!)
    // ========================================

    // Para la barra de búsqueda: "Buscar equipo..."
    public function searchTeam(string $name): array
    {
        // Cacheamos 1 mes, los equipos no cambian de nombre a menudo
        return $this->getCachedData('teams', ['search' => $name], 2592000);
    }

    // Para la barra de búsqueda: "Buscar jugador..."
    public function searchPlayer(string $name, int $teamId = null): array
    {
        $params = ['search' => $name];
        if ($teamId) {
            $params['team'] = $teamId; // Buscar jugador dentro de un equipo concreto
        }
        // OJO: La búsqueda de jugadores requiere al menos la liga o el equipo obligatoriamente en la versión gratuita a veces,
        // pero probaremos la búsqueda global. Si falla, limitaremos por liga.
        return $this->getCachedData('players', $params, 86400);
    }

    // Para la barra de búsqueda: "Buscar liga..."
    public function searchLeague(string $name): array
    {
        return $this->getCachedData('leagues', ['search' => $name], 2592000);
    }

    // ========================================
    // UTILIDADES
    // ========================================

    public function getCountries(): array
    {
        return $this->getCachedData('countries', [], 2592000); // Rara vez cambian
    }

    public function getSeasons(): array
    {
        // Devuelve los años de temporadas disponibles
        return $this->getCachedData('leagues/seasons', [], 2592000);
    }
}
