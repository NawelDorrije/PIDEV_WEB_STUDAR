<?php
namespace App\Controller\GestionReservation;

use App\Entity\Logement;
use App\Entity\Rendezvous;
use App\Entity\Utilisateur;
use App\Form\RendezvousType;
use App\Repository\RendezvousRepository;
use App\Repository\LogementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rendezvous_PROP')]
final class RendezvousController_PROP extends AbstractController
{
    #[Route('/', name: 'app_rendezvous_index_PROP', methods: ['GET'])]
    public function index(
        Request $request, 
        RendezvousRepository $rendezvousRepository, 
        LogementRepository $logementRepository
    ): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $status = $request->query->get('status');
        
        $queryBuilder = $rendezvousRepository->createQueryBuilder('r')
            ->where('r.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $utilisateur);
        
        if ($status) {
            $queryBuilder->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }
        
        $rendezvouses = $queryBuilder->getQuery()->getResult();
        
        return $this->render('rendezvous/index_PROP.html.twig', [
            'rendezvouses' => $rendezvouses,
            'current_status' => $status,
            'logement_repo' => $logementRepository
        ]);
    }

    #[Route('/{id}', name: 'app_rendezvous_show_PROP', methods: ['GET'])]
    public function show(Rendezvous $rendezvou, LogementRepository $logementRepository): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $rendezvou->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce rendez-vous.');
        }

        return $this->render('rendezvous/show_PROP.html.twig', [
            'rendezvou' => $rendezvou,
            'logement_repo' => $logementRepository
        ]);
    }

    #[Route('/{id}/accept', name: 'app_rendezvous_accept', methods: ['POST'])]
    public function accept(
        Request $request,
        Rendezvous $rendezvou,
        EntityManagerInterface $entityManager
    ): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $rendezvou->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accepter ce rendez-vous.');
        }

        if ($this->isCsrfTokenValid('accept'.$rendezvou->getId(), $request->request->get('_token'))) {
            $rendezvou->setStatus('confirmée');
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_rendezvous_index_PROP');
    }

    #[Route('/{id}/reject', name: 'app_rendezvous_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        Rendezvous $rendezvou,
        EntityManagerInterface $entityManager
    ): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $rendezvou->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas rejeter ce rendez-vous.');
        }

        if ($this->isCsrfTokenValid('reject'.$rendezvou->getId(), $request->request->get('_token'))) {
            $rendezvou->setStatus('refusée');
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_rendezvous_index_PROP');
    }

    #[Route('/webhook/twilio', name: 'app_rendezvous_twilio_webhook', methods: ['POST'])]
    public function twilioWebhook(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $message = $request->request->get('Body');
        $from = $request->request->get('From');
        $from = str_replace('whatsapp:', '', $from);

        if (preg_match('/^(Accept|Reject)\s+(\d+)$/', $message, $matches)) {
            $action = $matches[1];
            $rendezvousId = $matches[2];
            $rendezvous = $entityManager->getRepository(Rendezvous::class)->find($rendezvousId);

            if ($rendezvous && $rendezvous->getProprietaire()->getNumTel() === $from) {
                $rendezvous->setStatus($action === 'Accept' ? 'confirmée' : 'refusée');
                $entityManager->flush();
                return new JsonResponse(['status' => 'success']);
            }
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Invalid message or unauthorized'], 400);
    }
}