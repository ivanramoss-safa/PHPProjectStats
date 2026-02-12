<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\api\FootballApiService;
use App\Repository\ReviewRepository;
use App\Repository\NewsRepository;
use App\Form\ReviewType;
use App\Entity\Review;

class CoachController extends AbstractController
{
    #[Route('/coaches', name: 'coach_index', methods: ['GET'])]
    public function index(\Symfony\Component\HttpFoundation\Request $request, \App\Service\api\FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo): Response
    {
        $search = $request->query->get('q');
        $coaches = [];
        $topRated = [];

        if ($search) {
            $coaches = $api->searchWithVariations('coachs', $search); 


        } else {
            $topRatedRefs = $reviewRepo->findTopRated('coach', 8);
            $existingIds = [];

            foreach ($topRatedRefs as $ref) {
                $cData = $api->getCoachById((int)$ref['externalId']);
                if (!empty($cData['response'])) {
                    $coachItem = $cData['response'][0];
                    $coachItem['average_rating'] = $ref['avg_rating'];
                    $topRated[] = $coachItem;
                    $existingIds[] = $coachItem['id'];
                }
            }



        }

        return $this->render('coach/index.html.twig', [
            'coaches' => $coaches,
            'topRated' => $topRated,
            'searchQuery' => $search,
        ]);
    }

    #[Route('/coach/{id}', name: 'coach_show')]
    public function show(int $id, \App\Service\api\FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo, \App\Repository\NewsRepository $newsRepo): Response
    {

        $coachData = $api->getCoachById($id);
        $coach = $coachData['response'][0] ?? null;

        if (!$coach) {
            throw $this->createNotFoundException('Entrenador no encontrado.');
        }

        $trophiesData = $api->getCoachTrophies($id);
        $trophies = $trophiesData['response'] ?? [];

        $reviews = $reviewRepo->findBy(
            ['type' => 'coach', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );
        
        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);

        $relatedNews = $newsRepo->findByRelatedEntity('coach', $id);

        $averageRating = $reviewRepo->getAverageRating('coach', (string)$id);

        return $this->render('coach/show.html.twig', [
            'coach' => $coach, 
            'trophies' => $trophies,
            'reviews' => $reviews,
            'reviewForm' => $reviewForm->createView(),
            'averageRating' => $averageRating,
            'relatedNews' => $relatedNews,
        ]);
    }
}
