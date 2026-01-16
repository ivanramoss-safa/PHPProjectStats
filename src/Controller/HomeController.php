<?php

namespace App\Controller;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(FootballApiService $apiService): Response
    {

        $liveData = $apiService->getLiveFixtures();

        $partidos = $liveData['response'] ?? [];

        return $this->render('home/index.html.twig', [
            'partidos' => $partidos,
        ]);
    }
}
