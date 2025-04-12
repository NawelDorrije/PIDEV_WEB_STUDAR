<?php

namespace App\Controller\GestionReservation;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rendezvous_PROP')]
final class RendezvousController_PROP extends AbstractController
{
    #[Route('/', name: 'app_rendezvous_index_PROP', methods: ['GET'])]
    public function index(Request $request, RendezvousRepository $rendezvousRepository): Response
    {
        $status = $request->query->get('status');
        
        if ($status) {
            $rendezvouses = $rendezvousRepository->findBy(['status' => $status]);
        } else {
            $rendezvouses = $rendezvousRepository->findAll();
        }
        
        return $this->render('rendezvous/index_PROP.html.twig', [
            'rendezvouses' => $rendezvouses,
            'current_status' => $status
        ]);
    }

  // src/Controller/GestionReservation/RendezvousController.php


    #[Route('/{id}', name: 'app_rendezvous_show_PROP', methods: ['GET'])]
    public function show(Rendezvous $rendezvou): Response
    {
        return $this->render('rendezvous/show_PROP.html.twig', [
            'rendezvou' => $rendezvou,
        ]);
    }

  
    #[Route('/{id}/accept', name: 'app_rendezvous_accept', methods: ['POST'])]
public function accept(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager): Response
{
    if ($this->isCsrfTokenValid('accept'.$rendezvou->getId(), $request->request->get('_token'))) {
        $rendezvou->setStatus('confirmée');
        $entityManager->flush();
    }

    return $this->redirectToRoute('app_rendezvous_index_PROP');
}

#[Route('/{id}/reject', name: 'app_rendezvous_reject', methods: ['POST'])]
public function reject(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager): Response
{
    if ($this->isCsrfTokenValid('reject'.$rendezvou->getId(), $request->request->get('_token'))) {
        $rendezvou->setStatus('refusée');
        $entityManager->flush();
    }

    return $this->redirectToRoute('app_rendezvous_index_PROP');
}

    
}