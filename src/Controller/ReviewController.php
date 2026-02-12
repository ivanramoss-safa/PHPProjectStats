<?php

namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReviewController extends AbstractController
{
    #[Route('/review/add/{type}/{externalId}', name: 'review_add', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function add(Request $request, string $type, string $externalId, EntityManagerInterface $em): Response
    {
        $review = new Review();
        $review->setType($type);
        $review->setExternalId($externalId);
        $review->setUser($this->getUser());
        $review->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {




            
            $em->persist($review);
            $em->flush();

            $this->addFlash('success', '¡Gracias por tu valoración!');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirect($request->headers->get('referer') ?? '/');
    }

    #[Route('/review/delete/{id}', name: 'review_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(Review $review, EntityManagerInterface $em, Request $request): Response
    {

        if ($review->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('No tienes permiso para borrar este comentario.');
        }

        if ($this->isCsrfTokenValid('delete'.$review->getId(), $request->request->get('_token'))) {
            $em->remove($review);
            $em->flush();
            $this->addFlash('success', 'Comentario eliminado.');
        }

        return $this->redirect($request->headers->get('referer'));
    }

    #[Route('/review/list/{type}/{externalId}', name: 'review_list', methods: ['GET'])]
    public function list(string $type, string $externalId, ReviewRepository $repo): Response
    {
        $reviews = $repo->findBy(
            ['type' => $type, 'externalId' => $externalId],
            ['createdAt' => 'DESC']
        );

        return $this->render('review/_list.html.twig', [
            'reviews' => $reviews,
        ]);
    }
}
