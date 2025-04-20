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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Stripe\Checkout\Session;
use Stripe\Stripe;

final class CommandeController extends AbstractController
{
    private PanierRepository $panierRepository;
    private LignePanierRepository $lignePanierRepository;
    private CommandeRepository $commandeRepository;
    private MeubleRepository $meubleRepository;
    private EntityManagerInterface $entityManager;
    private ChartBuilderInterface $chartBuilder;
    private $pdf;
    private LoggerInterface $logger;
    private MailerInterface $mailer;

    public function __construct(
        PanierRepository $panierRepository,
        LignePanierRepository $lignePanierRepository,
        CommandeRepository $commandeRepository,
        MeubleRepository $meubleRepository,
        EntityManagerInterface $entityManager,
        ChartBuilderInterface $chartBuilder,
        Pdf $pdf,
        LoggerInterface $logger,
        MailerInterface $mailer
    ) {
        $this->panierRepository = $panierRepository;
        $this->lignePanierRepository = $lignePanierRepository;
        $this->commandeRepository = $commandeRepository;
        $this->meubleRepository = $meubleRepository;
        $this->entityManager = $entityManager;
        $this->chartBuilder = $chartBuilder;
        $this->pdf = $pdf;
        $this->logger = $logger;
        $this->mailer = $mailer;
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
            $commande->setMethodePaiement('Paiement a la livraison');
            $commande->setMontantTotal($this->panierRepository->calculerSommePanier($panier->getId()));
            if ($paymentMethod === 'delivery') {
                $commande->setAdresseLivraison($address);
            }

            $commandeId = $this->commandeRepository->ajouterCommande($commande);

            if ($paymentMethod === 'delivery') {
                // Paiement à la livraison : finaliser directement
                $this->entityManager->commit();
                $this->sendConfirmationEmailToBuyer($commande, $utilisateur, $address);
                $this->sendNotificationEmailsToSellers($commande);
                $this->addFlash('success', 'Commande confirmée avec paiement à la livraison. Adresse : ' . $address);
                return $this->redirectToRoute('app_gestion_meubles_mes_commandes');
            } else {
                // Paiement par carte : initier une session Stripe Checkout
                Stripe::setApiKey($this->getParameter('stripe_secret_key'));

                $lineItems = [];
                foreach ($panier->getLignesPanier() as $ligne) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => [
                                'name' => $ligne->getMeuble()->getNom(),
                            ],
                            'unit_amount' => $ligne->getMeuble()->getPrix() * 100, // Montant en centimes
                        ],
                        'quantity' => 1,
                    ];
                }

                // Générer les URLs avec l'URL de base
                $baseUrl = rtrim($this->getParameter('app_url'), '/');
                $successPath = ltrim($this->generateUrl('app_gestion_meubles_payment_success', ['commandeId' => $commandeId]), '/');
                $cancelPath = ltrim($this->generateUrl('app_gestion_meubles_payment_cancel', []), '/');

                $successUrl = $baseUrl . '/' . $successPath;
                $cancelUrl = $baseUrl . '/' . $cancelPath;

                $this->logger->info('Success URL: ' . $successUrl);
                $this->logger->info('Cancel URL: ' . $cancelUrl);

                $session = Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'commande_id' => $commandeId,
                    ],
                ]);

                $commande->setSessionStripe($session->id);
                $commande->setMethodePaiement('stripe');
                $commande->setStatut(Commande::STATUT_PAYEE);
                $this->entityManager->flush();
                $this->entityManager->commit();

                return $this->redirect($session->url);
            }
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Erreur lors de la confirmation de la commande : ' . $e->getMessage());
            $this->addFlash('error', 'Erreur lors de la confirmation de la commande : ' . $e->getMessage());
            return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
        }
    }

    #[Route('/payment/success/{commandeId}', name: 'app_gestion_meubles_payment_success')]
    public function paymentSuccess(int $commandeId): Response
    {
        $commande = $this->commandeRepository->find($commandeId);
        if (!$commande) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
        }

        // Vérifier le paiement via Stripe
        Stripe::setApiKey($this->getParameter('stripe_secret_key'));
        $session = Session::retrieve($commande->getSessionStripe());

        if ($session->payment_status === 'paid') {
            $commande->setStatut(Commande::STATUT_PAYEE);
            $this->entityManager->flush();

            // Envoyer un email de confirmation à l'acheteur
            $this->sendConfirmationEmailToBuyer($commande, $commande->getAcheteur(), $commande->getAdresseLivraison());

            // Envoyer des notifications aux vendeurs
            $this->sendNotificationEmailsToSellers($commande);

            // Générer un bon de commande PDF
            $this->generateOrderConfirmationPdf($commande);

            $this->addFlash('success', 'Paiement par carte confirmé avec succès. Un email de confirmation a été envoyé.');

            // Redirection immédiate vers app_gestion_meubles_mes_commandes
            return $this->redirectToRoute('app_gestion_meubles_mes_commandes');
        } else {
            $this->addFlash('error', 'Le paiement n\'a pas été complété.');
            return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
        }
    }

    #[Route('/payment/cancel', name: 'app_gestion_meubles_payment_cancel')]
    public function paymentCancel(): Response
    {
        $this->addFlash('error', 'Paiement annulé.');
        return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
    }

    /**
     * Envoie un email de confirmation à l'acheteur avec un template Twig.
     */
    private function sendConfirmationEmailToBuyer(Commande $commande, Utilisateur $utilisateur, ?string $address): void
    {
        $email = (new Email())
            ->from('votre_email@gmail.com')
            ->to($utilisateur->getEmail())
            ->subject('Confirmation de votre commande')
            ->html($this->renderView('emails/confirmation_acheteur.html.twig', [
                'commande' => $commande,
                'address' => $address,
                'nom' => $utilisateur->getNom(),
                'prenom' => $utilisateur->getPrenom(),
            ]));

        $this->mailer->send($email);
    }

    /**
     * Envoie des emails de notification aux vendeurs avec un template Twig.
     */
    private function sendNotificationEmailsToSellers(Commande $commande): void
    {
        $vendeursArticles = [];

        foreach ($commande->getPanier()->getLignesPanier() as $ligne) {
            $vendeur = $ligne->getMeuble()->getVendeur();
            if (!isset($vendeursArticles[$vendeur->getCin()])) {
                $vendeursArticles[$vendeur->getCin()] = [
                    'vendeur' => $vendeur,
                    'articles' => [],
                ];
            }
            $vendeursArticles[$vendeur->getCin()]['articles'][] = $ligne;
        }

        foreach ($vendeursArticles as $data) {
            $vendeur = $data['vendeur'];
            $articles = $data['articles'];

            $email = (new Email())
                ->from('votre_email@gmail.com')
                ->to($vendeur->getEmail())
                ->subject('Nouvelle commande pour vos articles')
                ->html($this->renderView('emails/notification_vendeur.html.twig', [
                    'commande' => $commande,
                    'articles' => $articles,
                    'nom' => $vendeur->getNom(),
                    'prenom' => $vendeur->getPrenom(),
                ]));

            $this->mailer->send($email);
        }
    }

    /**
     * Génère un bon de commande PDF après un paiement réussi.
     */
    private function generateOrderConfirmationPdf(Commande $commande): void
    {
        $html = $this->renderView('gestion_meubles/commande/bon_commande_pdf.html.twig', [
            'commande' => $commande,
        ]);

        $pdfContent = $this->pdf->getOutputFromHtml($html);
        $pdfPath = $this->getParameter('kernel.project_dir') . '/public/uploads/commandes/commande_' . $commande->getId() . '.pdf';
        file_put_contents($pdfPath, $pdfContent);
    }

    #[Route('/admin/commandes', name: 'app_gestion_meubles_commandes_admin')]
    public function listeCommandesAdmin(Request $request, PaginatorInterface $paginator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $queryBuilder = $this->commandeRepository->createQueryBuilder('c')
            ->leftJoin('c.acheteur', 'a')
            ->addSelect('a');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('gestion_meubles/commande/liste_admin.html.twig', [
            'pagination' => $pagination,
        ]);
    }

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

    #[Route('/admin/statistiques', name: 'app_gestion_meubles_statistiques')]
    public function statistiquesAdmin(
        Request $request,
        MeubleRepository $meubleRepository,
        CommandeRepository $commandeRepository,
        ChartBuilderInterface $chartBuilder
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $statut = $request->query->get('statut', '');
        $periode = $request->query->get('periode', 'all');

        $nombreMeubles = $meubleRepository->count([]);
        $chiffreAffaires = $commandeRepository->getChiffreAffairesTotal($statut, $periode);
        $topVendeur = $commandeRepository->getTopVendeur($periode);
        $commandesParStatut = $commandeRepository->getCommandesParStatut($periode);

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

    #[Route('/commande/{id}/pdf', name: 'app_gestion_meubles_commande_pdf', methods: ['GET'])]
    public function downloadCommandePdf(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);

        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $utilisateur = $this->getUser();
        if (!$utilisateur || $commande->getAcheteur() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à télécharger cette commande.');
        }

        $html = $this->renderView('gestion_meubles/commande/bon_commande_pdf.html.twig', [
            'commande' => $commande,
        ]);

        $pdfContent = $this->pdf->getOutputFromHtml($html);

        $response = new Response($pdfContent);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('commande_%s.pdf', $commande->getId())
        );
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/commande/{id}/annuler', name: 'app_gestion_meubles_commande_annuler', methods: ['POST'])]
    public function annulerCommande(Request $request, int $id): JsonResponse
    {
        $this->logger->info('Tentative d\'annulation de la commande ID: ' . $id);

        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            $this->logger->warning('Utilisateur non connecté pour annulation de la commande ID: ' . $id);
            return new JsonResponse(['error' => 'Vous devez être connecté.'], 403);
        }

        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            $this->logger->warning('Commande non trouvée: ID ' . $id);
            return new JsonResponse(['error' => 'Commande non trouvée.'], 404);
        }

        if ($commande->getAcheteur() !== $utilisateur) {
            $this->logger->warning('Utilisateur non autorisé pour la commande ID: ' . $id);
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à annuler cette commande.'], 403);
        }

        $raisonAnnulation = $request->request->get('raison', 'Annulation par l\'utilisateur');

        try {
            $success = $this->commandeRepository->annulerCommande($id, $raisonAnnulation, $utilisateur);
            if ($success) {
                $this->logger->info('Commande annulée avec succès: ID ' . $id);
                return new JsonResponse(['success' => 'Commande annulée avec succès.']);
            } else {
                $this->logger->warning('Échec de l\'annulation: commande ID ' . $id . ' (déjà annulée ou délai dépassé)');
                return new JsonResponse(['error' => 'Impossible d\'annuler la commande. Vérifiez si elle est déjà annulée ou si le délai de 24h est dépassé.'], 400);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'annulation de la commande ID: ' . $id . ' - ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur lors de l\'annulation : ' . $e->getMessage()], 500);
        }
    }
}