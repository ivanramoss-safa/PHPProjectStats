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

class VenueController extends AbstractController
{
    #[Route('/venues', name: 'venue_index', methods: ['GET'])]
    public function index(\Symfony\Component\HttpFoundation\Request $request, \App\Service\api\FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo): Response
    {
        $search = $request->query->get('q');
        $venues = [];
        $topRated = [];

        if ($search) {
            $venues = $api->searchWithVariations('venues', $search);
        } else {
            $topRatedRefs = $reviewRepo->findTopRated('venue', 8);
            $existingIds = [];

            foreach ($topRatedRefs as $ref) {
                $vData = $api->getVenueById((int)$ref['externalId']);
                if (!empty($vData['response'])) {
                    $venueItem = $vData['response'][0];
                    $venueItem['average_rating'] = $ref['avg_rating'];
                    $topRated[] = $venueItem;
                    $existingIds[] = $venueItem['id'];
                }
            }










        }

        return $this->render('venue/index.html.twig', [
            'venues' => $venues,
            'topRated' => $topRated,
            'searchQuery' => $search,
        ]);
    }

    #[Route('/venue/{id}', name: 'venue_show')]
    public function show(int $id, \App\Service\api\FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo, \App\Repository\NewsRepository $newsRepo): Response
    {

        $venueData = $api->getVenueById($id);
        $venue = $venueData['response'][0] ?? null;

        if (!$venue) {
            throw $this->createNotFoundException('Estadio no encontrado.');
        }

        $reviews = $reviewRepo->findBy(
            ['type' => 'venue', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );
        
        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);

        $relatedNews = $newsRepo->findByRelatedEntity('venue', $id);

        $averageRating = $reviewRepo->getAverageRating('venue', (string)$id);

        return $this->render('venue/show.html.twig', [
            'venue' => $venue,
            'reviews' => $reviews,
            'reviewForm' => $reviewForm->createView(),
            'averageRating' => $averageRating,
            'relatedNews' => $relatedNews,
        ]);
    }
}
