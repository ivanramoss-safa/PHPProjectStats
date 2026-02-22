<?php
namespace App\Controller;
use App\Service\api\FootballApiService;
use App\Repository\NewsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
class LeagueController extends AbstractController
{
    #[Route('/leagues', name: 'leagues_index', methods: ['GET'])]
    public function index(Request $request, FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo): Response
    {
        $search = $request->query->get('q');
        $leagues = [];
        $topRated = [];
        if ($search) {
            $dataResponse = $api->searchWithVariations('leagues', $search);
            $leagues = [];
            foreach ($dataResponse as $item) {
                $leagues[] = $item;
            }
        } else {
            $topRatedRefs = $reviewRepo->findTopRated('league', 8);
            $existingIds = [];
            foreach ($topRatedRefs as $ref) {
                $lData = $api->getLeagueById((int)$ref['externalId']);
                if (!empty($lData['response'])) {
                    $leagueItem = $lData['response'][0];
                    $leagueItem['average_rating'] = $ref['avg_rating'];
                    $topRated[] = $leagueItem;
                    $existingIds[] = $leagueItem['league']['id'];
                }
            }
            if (count($topRated) < 8) {
                $defaultIds = [140, 39, 135, 78, 61, 2, 3, 15];
                foreach ($defaultIds as $defId) {
                    if (count($topRated) >= 8) break;
                    if (!in_array($defId, $existingIds)) {
                        $lData = $api->getLeagueById($defId);
                        if (!empty($lData['response'])) {
                            $item = $lData['response'][0];
                            $item['average_rating'] = 0;
                            $topRated[] = $item;
                            $existingIds[] = $defId;
                        }
                    }
                }
            }
        }
        return $this->render('league/index.html.twig', [
            'leagues' => $leagues,
            'topRated' => $topRated,
            'searchQuery' => $search,
        ]);
    }
    #[Route('/api/leagues/search', name: 'api_leagues_search', methods: ['GET'])]
    public function search(Request $request, FootballApiService $api): Response
    {
        $query = $request->query->get('q', '');
        if (strlen($query) < 2) {
            return $this->json(['results' => []]);
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
            $data = $api->getCachedData('leagues', ['search' => $term], 3600);
            foreach ($data['response'] ?? [] as $item) {
                if (!isset($results[$item['league']['id']])) {
                    $results[$item['league']['id']] = [
                        'id' => $item['league']['id'],
                        'name' => $item['league']['name'],
                        'logo' => $item['league']['logo'] ?? null,
                        'country' => $item['country']['name'] ?? '',
                        'flag' => $item['country']['flag'] ?? null,
                    ];
                }
            }
        }
        return $this->json(['results' => array_values($results)]);
    }
    #[Route('/league/{id}', name: 'league_show')]
    public function show(int $id, Request $request, FootballApiService $api, NewsRepository $newsRepo, \App\Repository\ReviewRepository $reviewRepo): Response
    {
        $leagueData = $api->getLeagueById($id);
        $leagueInfo = $leagueData['response'][0]['league'] ?? null;
        $countryInfo = $leagueData['response'][0]['country'] ?? null;
        $seasonsAvailable = $leagueData['response'][0]['seasons'] ?? [];
        $seasonsAvailable = array_filter($seasonsAvailable, function($s) {
            return ($s['coverage']['standings'] ?? false)
                || ($s['coverage']['fixtures']['events'] ?? false)
                || ($s['coverage']['fixtures']['lineups'] ?? false)
                || ($s['coverage']['fixtures']['statistics_fixtures'] ?? false)
                || ($s['coverage']['players'] ?? false);
        });
        $seasonsAvailable = array_values($seasonsAvailable);
        $seasonsAvailable = $api->filterSeasonsByPlan($seasonsAvailable);
        if (!$leagueInfo) {
            throw $this->createNotFoundException('Liga no encontrada');
        }
        $requestedSeason = $request->query->get('season');
        $isLive = ($requestedSeason === 'live' || $requestedSeason === null || $requestedSeason === '');
        $season = 2025;
        if (!$isLive) {
            $season = (int)$requestedSeason;
            if ($season < 2022) $season = 2025;
        }
        $fixtureDate = $request->query->get('fixture_date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fixtureDate)) {
            $fixtureDate = date('Y-m-d');
        }
        $prevDate = date('Y-m-d', strtotime($fixtureDate . ' -1 day'));
        $nextDate = date('Y-m-d', strtotime($fixtureDate . ' +1 day'));
        $standingsData = $api->getStandings($id, $season);
        if (!empty($standingsData['errors']['plan'])) {
             $standings = [];
        } else {
            $standings = $standingsData['response'][0]['league']['standings'][0] ?? [];
        }
        $topScorers = $api->getTopScorers($id, $season);
        $topAssists = $api->getTopAssists($id, $season);
        $topYellow        = $api->getTopYellowCards($id, $season);
        $topRed           = $api->getTopRedCards($id, $season);

        $topMinutesGoal   = $api->getBeSoccerRanking($id, $season, 'topMinutesGoal');
        $topPenaltyGoals  = $api->getBeSoccerRanking($id, $season, 'topPenaltyGoals');
        $topMissedPenalty = $api->getBeSoccerRanking($id, $season, 'topMissedPenalty');
        $topGoalkeeper    = $api->getBeSoccerRanking($id, $season, 'topGoalkeeper');
        $topSavedPenalty  = $api->getBeSoccerRanking($id, $season, 'topSavedPenalty');
        $topPlayed        = $api->getBeSoccerRanking($id, $season, 'topPlayed');
        if ($isLive) {
            $allDateFixtures = $api->getFixturesByDate($fixtureDate);
            $fixtures = array_values(array_filter(
                $allDateFixtures['response'] ?? [],
                fn($m) => ($m['league']['id'] ?? 0) == $id
            ));
        } else {
            $fixturesData = $api->getFixturesByLeague($id, $season);
            if (!empty($fixturesData['errors']['plan'])) {
                $fixtures = [];
            } else {
                $fixtures = $fixturesData['response'] ?? [];
            }
        }
        $leagueNews = $newsRepo->findByRelatedEntity('league', $id);
        $reviews = $reviewRepo->findBy(
            ['type' => 'league', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );
        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);
        $averageRating = $reviewRepo->getAverageRating('league', (string)$id);

        $allTransfers = $api->getTransfersByLeagueAll($id, $season);
        $transfersPerPage = 10;
        $transfersPage = max(1, $request->query->getInt('transfers_page', 1));
        $transfersTotalPages = (int)ceil(count($allTransfers) / $transfersPerPage);
        $transfers = array_slice($allTransfers, ($transfersPage - 1) * $transfersPerPage, $transfersPerPage);

        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $datesToFetch = [$tomorrow, $today, $yesterday];
        $allLeagueInjuries = [];
        foreach ($datesToFetch as $d) {
            $resp = $api->getCumulativeInjuries(['league' => $id, 'date' => $d]);
            $allLeagueInjuries = array_merge($allLeagueInjuries, $resp['response'] ?? []);
        }
        
        $seenPlayers = [];
        $injuries = [];
        foreach ($allLeagueInjuries as $inj) {
            $pid = $inj['player']['id'] ?? 0;
            if (!isset($seenPlayers[$pid])) {
                $seenPlayers[$pid] = true;
                $injuries[] = $inj;
            }
        }
        usort($injuries, fn($a, $b) => strtotime($b['fixture']['date'] ?? '0') - strtotime($a['fixture']['date'] ?? '0'));
        $injuriesByTeam = [];
        foreach ($injuries as $inj) {
            $teamName = $inj['team']['name'] ?? 'Unknown';
            $injuriesByTeam[$teamName][] = $inj;
        }

        $enrichedScorers = $topScorers['response'] ?? [];
        foreach ($enrichedScorers as &$scorer) {
            $sId = $scorer['player']['id'] ?? 0;
            if ($sId > 0) {
                $scorer['currentTeam'] = $api->getPlayerCurrentTeamName($sId, $scorer['statistics'] ?? []);
            }
        }
        unset($scorer);
        
        $enrichedAssists = $topAssists['response'] ?? [];
        foreach ($enrichedAssists as &$assister) {
            $aId = $assister['player']['id'] ?? 0;
            if ($aId > 0) {
                $assister['currentTeam'] = $api->getPlayerCurrentTeamName($aId, $assister['statistics'] ?? []);
            }
        }
        unset($assister);

        $enrichedYellow = $topYellow['response'] ?? [];
        $enrichedRed    = $topRed['response'] ?? [];



        return $this->render('league/show.html.twig', [

            'league'           => $leagueInfo,
            'country'          => $countryInfo,
            'seasons'          => $seasonsAvailable,
            'currentSeason'    => $season,
            'isLive'           => $isLive,
            'fixtureDate'      => $fixtureDate,
            'prevDate'         => $prevDate,
            'nextDate'         => $nextDate,
            'standings'        => $standings,
            'topScorers'       => $enrichedScorers,
            'topAssists'       => $enrichedAssists,
            'topYellow'        => $enrichedYellow,
            'topRed'           => $enrichedRed,
            'topMinutesGoal'   => $topMinutesGoal['response'] ?? [],
            'topPenaltyGoals'  => $topPenaltyGoals['response'] ?? [],
            'topMissedPenalty' => $topMissedPenalty['response'] ?? [],
            'topGoalkeeper'    => $topGoalkeeper['response'] ?? [],
            'topSavedPenalty'  => $topSavedPenalty['response'] ?? [],
            'topPlayed'        => $topPlayed['response'] ?? [],
            'fixtures'         => $fixtures,
            'transfers'        => $transfers,
            'transfersPage'    => $transfersPage,
            'transfersTotalPages' => $transfersTotalPages,
            'injuriesByTeam'   => $injuriesByTeam,
            'news'             => $leagueNews,
            'reviews'          => $reviews,
            'averageRating'    => $averageRating,
            'reviewForm'       => $reviewForm->createView(),
        ]);
    }
}


