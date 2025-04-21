<?php

namespace App\Controller\GestionReservation;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use App\Repository\RendezvousRepository;
use App\Repository\LogementRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rendezvous_ADMIN')]
final class RendezvousController_ADMIN extends AbstractController
{
  
    #[Route('/', name: 'app_rendezvous_index_ADMIN', methods: ['GET'])]
    public function index(Request $request, RendezvousRepository $rendezvousRepository, LogementRepository $logementRepository): Response
    {
        $status = $request->query->get('status');
        
        if ($status) {
            $rendezvouses = $rendezvousRepository->findBy(['status' => $status]);
        } else {
            $rendezvouses = $rendezvousRepository->findAll();
        }
        
        return $this->render('rendezvous/index_ADMIN.html.twig', [
            'rendezvouses' => $rendezvouses,
            'current_status' => $status,
            'logement_repo' => $logementRepository

        ]);
    }

  // src/Controller/GestionReservation/RendezvousController.php


    #[Route('/{id}', name: 'app_rendezvous_show_ADMIN', methods: ['GET'])]
    public function show(Rendezvous $rendezvou, LogementRepository $logementRepository): Response
    {
        return $this->render('rendezvous/show_ADMIN.html.twig', [
            'rendezvou' => $rendezvou,
            'logement_repo' => $logementRepository
        ]);
    }
    
}