<?php

namespace App\Controller;

use App\Entity\News;
use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/news')]
class NewsController extends AbstractController
{
    #[Route('/{id}/associations', name: 'news_associations', methods: ['GET'])]
    public function getAssociations(News $news, FootballApiService $apiService): JsonResponse
    {
        $associations = [];

        foreach ($news->getPlayerIds() as $id) {
            try {


                $data = $apiService->getPlayerStatistics($id, 2024); 
                if (!empty($data['response'])) {
                    $player = $data['response'][0]['player'];
                    $associations[] = [
                        'type' => 'Jugador',
                        'name' => $player['name'],
                        'photo' => $player['photo'],
                        'url' => $this->generateUrl('player_show', ['id' => $id]),
                        'badgeClass' => 'bg-primary'
                    ];
                }
            } catch (\Throwable $e) {


            }
        }

        foreach ($news->getTeamIds() as $id) {
            try {
                $data = $apiService->getTeamById($id);
                if (!empty($data['response'])) {
                    $team = $data['response'][0]['team'];
                    $associations[] = [
                        'type' => 'Equipo',
                        'name' => $team['name'],
                        'photo' => $team['logo'],
                        'url' => $this->generateUrl('team_show', ['id' => $id]),
                        'badgeClass' => 'bg-success'
                    ];
                }
            } catch (\Throwable $e) { continue; }
        }

        foreach ($news->getLeagueIds() as $id) {
            try {
                $data = $apiService->getLeagueById($id);
                if (!empty($data['response'])) {
                    $league = $data['response'][0]['league'];
                    $associations[] = [
                        'type' => 'Liga',
                        'name' => $league['name'],
                        'photo' => $league['logo'],
                        'url' => $this->generateUrl('league_show', ['id' => $id]),
                        'badgeClass' => 'bg-warning text-dark'
                    ];
                }
            } catch (\Throwable $e) { continue; }
        }

        foreach ($news->getCoachIds() as $id) {
            try {
                $data = $apiService->getCoachById($id);
                if (!empty($data['response'])) {
                    $coach = $data['response'][0];
                    $associations[] = [
                        'type' => 'Entrenador',
                        'name' => $coach['name'],
                        'photo' => $coach['photo'],
                        'url' => $this->generateUrl('coach_show', ['id' => $id]),
                        'badgeClass' => 'bg-info text-dark'
                    ];
                }
            } catch (\Throwable $e) { continue; }
        }

        foreach ($news->getVenueIds() as $id) {
            try {
                $data = $apiService->getVenueById($id);
                if (!empty($data['response'])) {
                    $venue = $data['response'][0];
                    $associations[] = [
                        'type' => 'Estadio',
                        'name' => $venue['name'],
                        'photo' => $venue['image'],
                        'url' => $this->generateUrl('venue_show', ['id' => $id]),
                        'badgeClass' => 'bg-secondary'
                    ];
                }
            } catch (\Throwable $e) { continue; }
        }

        return $this->json($associations);
    }
}
