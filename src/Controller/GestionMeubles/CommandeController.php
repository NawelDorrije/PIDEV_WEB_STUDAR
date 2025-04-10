<?php

namespace App\Controller\GestionMeubles;

use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommandeController extends AbstractController
{
    private CommandeRepository $commandeRepository;

    // Injection du repository via le constructeur
    public function __construct(CommandeRepository $commandeRepository)
    {
        $this->commandeRepository = $commandeRepository;
    }

    #[Route('/gestion/meubles/commandes/acheteur/{cin}', name: 'app_gestion_meubles_commandes_acheteur')]
    public function listeCommandesParAcheteur(string $cin): Response
    {
        // Récupérer les commandes pour cet acheteur
        $commandes = $this->commandeRepository->findByCinAcheteur($cin);

        // Vérifier si des commandes existent
        if (empty($commandes)) {
            $this->addFlash('warning', 'Aucune commande trouvée pour cet acheteur.');
        }

        return $this->render('gestion_meubles/commande/liste.html.twig', [
            'commandes' => $commandes,
            'cin_acheteur' => $cin,
        ]);
    }
    #[Route('/gestion/meubles/commandes/mes-commandes', name: 'app_gestion_meubles_mes_commandes')]
public function mesCommandes(): Response
{
    $utilisateur = $this->getUser();
    if (!$utilisateur instanceof Utilisateur) {
        throw $this->createAccessDeniedException('Vous devez être connecté.');
    }

    $commandes = $this->commandeRepository->findByAcheteur($utilisateur);

    if (empty($commandes)) {
        $this->addFlash('warning', 'Aucune commande trouvée.');
    }

    return $this->render('gestion_meubles/commande/liste.html.twig', [
        'commandes' => $commandes,
        'cin_acheteur' => $utilisateur->getCin(),
    ]);
}
}