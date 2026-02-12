<?php

namespace App\Service\api;

use App\Entity\Category;
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

    public function getCachedData(string $endpoint, array $params = [], int $cacheSeconds = 3600): array
    {

        $cacheKey = 'api_' . str_replace('/', '_', $endpoint) . '_' . md5(json_encode($params));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $params, $cacheSeconds) {

            $response = $this->client->request('GET', 'https://v3.football.api-sports.io/' . $endpoint, [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => $params,
                'timeout' => 5, 
            ]);

            $data = $response->toArray();

            try {
                $headers = $response->getHeaders(false);
                $remaining = $headers['x-ratelimit-requests-remaining'][0] ?? null;
                $limit = $headers['x-ratelimit-requests-limit'][0] ?? null;

                if ($remaining !== null && $limit !== null) {
                    $used = $limit - $remaining;
                    $usageData = [
                        'used' => $used,
                        'limit' => $limit,
                        'remaining' => $remaining,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    $projectDir = dirname(__DIR__, 3); 
                    file_put_contents($projectDir . '/var/api_usage.json', json_encode($usageData));
                }
            }
            catch (\Exception $e) {

            }

            if (!empty($data['errors'])) {
                $item->expiresAfter(5); 
            }
            else {
                $item->expiresAfter($cacheSeconds); 
            }

            return $data;
        });
    }



    public function getApiStatus(): array
    {

        try {
            $response = $this->client->request('GET', 'https://v3.football.api-sports.io/status', [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ]
            ]);

            return $response->toArray();
        }
        catch (\Exception $e) {
            return ['errors' => ['msg' => $e->getMessage()]];
        }
    }




    public function getLiveFixtures(): array
    {
        return $this->getCachedData('fixtures', ['live' => 'all'], 60);
    }

    public function getFixturesByDate(string $date): array
    {
        return $this->getCachedData('fixtures', ['date' => $date], 120); 
    }

    public function getFixturesByLeague(int $leagueId, int $season): array
    {
        return $this->getCachedData('fixtures', ['league' => $leagueId, 'season' => $season], 86400);
    }

    public function getFixtureById(int $fixtureId): array
    {
        $data = $this->getCachedData('fixtures', ['id' => $fixtureId], 300);

        if (!empty($data['response'][0]['fixture']['status']['short'])
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




    public function getInjuriesByTeam(int $teamId, int $season): array
    {
        return $this->getCachedData('injuries', ['team' => $teamId, 'season' => $season], 3600);
    }

    public function getInjuriesByFixture(int $fixtureId): array
    {
        return $this->getCachedData('injuries', ['fixture' => $fixtureId], 86400);
    }




    public function getCoachById(int $coachId): array
    {
        return $this->getCachedData('coachs', ['id' => $coachId], 604800);
    }

    public function searchCoach(string $name): array
    {
        return $this->getCachedData('coachs', ['search' => $name], 2592000);
    }




    public function getVenueById(int $venueId): array
    {
        return $this->getCachedData('venues', ['id' => $venueId], 2592000);
    }

    public function searchVenue(string $name): array
    {
        return $this->getCachedData('venues', ['search' => $name], 2592000);
    }

    
    public function searchWithVariations(string $endpoint, string $query, int $cacheSeconds = 3600): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        $variations = [$query];

        $variations[] = str_replace(' ', '', $query);

        $variations[] = preg_replace('/([a-z])([A-Z])/', '$1 $2', $query);

        if (stripos($query, 'laliga') !== false) {
            $variations[] = str_ireplace('laliga', 'La Liga', $query);
        }

        if (stripos($query, 'seriea') !== false) {
            $variations[] = str_ireplace('seriea', 'Serie A', $query);
        }

        $variations = array_unique($variations);
        $results = [];

        foreach ($variations as $term) {
            $data = $this->getCachedData($endpoint, ['search' => $term], $cacheSeconds);

            foreach ($data['response'] ?? [] as $item) {

                $id = null;
                if ($endpoint === 'leagues')
                    $id = $item['league']['id'];
                elseif ($endpoint === 'teams')
                    $id = $item['team']['id'];
                elseif ($endpoint === 'players')
                    $id = $item['player']['id']; 
                elseif ($endpoint === 'venues')
                    $id = $item['id'];
                elseif ($endpoint === 'coachs')
                    $id = $item['id'];

                if ($id && !isset($results[$id])) {
                    $results[$id] = $item;
                }
            }
        }

        return array_values($results);
    }




    public function getPlayerTrophies(int $playerId): array
    {
        return $this->getCachedData('trophies', ['player' => $playerId], 604800);
    }

    public function getCoachTrophies(int $coachId): array
    {
        return $this->getCachedData('trophies', ['coach' => $coachId], 604800);
    }




    public function getPlayerTransfers(int $playerId): array
    {
        return $this->getCachedData('transfers', ['player' => $playerId], 86400);
    }

    public function getTeamTransfers(int $teamId): array
    {
        return $this->getCachedData('transfers', ['team' => $teamId], 86400);
    }

    
    public function getTransfersByLeague(int $leagueId = 140, int $season = 2024, int $limit = 10): array
    {
        $cacheKey = 'transfers_league_v9_' . $leagueId . '_' . $season;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($leagueId, $season, $limit) {
            $item->expiresAfter(86400); 

            $teamsData = $this->getTeamsByLeague($leagueId, $season);
            $teams = $teamsData['response'] ?? [];

            if (empty($teams)) {
                $teamsData = $this->getTeamsByLeague($leagueId, $season - 1);
                $teams = $teamsData['response'] ?? [];
            }

            $allTransfers = [];

            $oneYearAgo = strtotime('-12 months');

            $parseDate = function ($dateStr) {

                    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dateStr)) {
                        return strtotime($dateStr);
                    }

                    if (preg_match('/^(\\d{2})(\\d{2})(\\d{2})$/', $dateStr, $m)) {
                        $yy = (int)$m[1];
                        if ($yy > (int)date('y'))
                            return false;
                        $year = $yy + 2000;
                        return strtotime("$year-{$m[2]}-{$m[3]}");
                    }
                    return false;
                }
                    ;

                foreach ($teams as $teamData) {
                    $teamId = $teamData['team']['id'];
                    $transfersData = $this->getTeamTransfers($teamId);

                    foreach ($transfersData['response'] ?? [] as $playerTransfers) {
                        foreach ($playerTransfers['transfers'] ?? [] as $transfer) {
                            $transferDate = $transfer['date'] ?? '';
                            $transferTimestamp = $parseDate($transferDate);

                            if ($transferTimestamp && $transferTimestamp > $oneYearAgo) {
                                $allTransfers[] = [
                                    'player' => $playerTransfers['player'] ?? [],
                                    'transfer' => $transfer,
                                    'date' => $transferDate,
                                    'timestamp' => $transferTimestamp, 
                                ];
                            }
                        }
                    }
                }

                usort($allTransfers, function ($a, $b) {
                    return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
                }
                );

                $seenPlayers = [];
                $uniqueTransfers = [];
                foreach ($allTransfers as $transfer) {
                    $playerId = $transfer['player']['id'] ?? 0;
                    if (!isset($seenPlayers[$playerId])) {
                        $seenPlayers[$playerId] = true;
                        $uniqueTransfers[] = $transfer;
                    }
                }

                return array_slice($uniqueTransfers, 0, $limit);
            });
    }

    
    public function getInjuriesByLeague(int $leagueId = 140, int $season = 2024, int $limit = 10): array
    {
        $cacheKey = 'injuries_league_v3_' . $leagueId . '_' . $season;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($leagueId, $season, $limit) {
            $item->expiresAfter(86400); 

            $injuriesData = $this->getCachedData('injuries', [
                'league' => $leagueId,
                'season' => $season,
            ], 86400);

            $allInjuries = $injuriesData['response'] ?? [];

            if (empty($allInjuries)) {
                $injuriesData = $this->getCachedData('injuries', [
                    'date' => date('Y-m-d'),
                    'league' => $leagueId,
                ], 3600);
                $allInjuries = $injuriesData['response'] ?? [];
            }

            usort($allInjuries, function ($a, $b) {
                    $dateA = $a['fixture']['date'] ?? '1970-01-01';
                    $dateB = $b['fixture']['date'] ?? '1970-01-01';
                    return strtotime($dateB) - strtotime($dateA);
                }
                );

                return array_slice($allInjuries, 0, $limit);
            });
    }




    public function searchPlayerV2(string $name, ?int $teamId = null): array
    {
        $params = ['search' => $name];
        if ($teamId) {
            $params['team'] = $teamId; 
        }


        return $this->getCachedData('players', $params, 86400);
    }

    
    public function searchPlayers(string $query, int $page = 1): array
    {


        $currentSeason = 2024;

        return $this->getCachedData('players', [
            'search' => $query,
            'season' => $currentSeason,
            'page' => $page
        ], 3600);
    }

    
    public function searchTeams(string $query, int $page = 1): array
    {
        return $this->getCachedData('teams', [
            'search' => $query,
            'page' => $page
        ], 86400);
    }

    public function searchLeagues(string $name): array
    {
        return $this->getCachedData('leagues', ['search' => $name], 2592000);
    }

    
    public function searchVenues(string $query): array
    {
        return $this->getCachedData('venues', [
            'search' => $query
        ], 604800);
    }

    
    public function searchCoaches(string $query): array
    {
        return $this->getCachedData('coachs', [
            'search' => $query
        ], 604800);
    }




    public function getCountries(): array
    {
        return $this->getCachedData('countries', [], 2592000); 
    }

    public function getSeasons(): array
    {

        return $this->getCachedData('leagues/seasons', [], 2592000);
    }

    
    public function getItemsFromCategory(Category $category, string $query = '', int $page = 1): array
    {
        $type = $category->getType();

        $categoryItems = $category->getCategoryItems();

        if ($categoryItems->count() > 0) {

            $externalIds = [];
            foreach ($categoryItems as $item) {
                $externalIds[] = $item->getExternalId();
            }

            switch ($type) {
                case 'player':


                    $currentSeason = 2024;
                    $results = [];
                    foreach ($externalIds as $id) {
                        $playerData = $this->getPlayerStatistics($id, $currentSeason);
                        if (!empty($playerData['response'])) {
                            $results[] = $playerData['response'][0];
                        }
                    }
                    return ['response' => $results, 'paging' => ['current' => 1, 'total' => 1]];

                case 'team':

                    $results = [];
                    foreach ($externalIds as $id) {
                        $teamData = $this->getTeamById($id);
                        if (!empty($teamData['response'])) {
                            $results[] = $teamData['response'][0];
                        }
                    }
                    return ['response' => $results, 'paging' => ['current' => 1, 'total' => 1]];

                case 'league':

                    $results = [];
                    foreach ($externalIds as $id) {
                        $leagueData = $this->getLeagueById($id);
                        if (!empty($leagueData['response'])) {
                            $results[] = $leagueData['response'][0];
                        }
                    }
                    return ['response' => $results, 'paging' => ['current' => 1, 'total' => 1]];

                case 'stadium':

                    $results = [];
                    foreach ($externalIds as $id) {
                        $venueData = $this->getVenueById($id);
                        if (!empty($venueData['response'])) {
                            $results[] = $venueData['response'][0];
                        }
                    }
                    return ['response' => $results, 'paging' => ['current' => 1, 'total' => 1]];

                case 'coach':

                    $results = [];
                    foreach ($externalIds as $id) {
                        $coachData = $this->getCoachById($id);
                        if (!empty($coachData['response'])) {
                            $results[] = $coachData['response'][0];
                        }
                    }
                    return ['response' => $results, 'paging' => ['current' => 1, 'total' => 1]];

                default:
                    return ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];
            }
        }

        switch ($type) {
            case 'player':
                return !empty($query) ? $this->searchPlayers($query, $page) : ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];
            case 'team':
                return !empty($query) ? $this->searchTeams($query, $page) : ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];
            case 'league':
                return !empty($query) ? $this->searchLeagues($query) : ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];
            case 'stadium':
                return !empty($query) ? $this->searchVenues($query) : ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];
            case 'coach':
                return !empty($query) ? $this->searchCoaches($query) : ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];
            default:
                return ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];
        }
    }
    public function getPlayerSeasons(int $playerId): array
    {

        $data = $this->getCachedData('players/seasons', ['player' => $playerId], 86400);
        return $data['response'] ?? [];
    }

    public function getTeamSeasons(int $teamId): array
    {

        $data = $this->getCachedData('teams/seasons', ['team' => $teamId], 86400);
        return $data['response'] ?? [];
    }


    
    public function filterSeasonsByPlan(array $seasons): array
    {

        $minYear = 2022;

        return array_values(array_filter($seasons, function ($year) use ($minYear) {

            $y = is_array($year) ? ($year['year'] ?? 0) : $year;


            if ($y == 2025)
                return false;

            return $y >= $minYear;
        }));
    }

    
    public function getApiUsage(): array
    {
        $projectDir = dirname(__DIR__, 3);
        $file = $projectDir . '/var/api_usage.json';

        if (!file_exists($file)) {
            return ['used' => 0, 'limit' => 100, 'remaining' => 100]; 
        }

        $data = json_decode(file_get_contents($file), true);

        $today = date('Y-m-d');
        $fileDate = isset($data['updated_at']) ? substr($data['updated_at'], 0, 10) : '';

        if ($fileDate !== $today) {
            return ['used' => 0, 'limit' => ($data['limit'] ?? 100), 'remaining' => ($data['limit'] ?? 100)];
        }

        return $data;
    }
}
