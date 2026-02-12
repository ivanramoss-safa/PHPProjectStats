<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InjuryController extends AbstractController
{
    #[Route('/injury/{id}', name: 'injury_show')]
    public function show(int $id): Response
    {
        return $this->render('wip.html.twig', [
            'section_name' => 'Detalle de Lesión/Sanción',
            'item_id' => $id,
        ]);
    }
}
