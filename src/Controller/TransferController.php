<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TransferController extends AbstractController
{
    #[Route('/transfer/{id}', name: 'transfer_show')]
    public function show(int $id): Response
    {
        return $this->render('wip.html.twig', [
            'section_name' => 'Detalle del Fichaje',
            'item_id' => $id,
        ]);
    }
}
