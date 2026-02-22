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

        $fallbackDir = dirname(__DIR__, 3) . '/var/api_fallback_cache';
        if (!is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0777, true);
        }
        $fallbackFile = $fallbackDir . '/' . $cacheKey . '.json';

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $params, $cacheSeconds, $fallbackFile) {

            try {
                $response = $this->client->request('GET', 'https://v3.football.api-sports.io/' . $endpoint, [
                    'headers' => [
                        'x-apisports-key' => $this->apiKey,
                    ],
                    'query' => $params,
                    'timeout' => 8,
                ]);

                $data = $response->toArray(false); 

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
                        @file_put_contents($projectDir . '/var/api_usage.json', json_encode($usageData));
                    }
                }
                catch (\Exception $e) {

                }

                if (empty($data['response']) && file_exists($fallbackFile)) {

                    $data = json_decode(file_get_contents($fallbackFile), true);
                    $item->expiresAfter(3600); 
                }
                elseif (!empty($data['response'])) {

                    @file_put_contents($fallbackFile, json_encode($data));
                    $item->expiresAfter($cacheSeconds);
                }
                elseif (!empty($data['errors'])) {

                    $item->expiresAfter(5);
                }
                else {

                    $item->expiresAfter($cacheSeconds);
                }

                return $data;

            }
            catch (\Exception $e) {

                if (file_exists($fallbackFile)) {
                    $item->expiresAfter(3600); 
                    return json_decode(file_get_contents($fallbackFile), true);
                }

                $item->expiresAfter(5);
                return ['response' => [], 'errors' => ['msg' => $e->getMessage()]];
            }
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

    public function getFixturePredictions(int $fixtureId): array
    {
        return $this->getCachedData('predictions', ['fixture' => $fixtureId], 86400);
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
        if ($season == 2025) {
            $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $leagueId . '.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (isset($data['standings']) && !empty($data['standings'][0])) {
                    $map = [
                        'FC Barcelona' => 529, 'Real Madrid' => 541, 'Villarreal' => 548,
                        'Atlético de Madrid' => 530, 'Real Betis' => 543, 'Celta' => 538,
                        'Espanyol' => 540, 'Athletic' => 431, 'Osasuna' => 727,
                        'Real Sociedad' => 547, 'Sevilla' => 536, 'Getafe' => 546,
                        'Girona' => 542, 'Rayo Vallecano' => 728, 'Alavés' => 263,
                        'Valencia' => 532, 'Mallorca' => 794, 'Valladolid' => 720,
                        'Leganés' => 745, 'Las Palmas' => 533
                    ];
                    foreach ($data['standings'][0] as &$teamInfo) {
                        $nm = $teamInfo['team']['name'] ?? '';
                        foreach ($map as $key => $val) {
                            if (stripos($nm, $key) !== false) {
                                $teamInfo['team']['id'] = $val;
                                break;
                            }
                        }
                    }
                    return [
                        'response' => [
                            [
                                'league' => [
                                    'id' => $leagueId,
                                    'name' => 'Available Division',
                                    'season' => 2025,
                                    'standings' => $data['standings']
                                ]
                            ]
                        ]
                    ];
                }
            }
        }
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
        if ($season == 2025) {
            $fallback = $this->getCachedData('players', ['id' => $playerId, 'season' => 2024], 86400);
            $scrapedLeagueId = null;
            $realTeam = null;
            foreach ($fallback['response'][0]['statistics'] ?? [] as $stat) {
                $lId = $stat['league']['id'] ?? 0;
                $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $lId . '.json';
                if (file_exists($cacheFile)) {
                    $scrapedLeagueId = $lId;
                    $realTeam = $stat['team'] ?? null;
                    break;
                }
            }

            if ($scrapedLeagueId) {
                $pname = $fallback['response'][0]['player']['name'] ?? '';
                $goals = 0;
                $assists = 0;

                $teamId = $realTeam['id'] ?? null;
                $teamName = $realTeam['name'] ?? 'Team';
                $teamLogo = $realTeam['logo'] ?? '';

                $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $scrapedLeagueId . '.json';
                if ($pname && file_exists($cacheFile)) {
                    $bsData = json_decode(file_get_contents($cacheFile), true);
                    foreach (['topScorers', 'topAssists'] as $type) {
                        foreach ($bsData[$type] ?? [] as $ts) {
                            $n = $ts['player']['name'] ?? '';
                            if (stripos($n, $pname) !== false || stripos($pname, $n) !== false) {
                                if ($type === 'topScorers')
                                    $goals = $ts['statistics'][0]['goals']['total'] ?? 0;
                                if ($type === 'topAssists')
                                    $assists = $ts['statistics'][0]['goals']['assists'] ?? 0;
                            }
                        }
                    }
                }
                return ['response' => [[
                            'player' => [
                                'id' => $playerId,
                                'name' => $pname ?: 'Player',
                                'firstname' => '',
                                'lastname' => '',
                                'age' => null,
                                'nationality' => '',
                                'height' => '',
                                'weight' => '',
                                'injured' => false,
                                'photo' => $fallback['response'][0]['player']['photo'] ?? ''
                            ],
                            'statistics' => [[
                                    'team' => ['id' => $teamId, 'name' => $teamName, 'logo' => $teamLogo],
                                    'league' => ['id' => $scrapedLeagueId, 'name' => 'Available Division', 'logo' => ''],
                                    'goals' => ['total' => $goals, 'assists' => $assists],
                                    'games' => ['appearences' => null, 'minutes' => null],
                                    'passes' => ['total' => null, 'key' => null, 'accuracy' => null],
                                    'tackles' => ['total' => null, 'blocks' => null, 'interceptions' => null],
                                    'duels' => ['total' => null, 'won' => null],
                                    'dribbles' => ['attempts' => null, 'success' => null, 'past' => null],
                                    'fouls' => ['drawn' => null, 'committed' => null],
                                    'cards' => ['yellow' => 0, 'yellowred' => 0, 'red' => 0],
                                    'penalty' => ['won' => null, 'commited' => null, 'scored' => null, 'missed' => null, 'saved' => null]
                                ]]
                        ]]];
            }
        }
        return $this->getCachedData('players', ['id' => $playerId, 'season' => $season], 86400);
    }

    public function getTopScorers(int $leagueId, int $season): array
    {
        if ($season == 2025) {
            $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $leagueId . '.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (isset($data['topScorers']))
                    return $data['topScorers'];
            }
        }
        return $this->getCachedData('players/topscorers', ['league' => $leagueId, 'season' => $season], 3600);
    }

    public function getTopAssists(int $leagueId, int $season): array
    {
        if ($season == 2025) {
            $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $leagueId . '.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (isset($data['topAssists']))
                    return $data['topAssists'];
            }
        }
        return $this->getCachedData('players/topassists', ['league' => $leagueId, 'season' => $season], 3600);
    }

    public function getTopYellowCards(int $leagueId, int $season): array
    {
        if ($season == 2025) {
            $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $leagueId . '.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (isset($data['topYellow']))
                    return $data['topYellow'];
            }
        }
        return $this->getCachedData('players/topyellowcards', ['league' => $leagueId, 'season' => $season], 3600);
    }

    public function getTopRedCards(int $leagueId, int $season): array
    {
        if ($season == 2025) {
            $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $leagueId . '.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (isset($data['topRed']))
                    return $data['topRed'];
            }
        }
        return $this->getCachedData('players/topredcards', ['league' => $leagueId, 'season' => $season], 3600);
    }

    
    public function getBeSoccerRanking(int $leagueId, int $season, string $key): array
    {
        if ($season == 2025) {
            $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_standings_cache_' . $leagueId . '.json';
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (isset($data[$key]))
                    return $data[$key];
            }
        }
        return ['response' => []];
    }

    
    public function getBeSoccerPlayerStats(string $playerName): ?array
    {
        $cacheFile = dirname(__DIR__, 3) . '/data/besoccer_player_cache.json';
        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        if (!$data)
            return null;

        if (isset($data[$playerName])) {
            return $data[$playerName];
        }

        $normalize = function (string $s): string {
            $s = mb_strtolower($s);
            return str_replace(
            ['a\'', 'e\'', 'i\'', 'o\'', 'u\'', 'u"', 'n~', 'a`', 'a^', 'a"', 'e`', 'e^', 'e"', 'i"', 'o^', 'u`', 'u^'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'a', 'a', 'e', 'e', 'e', 'i', 'o', 'u', 'u'],
            $s
            );
        };

        $normalizedSearch = $normalize($playerName);

        foreach ($data as $cachedName => $stats) {
            if ($normalize($cachedName) === $normalizedSearch) {
                return $stats;
            }
        }

        $searchParts = array_filter(explode(' ', $normalizedSearch), fn($p) => strlen($p) > 1);
        foreach ($data as $cachedName => $stats) {
            $normalizedCached = $normalize($cachedName);
            $allFound = true;
            foreach ($searchParts as $part) {
                if (!str_contains($normalizedCached, $part)) {
                    $allFound = false;
                    break;
                }
            }
            if ($allFound && count($searchParts) > 1) {
                return $stats;
            }
        }

        return null;
    }




    public function getInjuriesByTeam(int $teamId, int $season): array
    {
        return $this->getCachedData('injuries', ['team' => $teamId, 'season' => $season], 3600);
    }

    public function getInjuriesByFixture(int $fixtureId): array
    {
        return $this->getCumulativeInjuries(['fixture' => $fixtureId]);
    }

    public function getCumulativeInjuries(array $params): array
    {

        $freshData = $this->getCachedData('injuries', $params, 3600);
        $freshInjuries = $freshData['response'] ?? [];

        $cacheDir = dirname(__DIR__, 3) . '/data/injuries';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        $cacheFile = $cacheDir . '/cumulative_injuries.json';

        $cumulative = [];
        if (file_exists($cacheFile)) {
            $cumulative = json_decode(file_get_contents($cacheFile), true) ?? [];
        }

        $now = time();
        $changed = false;
        foreach ($freshInjuries as $newInj) {
            $pid = $newInj['player']['id'] ?? 0;
            if ($pid) {

                $newInj['_last_seen'] = $now;
                $cumulative[$pid] = $newInj;
                $changed = true;
            }
        }

        $sevenDaysAgo = $now - (7 * 86400);
        $finalInjuries = [];
        foreach ($cumulative as $pid => $injData) {
            if (($injData['_last_seen'] ?? 0) >= $sevenDaysAgo) {

                $cleanInj = $injData;
                unset($cleanInj['_last_seen']);

                $match = true;
                if (isset($params['team']) && ($cleanInj['team']['id'] ?? 0) != $params['team']) {
                    $match = false;
                }
                if (isset($params['league']) && ($cleanInj['league']['id'] ?? 0) != $params['league']) {
                    $match = false;
                }
                if (isset($params['fixture']) && ($cleanInj['fixture']['id'] ?? 0) != $params['fixture']) {
                    $match = false;
                }
                if (isset($params['player']) && ($cleanInj['player']['id'] ?? 0) != $params['player']) {
                    $match = false;
                }

                if ($match) {
                    $finalInjuries[] = $cleanInj;
                }
            }
            else {
                unset($cumulative[$pid]);
                $changed = true;
            }
        }

        if ($changed) {
            file_put_contents($cacheFile, json_encode($cumulative));
        }

        return ['response' => $finalInjuries];
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

            $oneYearAgo = strtotime('-24 months');

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

        $cacheKey = 'injuries_league_date_v3_' . $leagueId . '_' . date('Y-m-d');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($leagueId, $limit) {
            $item->expiresAfter(3600); 

            $injuriesData = $this->getCumulativeInjuries([
                'league' => $leagueId,
                'date' => date('Y-m-d'),
            ]);

            $allInjuries = $injuriesData['response'] ?? [];

            usort($allInjuries, function ($a, $b) {
                    $dateA = $a['fixture']['date'] ?? '1970-01-01';
                    $dateB = $b['fixture']['date'] ?? '1970-01-01';
                    return strtotime($dateB) - strtotime($dateA);
                }
                );

                return array_slice($allInjuries, 0, $limit);
            });
    }

    
    public function getInjuriesByLeagueDate(int $leagueId, string $date): array
    {
        $data = $this->getCumulativeInjuries(['league' => $leagueId, 'date' => $date]);
        return $data['response'] ?? [];
    }

    
    public function getTransfersByLeagueAll(int $leagueId, int $season): array
    {
        $cacheKey = 'transfers_league_all_v1_' . $leagueId . '_' . $season;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($leagueId, $season) {
            $item->expiresAfter(86400);

            $teamsData = $this->getTeamsByLeague($leagueId, $season);
            $teams = $teamsData['response'] ?? [];
            if (empty($teams)) {
                $teamsData = $this->getTeamsByLeague($leagueId, $season - 1);
                $teams = $teamsData['response'] ?? [];
            }

            $oneYearAgo = strtotime('-12 months');
            $parseDate = function ($d) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
                        return strtotime($d);
                    if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $d, $m)) {
                        $yy = (int)$m[1];
                        if ($yy > (int)date('y'))
                            return false;
                        return strtotime((2000 + $yy) . "-{$m[2]}-{$m[3]}");
                    }
                    return false;
                }
                    ;

                $all = [];
                foreach ($teams as $teamData) {
                    $transfersData = $this->getTeamTransfers($teamData['team']['id']);
                    foreach ($transfersData['response'] ?? [] as $pt) {
                        foreach ($pt['transfers'] ?? [] as $t) {
                            $ts = $parseDate($t['date'] ?? '');
                            if ($ts && $ts > $oneYearAgo) {
                                $all[] = [
                                    'player' => $pt['player'] ?? [],
                                    'transfer' => $t,
                                    'date' => $t['date'],
                                    'timestamp' => $ts,
                                ];
                            }
                        }
                    }
                }

                usort($all, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));

                $seenKeys = [];
                $deduped = [];
                foreach ($all as $tr) {
                    $pid = $tr['player']['id'] ?? 0;
                    $inId = $tr['transfer']['teams']['in']['id'] ?? 0;
                    $outId = $tr['transfer']['teams']['out']['id'] ?? 0;
                    $type = $tr['transfer']['type'] ?? '';
                    $ts = $tr['timestamp'];

                    $dup = false;
                    foreach ($seenKeys as &$s) {


                        if ($s['pid'] === $pid) {
                            if (abs($ts - $s['ts']) <= 30 * 86400) {
                                $dup = true;
                                if ($ts < $s['ts']) {
                                    $s['ts'] = $ts;
                                    $deduped[$s['i']]['date'] = $tr['date'];
                                }
                                break;
                            }
                        }
                    }
                    unset($s);

                    if (!$dup) {
                        $seenKeys[] = ['pid' => $pid, 'inId' => $inId, 'outId' => $outId, 'type' => $type, 'ts' => $ts, 'i' => count($deduped)];
                        $deduped[] = $tr;
                    }
                }

                return $deduped;
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



    
    public function getPlayerCurrentTeamName(int $playerId, array $statistics = []): string
    {

        $team = $this->getPrimaryTeamFromStatistics($statistics);
        $teamName = $team['name'] ?? 'Sin equipo';

        try {
            $squadData = $this->getCachedData('players/squads', ['player' => $playerId], 86400);
            if (!empty($squadData['response'])) {
                foreach ($squadData['response'] as $squadEntry) {
                    $squadTeam = $squadEntry['team'] ?? null;
                    if ($squadTeam && !empty($squadTeam['name'])) {
                        $teamName = $squadTeam['name'];
                        break;
                    }
                }
            }
        }
        catch (\Exception $e) {

        }

        return $teamName;
    }

    
    public function getPrimaryTeamFromStatistics(array $statistics): ?array
    {
        if (empty($statistics)) {
            return null;
        }

        $bestTeam = null;
        $maxAppearances = -1;

        foreach ($statistics as $stat) {
            $team = $stat['team'] ?? null;
            if (!$team)
                continue;

            $appearances = $stat['games']['appearences'] ?? 0;

            if ($appearances > $maxAppearances) {
                $maxAppearances = $appearances;
                $bestTeam = $team;
            }
        }

        if (!$bestTeam && !empty($statistics[0]['team'])) {
            $bestTeam = $statistics[0]['team'];
        }

        return $bestTeam;
    }
}
