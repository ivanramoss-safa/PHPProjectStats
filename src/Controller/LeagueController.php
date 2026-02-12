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


        $requestedSeason = $request->query->getInt('season');
        $season = $requestedSeason;
        
        if (!$season) {
            foreach ($seasonsAvailable as $s) {
                if (($s['current'] ?? false) === true) {
                    $season = $s['year'];
                    break;
                }
            }
            if (!$season && !empty($seasonsAvailable)) {

                $last = end($seasonsAvailable);
                $season = $last['year'];
            }
            
            if (!$season) {
                $season = 2024; 
            }

            $checkStandings = $api->getStandings($id, $season);
            if (empty($checkStandings['response'][0]['league']['standings'][0])) {
                $season = $season - 1;
            }
        }

        $standingsData = $api->getStandings($id, $season);

        if (!empty($standingsData['errors']['plan'])) {






             $standings = [];





        } else {
            $standings = $standingsData['response'][0]['league']['standings'][0] ?? [];
        }

        $topScorers = $api->getTopScorers($id, $season);
        $topAssists = $api->getTopAssists($id, $season);
        $topYellow = $api->getTopYellowCards($id, $season);
        $topRed = $api->getTopRedCards($id, $season);

        $fixturesData = $api->getFixturesByLeague($id, $season);
        if (!empty($fixturesData['errors']['plan'])) {
            $fixtures = [];
        } else {
            $fixtures = $fixturesData['response'] ?? [];
        }

        $leagueNews = $newsRepo->findByRelatedEntity('league', $id);


        $reviews = $reviewRepo->findBy(
            ['type' => 'league', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );

        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);
        $averageRating = $reviewRepo->getAverageRating('league', (string)$id);
        return $this->render('league/show.html.twig', [
            'league' => $leagueInfo,
            'country' => $countryInfo,
            'seasons' => $seasonsAvailable,
            'currentSeason' => $season,
            'standings' => $standings,
            'topScorers' => $topScorers['response'] ?? [],
            'topAssists' => $topAssists['response'] ?? [],
            'topYellow' => $topYellow['response'] ?? [],
            'topRed' => $topRed['response'] ?? [],
            'fixtures' => $fixtures,
            'news' => $leagueNews,
            'reviews' => $reviews,
            'averageRating' => $averageRating,
            'reviewForm' => $reviewForm->createView(),
        ]);
    }
}