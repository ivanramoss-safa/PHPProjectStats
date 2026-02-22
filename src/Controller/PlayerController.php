<?php

namespace App\Controller;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class PlayerController extends AbstractController
{
    #[Route('/players', name: 'player_index', methods: ['GET'])]
    public function index(Request $request, FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo): Response
    {
        $search = $request->query->get('q');
        $players = [];
        $topRated = [];

        if ($search) {
            $dataResponse = $api->searchWithVariations('players', $search);

            foreach ($dataResponse as &$pl) {
                $plId = $pl['player']['id'] ?? 0;
                if ($plId > 0) {
                    $pl['currentTeam'] = $api->getPlayerCurrentTeamName($plId, $pl['statistics'] ?? []);
                }
            }
            unset($pl);
            $players = $dataResponse;
        } else {

            $topRatedRefs = $reviewRepo->findTopRated('player', 8);
            $existingIds = [];

            foreach ($topRatedRefs as $ref) {

                $pData = $api->getPlayerStatistics((int)$ref['externalId'], 2023); 
                if (!empty($pData['response'])) {
                    $playerItem = $pData['response'][0];
                    $playerItem['average_rating'] = $ref['avg_rating'];
                    $playerItem['currentTeam'] = $api->getPlayerCurrentTeamName(
                        (int)$ref['externalId'], $playerItem['statistics'] ?? []
                    );
                    $topRated[] = $playerItem;
                    $existingIds[] = $playerItem['player']['id'];
                }
            }

            if (count($topRated) < 8) {
                $defaultIds = [154, 874, 278, 1100, 2295, 738, 652, 30421]; 
                
                foreach ($defaultIds as $defId) {
                    if (count($topRated) >= 8) break;
                    if (!in_array($defId, $existingIds)) {
                        $pData = $api->getPlayerStatistics($defId, 2023);
                        if (!empty($pData['response'])) {
                            $item = $pData['response'][0];
                            $item['average_rating'] = 0; 
                            $item['currentTeam'] = $api->getPlayerCurrentTeamName(
                                $defId, $item['statistics'] ?? []
                            );
                            $topRated[] = $item;
                            $existingIds[] = $defId;
                        }
                    }
                }
            }
        }

        return $this->render('player/index.html.twig', [
            'players' => $players,
            'topRated' => $topRated,
            'searchQuery' => $search,
        ]);
    }

    #[Route('/player/{id}', name: 'player_show')]
    public function show(int $id, Request $request, FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo, \App\Repository\NewsRepository $newsRepo): Response
    {

        $seasons = $api->getPlayerSeasons($id);

        $seasons = $api->filterSeasonsByPlan($seasons);
        
        rsort($seasons);

        if (empty($seasons)) {
            $seasons = [2024, 2023]; 
        }

        $season = $request->query->getInt('season', 0);

        if (!in_array(2025, $seasons)) {
            array_unshift($seasons, 2025);
        }

        if (!$season) {
            $season = 2025;
        }


        $playerInfo = null; 

        if (!empty($seasons) && $season != 2025) {
            $latest = $seasons[0];
            if ($latest != 2025) {
                $latestData = $api->getPlayerStatistics($id, $latest);
                $hasStats = false;
                if (!empty($latestData['errors']['plan'])) {
                    $hasStats = false;
                } elseif (!empty($latestData['response'][0]['statistics'])) {
                    $hasStats = true;
                    if ($latest == $season) {
                        $playerInfo = $latestData['response'][0];
                    }
                }
                if (!$hasStats) {
                    array_shift($seasons);
                    if ($season == $latest) {
                        $season = $seasons[0] ?? 2025;
                        $playerInfo = null;
                    }
                }
            }
        }

        if (!$playerInfo) {
             $playerData = $api->getPlayerStatistics($id, $season);
             $playerInfo = $playerData['response'][0] ?? null;
        }

        $isMissingMetadata = function($info) {
             return empty($info['player']) || 
                    empty($info['player']['age']) || 
                    empty($info['player']['height']) || 
                    empty($info['player']['weight']);
        };

        if (!$playerInfo || $isMissingMetadata($playerInfo)) {
             $fallbackSeasonsToTry = array_unique(array_merge($seasons, [2024, 2023, 2022, 2021]));

             $originalStats = $playerInfo['statistics'] ?? [];
             
             foreach ($fallbackSeasonsToTry as $s) {
                 if ($s == $season && !$playerInfo) continue; 
                 if ($s == $season && $playerInfo) continue; 
                 
                 $fallbackData = $api->getPlayerStatistics($id, $s);
                 
                 if (!empty($fallbackData['errors']['plan'])) continue;

                 if (!empty($fallbackData['response'][0]) && !$isMissingMetadata($fallbackData['response'][0])) {

                     $validProfile = $fallbackData['response'][0];

                     if ($playerInfo) {

                         foreach ($validProfile['player'] as $key => $val) {
                             if (empty($playerInfo['player'][$key])) {
                                 $playerInfo['player'][$key] = $val;
                             }
                         }
                     } else {

                         $playerInfo = $validProfile;
                         $playerInfo['statistics'] = $originalStats; 
                     }
                     break; 
                 }
             }
        }

        if (!$playerInfo) {
             return $this->render('player/not_found.html.twig', [], new Response('', 404));
        }

        $trophiesData = $api->getPlayerTrophies($id);
        $trophies = $trophiesData['response'] ?? [];

        $transfersData = $api->getPlayerTransfers($id);
        $transfers = $transfersData['response'][0]['transfers'] ?? [];


        usort($transfers, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });



        $dedupedTransfers = [];
        foreach ($transfers as $transfer) {
            $type = $transfer['type'] ?? '';
            $date = strtotime($transfer['date'] ?? '1970-01-01');
            
            $isDuplicate = false;
            foreach ($dedupedTransfers as &$existing) {
                $eType = $existing['type'] ?? '';
                $eDate = strtotime($existing['date'] ?? '1970-01-01');

                if (abs($date - $eDate) <= 30 * 86400) {
                    $isDuplicate = true;
                    if ($date < $eDate) {
                        $existing['date'] = $transfer['date'];
                    }
                    break;
                }
            }
            unset($existing);
            
            if (!$isDuplicate) {
                $dedupedTransfers[] = $transfer;
            }
        }
        $transfers = $dedupedTransfers;

        usort($transfers, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));


        $currentTeam = null;
        if (!empty($transfers)) {
            $latestTransfer = $transfers[0];
            $teamIn = $latestTransfer['teams']['in'] ?? null;
            if ($teamIn && !empty($teamIn['id'])) {
                $currentTeam = $teamIn;
            }
        }

        if (!$currentTeam) {
            $currentTeam = $api->getPrimaryTeamFromStatistics($playerInfo['statistics'] ?? []);
        }



        try {
            $squadData = $api->getCachedData('players/squads', ['player' => $id], 86400);
            if (!empty($squadData['response'])) {


                foreach ($squadData['response'] as $squadEntry) {
                    $squadTeam = $squadEntry['team'] ?? null;
                    if ($squadTeam && !empty($squadTeam['id'])) {
                        $currentTeam = $squadTeam;
                        break; 
                    }
                }
            }
        } catch (\Exception $e) {

        }

        if ($currentTeam && !empty($currentTeam['name']) && !empty($transfers)) {
            $lastTransferInName = $transfers[0]['teams']['in']['name'] ?? '';
            if ($lastTransferInName && $lastTransferInName !== $currentTeam['name']) {
                $lastTransferInTeam = $transfers[0]['teams']['in'];

                array_unshift($transfers, [
                    'date' => '',
                    'type' => 'N/A',
                    'teams' => [
                        'out' => $lastTransferInTeam,
                        'in' => ['id' => null, 'name' => 'Libre', 'logo' => null],
                    ]
                ]);

                array_unshift($transfers, [
                    'date' => '',
                    'type' => 'Fichaje Libre',
                    'teams' => [
                        'out' => ['id' => null, 'name' => 'Libre', 'logo' => null],
                        'in' => $currentTeam,
                    ]
                ]);
            }
        }

        $reviews = $reviewRepo->findBy(
            ['type' => 'player', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );
        $averageRating = $reviewRepo->getAverageRating('player', (string)$id);
        
        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);

        file_put_contents('debug_team.txt', print_r($currentTeam, true));
        $relatedNews = $newsRepo->findByRelatedEntity('player', $id);

        $injury = null;
        if ($currentTeam && !empty($currentTeam['id'])) {
            $today = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $datesToFetch = [$tomorrow, $today, $yesterday];
            
            foreach ($datesToFetch as $d) {

                $injuryRaw = $api->getCumulativeInjuries(['team' => $currentTeam['id'], 'date' => $d]);
                if (!empty($injuryRaw['response'])) {

                    foreach ($injuryRaw['response'] as $injItem) {
                        if (($injItem['player']['id'] ?? 0) == $id) {
                            $injury = $injItem['player'];
                            break 2; 
                        }
                    }
                }
            }
        }

        $besoccer2025Stats = null;
        if ($season == 2025) {
            $playerNameForLookup = $playerInfo['player']['name'] ?? '';
            if ($playerNameForLookup) {
                $besoccer2025Stats = $api->getBeSoccerPlayerStats($playerNameForLookup);
            }
        }

        return $this->render('player/show.html.twig', [
            'player' => $playerInfo,
            'currentTeam' => $currentTeam,
            'trophies' => $trophies,
            'transfers' => $transfers,
            'season' => $season,
            'seasons' => $seasons,
            'reviews' => $reviews,
            'averageRating' => $averageRating,
            'reviewForm' => $reviewForm->createView(),
            'relatedNews' => $relatedNews,
            'injury' => $injury,
            'besoccer2025Stats' => $besoccer2025Stats,
        ]);
    }
}







