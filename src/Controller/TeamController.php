<?php

namespace App\Controller;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TeamController extends AbstractController
{
    #[Route('/teams', name: 'team_index', methods: ['GET'])]
    public function index(Request $request, FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo): Response
    {
        $search = $request->query->get('q');
        $teams = [];
        $topRated = [];

        if ($search) {
            $dataResponse = $api->searchWithVariations('teams', $search);

            $teams = $dataResponse;
        } else {
            $topRatedRefs = $reviewRepo->findTopRated('team', 8);
            $existingIds = [];

            foreach ($topRatedRefs as $ref) {

                $tData = $api->getTeamById((int)$ref['externalId']);
                if (!empty($tData['response'])) {
                    $teamItem = $tData['response'][0];
                    $teamItem['average_rating'] = $ref['avg_rating'];
                    $topRated[] = $teamItem;
                    $existingIds[] = $teamItem['team']['id'];
                }
            }

            if (count($topRated) < 8) {
                $defaultIds = [541, 529, 50, 40, 157, 85, 496, 451]; 
                
                foreach ($defaultIds as $defId) {
                    if (count($topRated) >= 8) break;
                    if (!in_array($defId, $existingIds)) {
                        $tData = $api->getTeamById($defId);
                        if (!empty($tData['response'])) {
                            $item = $tData['response'][0];
                            $item['average_rating'] = 0; 
                            $topRated[] = $item;
                            $existingIds[] = $defId;
                        }
                    }
                }
            }
        }

        return $this->render('team/index.html.twig', [
            'teams' => $teams,
            'topRated' => $topRated,
            'searchQuery' => $search,
        ]);
    }

    #[Route('/team/{id}', name: 'team_show')]
    public function show(int $id, Request $request, FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo, \App\Repository\NewsRepository $newsRepo): Response
    {

        $teamData = $api->getTeamById($id);
        $teamInfo = $teamData['response'][0] ?? null;

        if (!$teamInfo) {
            throw $this->createNotFoundException('Equipo no encontrado.');
        }


        $seasons = $api->getTeamSeasons($id);

        $seasonsAvailable = $api->filterSeasonsByPlan($seasons);

        $requestedSeason = $request->query->getInt('season');
        $season = $requestedSeason;
        
        if (!$season) {

            $season = 2024;
            if (!empty($seasonsAvailable)) {
                $season = max($seasonsAvailable);
            }
        }



        $squadData = $api->getSquad($id);
        $squad = $squadData['response'][0]['players'] ?? [];

        $rawTransfers = $api->getTeamTransfers($id);

        $transfers = $this->processTransfers($rawTransfers['response'] ?? [], $id);

        $fixturesData = $api->getFixturesByLeague($teamInfo['team']['id'], $season); 














        
        $fixturesData = $api->getCachedData('fixtures', ['team' => $id, 'season' => $season], 3600);
        $allFixtures = $fixturesData['response'] ?? [];

        usort($allFixtures, function($a, $b) {
            return strtotime($a['fixture']['date']) - strtotime($b['fixture']['date']);
        });

        $competitions = [];
        foreach ($allFixtures as $f) {
            $c = $f['league'];
            $competitions[$c['id']] = $c['name'];
        }

        $competitionFilter = $request->query->get('competition');
        $fixtures = $allFixtures;
        
        if ($competitionFilter && $competitionFilter !== 'all') {
            $fixtures = array_filter($allFixtures, function($f) use ($competitionFilter) {
                return $f['league']['id'] == $competitionFilter;
            });
        }

        $reviews = $reviewRepo->findBy(
            ['type' => 'team', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );
        $averageRating = $reviewRepo->getAverageRating('team', (string)$id);
        
        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);

        $relatedNews = $newsRepo->findByRelatedEntity('team', $id);

        $activeTab = $request->query->get('tab', 'squad');

        if (!in_array($activeTab, ['squad', 'fixtures', 'transfers', 'news', 'forum'])) {
            $activeTab = 'squad';
        }

        return $this->render('team/show.html.twig', [
            'team' => $teamInfo,
            'squad' => $squad,
            'transfers' => $transfers,
            'fixtures' => $fixtures,
            'seasons' => $seasonsAvailable,
            'currentSeason' => $season,
            'reviews' => $reviews,
            'averageRating' => $averageRating,
            'reviewForm' => $reviewForm->createView(),
            'relatedNews' => $relatedNews,
            'activeTab' => $activeTab,
            'competitions' => $competitions,
            'currentCompetition' => $competitionFilter,
        ]);
    }

    private function processTransfers(array $data, int $teamId): array
    {
        $all = [];
        foreach ($data as $item) {
            $player = $item['player'];
            foreach ($item['transfers'] ?? [] as $t) {

                $isIncoming = ($t['teams']['in']['id'] == $teamId);
                $isOutgoing = ($t['teams']['out']['id'] == $teamId);
                
                if ($isIncoming || $isOutgoing) {
                    $t['player'] = $player;
                    $t['direction'] = $isIncoming ? 'in' : 'out';
                    $all[] = $t;
                }
            }
        }

        usort($all, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));


        $deduped = [];
        foreach ($all as $transfer) {
            $playerId = $transfer['player']['id'] ?? 0;
            $inTeamId = $transfer['teams']['in']['id'] ?? 0;
            $outTeamId = $transfer['teams']['out']['id'] ?? 0;
            $type = $transfer['type'] ?? '';
            $date = strtotime($transfer['date'] ?? '1970-01-01');
            
            $isDuplicate = false;
            foreach ($deduped as &$existing) {
                $ePlayerId = $existing['player']['id'] ?? 0;
                $eInTeamId = $existing['teams']['in']['id'] ?? 0;
                $eOutTeamId = $existing['teams']['out']['id'] ?? 0;
                $eType = $existing['type'] ?? '';
                $eDate = strtotime($existing['date'] ?? '1970-01-01');
                
                if ($playerId === $ePlayerId && $inTeamId === $eInTeamId && $outTeamId === $eOutTeamId && $type === $eType) {

                    if (abs($date - $eDate) <= 4 * 86400) {
                        $isDuplicate = true;

                        if ($date < $eDate) {
                            $existing['date'] = $transfer['date'];
                        }
                        break;
                    }
                }
            }
            unset($existing);
            
            if (!$isDuplicate) {
                $deduped[] = $transfer;
            }
        }
        
        return array_slice($deduped, 0, 50);
    }
}
