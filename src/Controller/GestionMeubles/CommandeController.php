<?php

namespace App\Controller\GestionMeubles;

use App\Entity\GestionMeubles\Commande;
use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\CommandeRepository;
use App\Repository\GestionMeubles\LignePanierRepository;
use App\Repository\GestionMeubles\MeubleRepository;
use App\Repository\GestionMeubles\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Snappy\Pdf;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class CommandeController extends AbstractController
{
    private PanierRepository $panierRepository;
    private LignePanierRepository $lignePanierRepository;
    private CommandeRepository $commandeRepository;
    private MeubleRepository $meubleRepository;
    private EntityManagerInterface $entityManager;
    private ChartBuilderInterface $chartBuilder;

    public function __construct(
        PanierRepository $panierRepository,
        LignePanierRepository $lignePanierRepository,
        CommandeRepository $commandeRepository,
        MeubleRepository $meubleRepository,
        EntityManagerInterface $entityManager,
        ChartBuilderInterface $chartBuilder
    ) {
        $this->panierRepository = $panierRepository;
        $this->lignePanierRepository = $lignePanierRepository;
        $this->commandeRepository = $commandeRepository;
        $this->meubleRepository = $meubleRepository;
        $this->entityManager = $entityManager;
        $this->chartBuilder = $chartBuilder;
    }
    #[Route('/admin/commandes', name: 'app_gestion_meubles_commandes_admin')]
    public function listeCommandesAdmin(Request $request, PaginatorInterface $paginator): Response
    {
        // Vérifier que l'utilisateur est admin
       $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Récupérer toutes les commandes avec leurs acheteurs associés
        $queryBuilder = $this->commandeRepository->createQueryBuilder('c')
            ->leftJoin('c.acheteur', 'a')
            ->addSelect('a');

        // Paginer les résultats
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10 // 10 commandes par page
        );

        return $this->render('gestion_meubles/commande/liste_admin.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    // Autres méthodes existantes inchangées
    #[Route('/gestion/meubles/commandes/acheteur/{cin}', name: 'app_gestion_meubles_commandes_acheteur')]
    public function listeCommandesParAcheteur(string $cin): Response
    {
        $commandes = $this->commandeRepository->findByCinAcheteur($cin);

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

    #[Route('/confirm-checkout', name: 'app_gestion_meubles_panier_confirm_checkout', methods: ['POST'])]
    public function confirmCheckout(Request $request): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            $this->addFlash('error', 'Vous devez être connecté pour passer une commande.');
            return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
        }

        $panier = $this->panierRepository->findPanierEnCours($utilisateur);
        if (!$panier || !$this->lignePanierRepository->findByPanierId($panier->getId())) {
            $this->addFlash('error', 'Votre panier est vide ou introuvable.');
            return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
        }

        $paymentMethod = $request->request->get('payment_method');
        $address = $request->request->get('address');

        if ($paymentMethod === 'delivery' && empty($address)) {
            $this->addFlash('error', 'Veuillez fournir une adresse pour le paiement à la livraison.');
            return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
        }

        try {
            $this->entityManager->beginTransaction();

            $commande = new Commande();
            $commande->setPanier($panier);
            $commande->setAcheteur($utilisateur);
            $commande->setDateCommande(new \DateTime());
            $commande->setStatut(Commande::STATUT_EN_ATTENTE);
            $commande->setMethodePaiement($paymentMethod === 'delivery' ? Commande::METHODE_PAIEMENT_A_LA_LIVRAISON : Commande::METHODE_STRIPE);
            $commande->setMontantTotal($this->panierRepository->calculerSommePanier($panier->getId()));
            if ($paymentMethod === 'delivery') {
                $commande->setAdresseLivraison($address);
            }

            $commandeId = $this->commandeRepository->ajouterCommande($commande);

            $this->entityManager->commit();

            if ($paymentMethod === 'delivery') {
                $this->addFlash('success', 'Commande confirmée avec paiement à la livraison. Adresse : ' . $address);
            } else {
                $this->addFlash('success', 'Commande confirmée avec paiement par carte.');
            }

            return $this->redirectToRoute('app_gestion_meubles_mes_commandes');
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->addFlash('error', 'Erreur lors de la confirmation de la commande : ' . $e->getMessage());
            return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
        }
    }
    #[Route('/admin/statistiques', name: 'app_gestion_meubles_statistiques')]
    public function statistiquesAdmin(
        Request $request,
        MeubleRepository $meubleRepository,
        CommandeRepository $commandeRepository,
        ChartBuilderInterface $chartBuilder
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN'); 
    
        // Filtres
        $statut = $request->query->get('statut', '');
        $periode = $request->query->get('periode', 'all');
    
        // KPI
        $nombreMeubles = $meubleRepository->count([]);
        $chiffreAffaires = $commandeRepository->getChiffreAffairesTotal($statut, $periode);
        $topVendeur = $commandeRepository->getTopVendeur($periode);
        $commandesParStatut = $commandeRepository->getCommandesParStatut($periode);
    
        // Graphique : Chiffre d'affaires par mois
        $caParMoisData = $commandeRepository->getChiffreAffairesParMois($periode);
        $caChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $caChart->setData([
            'labels' => array_keys($caParMoisData),
            'datasets' => [
                [
                    'label' => 'Chiffre d\'affaires (TND)',
                    'backgroundColor' => '#10b981',
                    'data' => array_values($caParMoisData),
                ],
            ],
        ]);
        $caChart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ]);
    
        // Graphique : Répartition des commandes par statut
        $statutChart = $chartBuilder->createChart(Chart::TYPE_PIE);
        $statutChart->setData([
            'labels' => array_keys($commandesParStatut),
            'datasets' => [
                [
                    'data' => array_values($commandesParStatut),
                    'backgroundColor' => ['#ef4444', '#10b981', '#3b82f6', '#6b7280'],
                ],
            ],
        ]);
    
        // Calendrier
        $ventesParJour = $commandeRepository->getVentesParJour($periode);
        $calendarEvents = [];
        foreach ($ventesParJour as $date => $info) {
            $montant = $info['montant'];
            $calendarEvents[] = [
                'title' => number_format($montant, 2, ',', ' ') . ' TND',
                'start' => $date,
                'backgroundColor' => $montant > 1000 ? '#10b981' : ($montant > 200 ? '#f97316' : '#6b7280'),
                'borderColor' => $montant > 1000 ? '#10b981' : ($montant > 200 ? '#f97316' : '#6b7280'),
            ];
        }
    
        return $this->render('gestion_meubles/meuble/statistiques-admin.html.twig', [
            'nombreMeubles' => $nombreMeubles,
            'chiffreAffaires' => $chiffreAffaires,
            'topVendeur' => $topVendeur,
            'commandesParStatut' => $commandesParStatut,
            'caChart' => $caChart,
            'statutChart' => $statutChart,
            'calendarEvents' => $calendarEvents,
            'filtreStatut' => $statut,
            'filtrePeriode' => $periode,
        ]);
    }
    // #[Route('/commande/{id}/pdf', name: 'app_gestion_meubles_commande_pdf', methods: ['GET'])]
    // public function downloadPdf(
    //     Commande $commande,
    //     Pdf $knpSnappyPdf,
    //     CommandeRepository $commandeRepository
    // ): Response {
    //     //$this->denyAccessUnlessGranted('ROLE_USER');

    //     // Vérifier que la commande appartient à l'utilisateur
    //     if ($commande->getAcheteur() !== $this->getUser()) {
    //         throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à cette commande.');
    //     }

    //     // Générer le HTML à partir du template
    //     $html = $this->renderView('gestion_meubles/commande/bon_commande_pdf.html.twig', [
    //         'commande' => $commande,
    //     ]);

    //     // Générer le PDF
    //     $pdfContent = $knpSnappyPdf->getOutputFromHtml($html);

    //     // Créer la réponse
    //     $response = new Response($pdfContent);
    //     $disposition = $response->headers->makeDisposition(
    //         ResponseHeaderBag::DISPOSITION_ATTACHMENT,
    //         sprintf('bon-commande-%s.pdf', $commande->getId())
    //     );
    //     $response->headers->set('Content-Type', 'application/pdf');
    //     $response->headers->set('Content-Disposition', $disposition);

    //     return $response;
    // }
}