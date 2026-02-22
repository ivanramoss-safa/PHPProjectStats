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
            $players = $dataResponse;
        } else {

            $topRatedRefs = $reviewRepo->findTopRated('player', 8);
            $existingIds = [];

            foreach ($topRatedRefs as $ref) {

                $pData = $api->getPlayerStatistics((int)$ref['externalId'], 2023); 
                if (!empty($pData['response'])) {
                    $playerItem = $pData['response'][0];
                    $playerItem['average_rating'] = $ref['avg_rating'];
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
        if (!$season) {
            $season = $seasons[0] ?? 2024;
        }


        $playerInfo = null; 

        if (!empty($seasons)) {
             $latest = $seasons[0];

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
                     $season = $seasons[0] ?? 2024;
                     $playerInfo = null; 
                 }
             }
        }

        if (!$playerInfo) {
             $playerData = $api->getPlayerStatistics($id, $season);
             $playerInfo = $playerData['response'][0] ?? null;
        }

        if (!$playerInfo && !empty($seasons)) {
            foreach ($seasons as $s) {
                if ($s == $season) continue; 
                
                $fallbackData = $api->getPlayerStatistics($id, $s);

                if (!empty($fallbackData['errors']['plan'])) {
                    continue; 
                }

                if (!empty($fallbackData['response'])) {
                    $playerInfo = $fallbackData['response'][0];
                    $playerInfo['statistics'] = []; 
                    break; 
                }
            }
        }

        if (!$playerInfo) {
             $lastDitchSeasons = [2024, 2023, 2022, 2021];
             foreach ($lastDitchSeasons as $year) {
                 if (in_array($year, $seasons)) continue; 
                 
                 $lastDitch = $api->getPlayerStatistics($id, $year);
                 if (!empty($lastDitch['response'])) {
                     $playerInfo = $lastDitch['response'][0];
                     $playerInfo['statistics'] = [];
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



        $normalize = fn(string $name) => strtolower(preg_replace('/[\s\-]+/', '', $name));
        
        $dedupedTransfers = [];
        foreach ($transfers as $transfer) {
            $inName = $normalize($transfer['teams']['in']['name'] ?? '');
            $outName = $normalize($transfer['teams']['out']['name'] ?? '');
            $type = $transfer['type'] ?? '';
            $date = strtotime($transfer['date'] ?? '1970-01-01');
            
            $isDuplicate = false;
            foreach ($dedupedTransfers as &$existing) {
                $eInName = $normalize($existing['teams']['in']['name'] ?? '');
                $eOutName = $normalize($existing['teams']['out']['name'] ?? '');
                $eType = $existing['type'] ?? '';
                $eDate = strtotime($existing['date'] ?? '1970-01-01');
                
                if ($inName === $eInName && $outName === $eOutName && $type === $eType) {
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
                $dedupedTransfers[] = $transfer;
            }
        }
        $transfers = $dedupedTransfers;

        usort($transfers, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        $currentTeam = null;
        if (!empty($transfers)) {
            $latestTransfer = $transfers[0]; 

            $currentTeam = $latestTransfer['teams']['in'];
        }

        if (!$currentTeam && $playerInfo) {
            $currentTeam = $playerInfo['statistics'][0]['team'];
        }

        $reviews = $reviewRepo->findBy(
            ['type' => 'player', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );
        
        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);

        $relatedNews = $newsRepo->findByRelatedEntity('player', $id);

        return $this->render('player/show.html.twig', [
            'player' => $playerInfo,
            'currentTeam' => $currentTeam,
            'trophies' => $trophies,
            'transfers' => $transfers,
            'season' => $season,
            'seasons' => $seasons,
            'reviews' => $reviews,
            'reviewForm' => $reviewForm->createView(),
            'relatedNews' => $relatedNews,
        ]);
    }
}

