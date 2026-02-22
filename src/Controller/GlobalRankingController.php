<?php

namespace App\Controller;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ranking')]
class GlobalRankingController extends AbstractController
{
    #[Route('/global', name: 'app_ranking_global')]
    public function globalStats(
        \App\Repository\ReviewRepository $reviewRepository,
        \App\Repository\RankingItemRepository $rankingItemRepository,
        FootballApiService $apiService
    ): Response {
        $totalReviews = $reviewRepository->getTotalReviews();
        $averages = $reviewRepository->getAverageRatingByCategory();

        $topPlayersDb = $reviewRepository->findTopRated('player', 5);
        $topPlayers = [];
        foreach ($topPlayersDb as $tp) {
            $data = $apiService->getPlayerStatistics($tp['externalId'], 2024);
            if (!empty($data['response'][0])) {
                $p = $data['response'][0]['player'];
                $topPlayers[] = [
                    'name' => $p['name'],
                    'photo' => $p['photo'],
                    'avg_rating' => $tp['avg_rating'],
                ];
            }
        }

        $topTeamsDb = $reviewRepository->findTopRated('team', 5);
        $topTeams = [];
        foreach ($topTeamsDb as $tt) {
            $data = $apiService->getTeamById($tt['externalId']);
            if (!empty($data['response'][0])) {
                $t = $data['response'][0]['team'];
                $topTeams[] = [
                    'name' => $t['name'],
                    'photo' => $t['logo'],
                    'avg_rating' => $tt['avg_rating'],
                ];
            }
        }

        $topRankedPlayersDb = $rankingItemRepository->getGlobalRankingPoints('player', 5);
        $topRankedPlayers = [];
        foreach ($topRankedPlayersDb as $rp) {
            $data = $apiService->getPlayerStatistics((int)$rp['externalId'], 2024);
            if (!empty($data['response'][0])) {
                $p = $data['response'][0]['player'];
                $topRankedPlayers[] = [
                    'name' => $p['name'],
                    'photo' => $p['photo'],
                    'points' => $rp['total_points'],
                ];
            }
        }

        $topRankedTeamsDb = $rankingItemRepository->getGlobalRankingPoints('team', 5);
        $topRankedTeams = [];
        foreach ($topRankedTeamsDb as $rt) {
            $data = $apiService->getTeamById((int)$rt['externalId']);
            if (!empty($data['response'][0])) {
                $t = $data['response'][0]['team'];
                $topRankedTeams[] = [
                    'name' => $t['name'],
                    'photo' => $t['logo'],
                    'points' => $rt['total_points'],
                ];
            }
        }
        
        return $this->render('ranking/global.html.twig', [
            'totalReviews' => $totalReviews,
            'averages' => $averages,
            'topPlayers' => $topPlayers,
            'topTeams' => $topTeams,
            'topRankedPlayers' => $topRankedPlayers,
            'topRankedTeams' => $topRankedTeams,
        ]);
    }
}
