<?php

namespace App\Controller\GestionMeubles;

use App\Entity\GestionMeubles\Commande;
use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\CommandeRepository;
use App\Repository\GestionMeubles\LignePanierRepository;
use App\Repository\GestionMeubles\MeubleRepository;
use App\Repository\GestionMeubles\PanierRepository;
use App\Service\CurrencyConverterService;
use App\Service\CancellationAnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Snappy\Pdf;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Route('/gestion/meubles')]
final class CommandeController extends AbstractController
{
    private PanierRepository $panierRepository;
    private LignePanierRepository $lignePanierRepository;
    private CommandeRepository $commandeRepository;
    private MeubleRepository $meubleRepository;
    private EntityManagerInterface $entityManager;
    private ChartBuilderInterface $chartBuilder;
    private Pdf $pdf;
    private LoggerInterface $logger;
    private MailerInterface $mailer;
    private CurrencyConverterService $currencyConverter;
    private CancellationAnalysisService $cancellationAnalysisService;

    public function __construct(
        PanierRepository $panierRepository,
        LignePanierRepository $lignePanierRepository,
        CommandeRepository $commandeRepository,
        MeubleRepository $meubleRepository,
        EntityManagerInterface $entityManager,
        ChartBuilderInterface $chartBuilder,
        Pdf $pdf,
        LoggerInterface $logger,
        MailerInterface $mailer,
        CurrencyConverterService $currencyConverter,
        CancellationAnalysisService $cancellationAnalysisService
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
        $this->currencyConverter = $currencyConverter;
        $this->cancellationAnalysisService = $cancellationAnalysisService;
    }

    #[Route('/commande/{id}/annuler', name: 'app_gestion_meubles_commande_annuler', methods: ['POST'])]
    public function annulerCommande(Request $request, int $id): JsonResponse
    {
        $this->logger->info('Tentative d\'annulation de la commande ID: ' . $id);

        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            $this->logger->warning('Utilisateur non connecté pour annulation de la commande ID: ' . $id);
            return new JsonResponse(['error' => 'Vous devez être connecté pour annuler une commande.'], 403);
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

        if ($commande->getStatut() === Commande::STATUT_ANNULEE) {
            $this->logger->warning('Commande déjà annulée: ID ' . $id);
            return new JsonResponse(['error' => 'Cette commande est déjà annulée.'], 400);
        }

        $dateCommande = $commande->getDateCommande();
        $now = new \DateTime();
        $interval = $now->diff($dateCommande);
        $heuresEcoulees = ($interval->days * 24) + $interval->h;
        if ($heuresEcoulees > 24) {
            $this->logger->warning('Délai d\'annulation dépassé pour la commande ID: ' . $id);
            return new JsonResponse(['error' => 'Le délai de 24 heures pour annuler la commande est dépassé.'], 400);
        }

        $raisonAnnulation = trim($request->request->get('raison', ''));
        if (empty($raisonAnnulation) || strlen($raisonAnnulation) < 5) {
            $this->logger->warning('Raison d\'annulation invalide pour la commande ID: ' . $id);
            return new JsonResponse(['error' => 'Veuillez fournir une raison d\'annulation valide (minimum 5 caractères).'], 400);
        }

        // Analyze cancellation reason using CancellationAnalysisService
        $analysisResult = $this->cancellationAnalysisService->analyzeCancellationReason($raisonAnnulation);
        $this->logger->info('Analyse de la raison d\'annulation pour commande ID: ' . $id . ' - Résultat: ' . ($analysisResult['isValid'] ? 'Valid' : 'Invalid') . ' - Explication: ' . $analysisResult['explanation']);

        if (!$analysisResult['isValid']) {
            $this->logger->warning('Raison d\'annulation rejetée pour commande ID: ' . $id . ' - Explication: ' . $analysisResult['explanation']);
            return new JsonResponse(['error' => 'Raison d\'annulation invalide : ' . $analysisResult['explanation']], 400);
        }

        try {
            $this->entityManager->beginTransaction();

            $success = $this->commandeRepository->annulerCommande($id, $raisonAnnulation, $utilisateur);
            if (!$success) {
                $this->logger->warning('Échec de l\'annulation: commande ID ' . $id);
                $this->entityManager->rollback();
                return new JsonResponse(['error' => 'Impossible d\'annuler la commande.'], 400);
            }

            $this->sendCancellationEmailToBuyer($commande, $utilisateur, $raisonAnnulation);
            $this->sendCancellationEmailsToSellers($commande, $raisonAnnulation);

            $this->entityManager->commit();
            $this->logger->info('Commande annulée avec succès: ID ' . $id);

            return new JsonResponse(['success' => 'Commande annulée avec succès. Un email de confirmation a été envoyé.']);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Erreur lors de l\'annulation de la commande ID: ' . $id . ' - ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur lors de l\'annulation : ' . $e->getMessage()], 500);
        }
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

            // Calculate total in TND
            $totalInTnd = $this->panierRepository->calculerSommePanier($panier->getId());
            if (!is_numeric($totalInTnd) || $totalInTnd < 0) {
                $this->logger->error("Invalid total amount in TND: {$totalInTnd}");
                throw new \Exception('Montant total du panier invalide.');
            }

            // Initialize variables for amount and currency
            $totalAmount = $totalInTnd;
            $currency = 'TND';

            // Convert to EUR only for Stripe payments
            if ($paymentMethod !== 'delivery') {
                $totalInEur = $this->currencyConverter->convertTndToEur($totalInTnd);
                if ($totalInEur === -1) {
                    $this->entityManager->rollback();
                    $this->addFlash('error', 'Erreur lors de la conversion du montant en EUR.');
                    return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
                }
                $totalAmount = $totalInEur;
                $currency = 'EUR';
            }

            $commande = new Commande();
            $commande->setPanier($panier);
            $commande->setAcheteur($utilisateur);
            $commande->setDateCommande(new \DateTime());
            $commande->setStatut(Commande::STATUT_EN_ATTENTE);
            $commande->setMethodePaiement($paymentMethod === 'delivery' ? 'Paiement_a_la_livraison' : 'stripe');
         
            $commande->setMontantTotal($totalAmount);
            if ($paymentMethod === 'delivery') {
                $commande->setAdresseLivraison($address);
            }

            $commandeId = $this->commandeRepository->ajouterCommande($commande);

            if ($paymentMethod === 'delivery') {
                $this->entityManager->commit();
                $this->addFlash('success', 'Commande confirmée avec paiement à la livraison. Adresse : ' . $address);
                $this->sendConfirmationEmailToBuyer($commande, $commande->getAcheteur(), $address);
                $this->sendNotificationEmailsToSellers($commande);
                return $this->redirectToRoute('app_gestion_meubles_mes_commandes');
            } else {
                // Paiement par carte : initier une session Stripe Checkout
                Stripe::setApiKey($this->getParameter('stripe_secret_key'));

                $lineItems = [];
                foreach ($panier->getLignesPanier() as $ligne) {
                    $meuble = $ligne->getMeuble();
                    $prixTnd = $meuble->getPrix();

                    if (!is_numeric($prixTnd) || $prixTnd < 0) {
                        $this->logger->error("Invalid price for meuble ID {$meuble->getId()}: {$prixTnd} TND");
                        throw new \Exception("Prix invalide pour l'article {$meuble->getNom()}.");
                    }

                    // Convert each item price to EUR for Stripe
                    $priceInEur = $this->currencyConverter->convertTndToEur($prixTnd);
                    if ($priceInEur === -1) {
                        $this->entityManager->rollback();
                        $this->addFlash('error', "Erreur lors de la conversion du prix de l'article {$meuble->getNom()} en EUR.");
                        return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
                    }

                    $unitAmount = intval(round($priceInEur * 100));
                    if ($unitAmount <= 0) {
                        $this->logger->error("Invalid unit amount for meuble ID {$meuble->getId()}: {$unitAmount} centimes (from {$priceInEur} EUR)");
                        throw new \Exception("Montant unitaire invalide pour l'article {$meuble->getNom()}.");
                    }

                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => [
                                'name' => $meuble->getNom(),
                            ],
                            'unit_amount' => $unitAmount,
                        ],
                        'quantity' => 1,
                    ];
                }

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

        Stripe::setApiKey($this->getParameter('stripe_secret_key'));
        $session = Session::retrieve($commande->getSessionStripe());

        if ($session->payment_status === 'paid') {
            $commande->setStatut(Commande::STATUT_PAYEE);
            $this->entityManager->flush();

            $this->sendConfirmationEmailToBuyer($commande, $commande->getAcheteur(), $commande->getAdresseLivraison());
            $this->sendNotificationEmailsToSellers($commande);
            $this->generateOrderConfirmationPdf($commande);

            $this->addFlash('success', 'Paiement par carte confirmé avec succès. Un email de confirmation a été envoyé.');
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

    private function sendConfirmationEmailToBuyer(Commande $commande, Utilisateur $utilisateur, ?string $address): void
    {
        try {
            $email = (new Email())
                ->from('naweldorrije789@gmail.com')
                ->to($utilisateur->getEmail())
                ->subject('Confirmation de votre commande')
                ->html($this->renderView('emails/confirmation_acheteur.html.twig', [
                    'commande' => $commande,
                    'address' => $address,
                    'nom' => $utilisateur->getNom(),
                    'prenom' => $utilisateur->getPrenom(),
                ]));
    
            // Embed the logo
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
            if (file_exists($logoPath)) {
                $email->embed(fopen($logoPath, 'r'), 'logo.png', 'image/png');
                $this->logger->info('Logo joint à l\'email de confirmation pour l\'acheteur : ' . $utilisateur->getEmail());
            } else {
                $this->logger->error('Fichier logo introuvable à : ' . $logoPath);
            }
    
            $this->mailer->send($email);
            $this->logger->info('Email de confirmation envoyé à ' . $utilisateur->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de confirmation à ' . $utilisateur->getEmail() . ' : ' . $e->getMessage());
        }
    }
    
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
    
            try {
                $email = (new Email())
                    ->from('naweldorrije789@gmail.com')
                    ->to($vendeur->getEmail())
                    ->subject('Nouvelle commande pour vos articles')
                    ->html($this->renderView('emails/notification_vendeur.html.twig', [
                        'commande' => $commande,
                        'articles' => $articles,
                        'nom' => $vendeur->getNom(),
                        'prenom' => $vendeur->getPrenom(),
                    ]));
    
                // Embed the logo
                $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
                if (file_exists($logoPath)) {
                    $email->embed(fopen($logoPath, 'r'), 'logo.png', 'image/png');
                    $this->logger->info('Logo joint à l\'email de notification pour le vendeur : ' . $vendeur->getEmail());
                } else {
                    $this->logger->error('Fichier logo introuvable à : ' . $logoPath);
                }
    
                $this->mailer->send($email);
                $this->logger->info('Email de notification envoyé à ' . $vendeur->getEmail());
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi de l\'email de notification à ' . $vendeur->getEmail() . ' : ' . $e->getMessage());
            }
        }
    }

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

    #[Route('/admin/export-commandes', name: 'app_gestion_meubles_export_commandes')]
    public function exportCommandes(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $month = $request->query->get('month');
        $year = $request->query->get('year');
        $format = $request->query->get('format', 'csv');

        if (!$month || !$year) {
            throw $this->createNotFoundException('Le mois et l\'année sont requis pour l\'exportation.');
        }

        $startDate = \DateTime::createFromFormat('Y-m-d', "$year-$month-01");
        if (!$startDate) {
            throw $this->createNotFoundException('Mois ou année invalide.');
        }
        $endDate = clone $startDate;
        $endDate->modify('last day of this month')->setTime(23, 59, 59);

        $queryBuilder = $this->commandeRepository->createQueryBuilder('c')
            ->leftJoin('c.acheteur', 'a')
            ->addSelect('a')
            ->where('c.dateCommande BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $commandes = $queryBuilder->getQuery()->getResult();

        $headers = [
            'Code',
            'Acheteur',
            'Date Rosée',
            'Statut',
            'Montant (TND)',
            'Méthode',
            'Adresse',
            'Date d\'annulation',
            'Raison d\'annulation',
        ];

        $data = [];
        foreach ($commandes as $commande) {
            $data[] = [
                $commande->getId(),
                $commande->getAcheteur() ? $commande->getAcheteur()->getNom() . ' ' . $commande->getAcheteur()->getPrenom() : 'Inconnu',
                $commande->getDateCommande()->format('d/m/Y H:i'),
                $commande->getStatut(),
                number_format($commande->getMontantTotal(), 2, ',', ' '),
                $commande->getMethodePaiement() ?? 'N/A',
                $commande->getAdresseLivraison() ?? 'N/A',
                $commande->getDateAnnulation() ? $commande->getDateAnnulation()->format('d/m/Y H:i') : 'N/A',
                $commande->getRaisonAnnulation() ?? 'N/A',
            ];
        }

        $filename = sprintf('commandes_%s_%s', $month, $year);

        if ($format === 'excel') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($data, null, 'A2');

            foreach (range('A', 'I') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'commandes_export_') . '.xlsx';
            $writer->save($tempFile);

            $response = new BinaryFileResponse($tempFile);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename . '.xlsx'
            );
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->deleteFileAfterSend(true);

            return $response;
        } else {
            $response = new StreamedResponse();
            $response->setCallback(function () use ($headers, $data) {
                $handle = fopen('php://output', 'w+');

                fputcsv($handle, $headers, ';');
                foreach ($data as $row) {
                    fputcsv($handle, $row, ';');
                }

                fclose($handle);
            });

            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');

            return $response;
        }
    }

    #[Route('/commandes/acheteur/{cin}', name: 'app_gestion_meubles_commandes_acheteur')]
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

    #[Route('/commandes/mes-commandes', name: 'app_gestion_meubles_mes_commandes')]
    public function mesCommandes(): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        try {
            $commandes = $this->commandeRepository->findByAcheteur($utilisateur);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des commandes pour l\'utilisateur ID: ' . $utilisateur->getCin() . ' - ' . $e->getMessage());
            throw new \RuntimeException('Erreur lors de la récupération des commandes : ' . $e->getMessage());
        }

        if (empty($commandes)) {
            $this->addFlash('warning', 'Aucune commande trouvée.');
        }

        return $this->render('gestion_meubles/commande/liste.html.twig', [
            'commandes' => $commandes,
            'cin_acheteur' => $utilisateur->getCin(),
        ]);
    }

    #[Route('/commandes/mes-ventes', name: 'app_gestion_meubles_mes_ventes')]
    public function mesVentes(Request $request, PaginatorInterface $paginator): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        try {
            $queryBuilder = $this->commandeRepository->createQueryBuilder('c')
                ->leftJoin('c.panier', 'p')
                ->leftJoin('p.lignesPanier', 'lp')
                ->leftJoin('lp.meuble', 'm')
                ->leftJoin('m.vendeur', 'v')
                ->andWhere('v = :vendeur')
                ->setParameter('vendeur', $utilisateur)
                ->orderBy('c.id', 'DESC');

            $pagination = $paginator->paginate(
                $queryBuilder,
                $request->query->getInt('page', 1),
                5 // Number of items per page
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des ventes pour le vendeur ID: ' . $utilisateur->getCin() . ' - ' . $e->getMessage());
            throw new \RuntimeException('Erreur lors de la récupération des ventes : ' . $e->getMessage());
        }

        if ($pagination->getTotalItemCount() === 0) {
            $this->addFlash('warning', 'Aucune vente trouvée.');
        }

        return $this->render('gestion_meubles/commande/liste_ventes.html.twig', [
            'pagination' => $pagination,
            'vendeur' => $utilisateur,
        ]);
    }

    #[Route('/commandes/export-ventes', name: 'app_gestion_meubles_export_ventes')]
    public function exportVentes(Request $request): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $format = $request->query->get('format', 'csv');

        try {
            $queryBuilder = $this->commandeRepository->createQueryBuilder('c')
                ->leftJoin('c.panier', 'p')
                ->leftJoin('p.lignesPanier', 'lp')
                ->leftJoin('lp.meuble', 'm')
                ->leftJoin('m.vendeur', 'v')
                ->leftJoin('c.acheteur', 'a')
                ->addSelect('a')
                ->andWhere('v = :vendeur')
                ->setParameter('vendeur', $utilisateur);

            $commandes = $queryBuilder->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des ventes pour l\'exportation (utilisateur ID: ' . $utilisateur->getCin() . '): ' . $e->getMessage());
            throw new \RuntimeException('Erreur lors de la récupération des ventes pour l\'exportation : ' . $e->getMessage());
        }

        $headers = [
            'Code',
            'Acheteur',
            'Date',
            'Statut',
            'Montant (TND)',
            'Articles',
        ];

        $data = [];
        foreach ($commandes as $commande) {
            $articles = [];
            foreach ($commande->getPanier()->getLignesPanier() as $ligne) {
                if ($ligne->getMeuble()->getVendeur() === $utilisateur) {
                    $articles[] = $ligne->getMeuble()->getNom() . ' (' . number_format($ligne->getMeuble()->getPrix(), 2, ',', ' ') . ' TND)';
                }
            }
            $articlesStr = implode('; ', $articles);

            $data[] = [
                $commande->getId(),
                $commande->getAcheteur() ? $commande->getAcheteur()->getNom() . ' ' . $commande->getAcheteur()->getPrenom() : 'Inconnu',
                $commande->getDateCommande()->format('d/m/Y H:i'),
                $commande->getStatut(),
                number_format($commande->getMontantTotal(), 2, ',', ' '),
                $articlesStr,
            ];
        }

        $filename = sprintf('mes_ventes_%s', date('Y-m-d'));

        if ($format === 'excel') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($data, null, 'A2');

            foreach (range('A', 'F') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'ventes_export_') . '.xlsx';
            $writer->save($tempFile);

            $response = new BinaryFileResponse($tempFile);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename . '.xlsx'
            );
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->deleteFileAfterSend(true);

            return $response;
        } else {
            $response = new StreamedResponse();
            $response->setCallback(function () use ($headers, $data) {
                $handle = fopen('php://output', 'w+');

                fputcsv($handle, $headers, ';');
                foreach ($data as $row) {
                    fputcsv($handle, $row, ';');
                }

                fclose($handle);
            });

            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');

            return $response;
        }
    }

    #[Route('/commande/{id}/update-statut', name: 'app_gestion_meubles_commande_update_statut', methods: ['POST'])]
    public function updateStatutCommande(Request $request, int $id): JsonResponse
    {
        $this->logger->info('Tentative de mise à jour du statut de la commande ID: ' . $id);

        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            $this->logger->warning('Utilisateur non connecté pour mise à jour du statut de la commande ID: ' . $id);
            return new JsonResponse(['error' => 'Vous devez être connecté pour modifier le statut d\'une commande.'], 403);
        }

        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            $this->logger->warning('Commande non trouvée: ID ' . $id);
            return new JsonResponse(['error' => 'Commande non trouvée.'], 404);
        }

        $nouveauStatut = $request->request->get('statut');
        if (!$nouveauStatut) {
            $this->logger->warning('Statut non fourni pour la commande ID: ' . $id);
            return new JsonResponse(['error' => 'Le statut est requis.'], 400);
        }

        try {
            $success = $this->commandeRepository->updateStatutCommande($id, $nouveauStatut, $utilisateur);
            if (!$success) {
                $this->logger->warning('Échec de la mise à jour du statut: commande ID ' . $id);
                return new JsonResponse(['error' => 'Impossible de mettre à jour le statut de la commande.'], 400);
            }

            $this->logger->info('Statut de la commande mis à jour avec succès: ID ' . $id . ' - Nouveau statut: ' . $nouveauStatut);
            return new JsonResponse(['success' => 'Statut de la commande mis à jour avec succès.']);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du statut de la commande ID: ' . $id . ' - ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur lors de la mise à jour du statut : ' . $e->getMessage()], 500);
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
    
        $statut = $request->query->get('statut', '');
        $periode = $request->query->get('periode', 'all');
    
        $nombreMeubles = $meubleRepository->count([]);
        $chiffreAffaires = $commandeRepository->getChiffreAffairesTotal($statut, $periode);
        $topVendeur = $commandeRepository->getTopVendeur($periode);
        $commandesParStatut = $commandeRepository->getCommandesParStatut($periode);
    
        $caParMoisData = $commandeRepository->getChiffreAffairesParMois($periode);
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
            'caParMoisData' => $caParMoisData,
            'calendarEvents' => $calendarEvents,
            'filtreStatut' => $statut,
            'filtrePeriode' => $periode,
        ]);
    }

    #[Route('/admin/export-stats-pdf', name: 'app_gestion_meubles_export_stats_pdf')]
    public function exportStatsPdf(
        Request $request,
        MeubleRepository $meubleRepository,
        CommandeRepository $commandeRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $statut = $request->query->get('statut', '');
        $periode = $request->query->get('periode', 'all');

        $nombreMeubles = $meubleRepository->count([]);
        $chiffreAffaires = $commandeRepository->getChiffreAffairesTotal($statut, $periode);
        $topVendeur = $commandeRepository->getTopVendeur($periode);
        $commandesParStatut = $commandeRepository->getCommandesParStatut($periode);
        $caParMoisData = $commandeRepository->getChiffreAffairesParMois($periode);
        $ventesParJour = $commandeRepository->getVentesParJour($periode);

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        $logoBase64 = null;
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
        }

        $html = $this->renderView('gestion_meubles/meuble/statistiques_pdf.html.twig', [
            'nombreMeubles' => $nombreMeubles,
            'chiffreAffaires' => $chiffreAffaires,
            'topVendeur' => $topVendeur,
            'commandesParStatut' => $commandesParStatut,
            'caParMoisData' => $caParMoisData,
            'ventesParJour' => $ventesParJour,
            'filtreStatut' => $statut,
            'filtrePeriode' => $periode,
            'logo' => $logoBase64,
        ]);

        $pdfContent = $this->pdf->getOutputFromHtml($html);

        $response = new Response($pdfContent);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('statistiques_%s.pdf', date('Y-m-d'))
        );
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
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

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        $logoBase64 = null;
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
        }

        $html = $this->renderView('gestion_meubles/commande/bon_commande_pdf.html.twig', [
            'commande' => $commande,
            'logo' => $logoBase64,
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

    private function sendCancellationEmailToBuyer(Commande $commande, Utilisateur $utilisateur, string $raisonAnnulation): void
    {
        try {
            $email = (new Email())
                ->from('naweldorrije789@gmail.com')
                ->to($utilisateur->getEmail())
                ->subject('Confirmation de l\'annulation de votre commande')
                ->html($this->renderView('emails/annulation_acheteur.html.twig', [
                    'commande' => $commande,
                    'nom' => $utilisateur->getNom(),
                    'prenom' => $utilisateur->getPrenom(),
                    'raison' => $raisonAnnulation,
                ]));

            $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
            if (file_exists($logoPath)) {
                $email->embed(fopen($logoPath, 'r'), 'logo.png', 'image/png');
                $this->logger->info('Logo joint à l\'email d\'annulation pour l\'acheteur : ' . $utilisateur->getEmail());
            } else {
                $this->logger->error('Fichier logo introuvable à : ' . $logoPath);
            }

            $this->mailer->send($email);
            $this->logger->info('Email d\'annulation envoyé à ' . $utilisateur->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email d\'annulation à ' . $utilisateur->getEmail() . ' : ' . $e->getMessage());
        }
    }

    private function sendCancellationEmailsToSellers(Commande $commande, string $raisonAnnulation): void
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

            try {
                $email = (new Email())
                    ->from('naweldorrije789@gmail.com')
                    ->to($vendeur->getEmail())
                    ->subject('Annulation d\'une commande pour vos articles')
                    ->html($this->renderView('emails/annulation_vendeur.html.twig', [
                        'commande' => $commande,
                        'articles' => $articles,
                        'nom' => $vendeur->getNom(),
                        'prenom' => $vendeur->getPrenom(),
                        'raison' => $raisonAnnulation,
                    ]));

                $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
                if (file_exists($logoPath)) {
                    $email->embed(fopen($logoPath, 'r'), 'logo.png', 'image/png');
                    $this->logger->info('Logo joint à l\'email d\'annulation pour le vendeur : ' . $vendeur->getEmail());
                } else {
                    $this->logger->error('Fichier logo introuvable à : ' . $logoPath);
                }

                $this->mailer->send($email);
                $this->logger->info('Email d\'annulation envoyé à ' . $vendeur->getEmail());
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi de l\'email d\'annulation à ' . $vendeur->getEmail() . ' : ' . $e->getMessage());
            }
        }
    }
}