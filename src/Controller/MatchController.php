<?php

namespace App\Controller;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MatchController extends AbstractController
{
    #[Route('/match/{id}', name: 'match_show')]
    public function show(int $id, Request $request, FootballApiService $api, \App\Repository\ReviewRepository $reviewRepo, \App\Repository\NewsRepository $newsRepo): Response
    {

        $fixtureData = $api->getFixtureById($id);
        $fixture = $fixtureData['response'][0] ?? null;

        if (!$fixture) {
            throw $this->createNotFoundException('Partido no encontrado.');
        }

        $reviews = $reviewRepo->findBy(
            ['type' => 'fixture', 'externalId' => (string)$id],
            ['createdAt' => 'DESC']
        );
        
        $review = new \App\Entity\Review();
        $reviewForm = $this->createForm(\App\Form\ReviewType::class, $review);


        $relatedNews = $newsRepo->findByRelatedEntity('fixture', $id);

        return $this->render('match/show.html.twig', [
            'fixture' => $fixture,
            'reviews' => $reviews,
            'reviewForm' => $reviewForm->createView(),
            'relatedNews' => $relatedNews,
        ]);
    }
}
