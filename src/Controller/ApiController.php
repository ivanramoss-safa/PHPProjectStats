<?php

namespace App\Controller;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/search')]
class ApiController extends AbstractController
{
    #[Route('/players', name: 'api_search_players')]
    public function searchPlayers(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');
        $league = $request->query->getInt('league', 140);
        $team = $request->query->getInt('team', 0);

        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $params = ['search' => $query, 'season' => 2024];
        if ($team > 0) {
            $params['team'] = $team;
        } else {
            $params['league'] = $league;
        }

        $data = $apiService->getCachedData('players', $params, 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $playerId = $item['player']['id'];
            $teamName = $apiService->getPlayerCurrentTeamName($playerId, $item['statistics'] ?? []);

            $results[] = [
                'id' => $playerId,
                'name' => $item['player']['name'],
                'photo' => $item['player']['photo'] ?? null,
                'extra' => $teamName,
            ];
        }

        return $this->json([
            'results' => $results,
            'hasMore' => count($results) >= 20,
        ]);
    }

    #[Route('/teams-by-league', name: 'api_teams_by_league')]
    public function getTeamsByLeague(Request $request, FootballApiService $apiService): Response
    {
        $league = $request->query->getInt('league', 140);
        $data = $apiService->getTeamsByLeague($league, 2024);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['team']['id'],
                'name' => $item['team']['name'],
            ];
        }

        usort($results, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $this->json(['results' => $results]);
    }

    #[Route('/teams', name: 'api_search_teams')]
    public function searchTeams(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');
        if (strlen($query) < 2) {
            return $this->json(['results' => []]);
        }

        $data = $apiService->getCachedData('teams', [
            'search' => $query,
        ], 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['team']['id'],
                'name' => $item['team']['name'],
                'photo' => $item['team']['logo'] ?? null,
                'extra' => $item['team']['country'] ?? '',
            ];
        }

        return $this->json(['results' => $results, 'hasMore' => false]);
    }

    #[Route('/leagues', name: 'api_search_leagues')]
    public function searchLeagues(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $queryNoSpaces = str_replace(' ', '', $query);
        $queryWithSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', $query);

        $data = $apiService->getCachedData('leagues', ['search' => $query], 3600);
        $results = [];

        foreach ($data['response'] ?? [] as $item) {
            $results[$item['league']['id']] = [
                'id' => $item['league']['id'],
                'name' => $item['league']['name'],
                'photo' => $item['league']['logo'] ?? null,
                'extra' => $item['country']['name'] ?? '',
            ];
        }

        if (empty($results) && $queryNoSpaces !== $query) {
            $data2 = $apiService->getCachedData('leagues', ['search' => $queryNoSpaces], 3600);
            foreach ($data2['response'] ?? [] as $item) {
                $results[$item['league']['id']] = [
                    'id' => $item['league']['id'],
                    'name' => $item['league']['name'],
                    'photo' => $item['league']['logo'] ?? null,
                    'extra' => $item['country']['name'] ?? '',
                ];
            }
        }

        if (empty($results) && $queryWithSpaces !== $query) {
            $data3 = $apiService->getCachedData('leagues', ['search' => $queryWithSpaces], 3600);
            foreach ($data3['response'] ?? [] as $item) {
                $results[$item['league']['id']] = [
                    'id' => $item['league']['id'],
                    'name' => $item['league']['name'],
                    'photo' => $item['league']['logo'] ?? null,
                    'extra' => $item['country']['name'] ?? '',
                ];
            }
        }

        return $this->json(['results' => array_values($results), 'hasMore' => false]);
    }

    #[Route('/coaches', name: 'api_search_coaches')]
    public function searchCoaches(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $data = $apiService->getCachedData('coachs', [
            'search' => $query,
        ], 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'photo' => $item['photo'] ?? null,
                'extra' => $item['team']['name'] ?? '',
            ];
        }

        return $this->json(['results' => $results, 'hasMore' => false]);
    }

    #[Route('/venues', name: 'api_search_venues')]
    public function searchVenues(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $data = $apiService->getCachedData('venues', [
            'search' => $query,
        ], 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'photo' => $item['image'] ?? null,
                'extra' => $item['city'] ?? '',
            ];
        }

        return $this->json(['results' => $results, 'hasMore' => false]);
    }
}
