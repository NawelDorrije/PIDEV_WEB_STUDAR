<?php

namespace App\Controller\GestionReservation;

use App\Entity\ReservationLogement;
use App\Form\ReservationLogementType;
use App\Repository\ReservationLogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TCPDF;
use TCPDF2DBarcode;

#[Route('/reservation/logement')]
final class ReservationLogementController extends AbstractController
{
    #[Route('/', name: 'app_reservation_logement_index', methods: ['GET'])]
    public function index(Request $request, ReservationLogementRepository $reservationLogementRepository): Response
    {
        $status = $request->query->get('status');
        
        if ($status) {
            $reservations = $reservationLogementRepository->findBy(['status' => $status]);
        } else {
            $reservations = $reservationLogementRepository->findAll();
        }
        
        return $this->render('reservation_logement/index.html.twig', [
            'reservation_logements' => $reservations,
            'current_status' => $status
        ]);
    }

    #[Route('/new', name: 'app_reservation_logement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservationLogement = new ReservationLogement();
        $form = $this->createForm(ReservationLogementType::class, $reservationLogement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservationLogement);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_logement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation_logement/new.html.twig', [
            'reservation_logement' => $reservationLogement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_logement_show', methods: ['GET'])]
    public function show(ReservationLogement $reservationLogement): Response
    {
        return $this->render('reservation_logement/show.html.twig', [
            'reservation_logement' => $reservationLogement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_logement_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, ReservationLogement $reservationLogement, EntityManagerInterface $entityManager): Response
{
    // Prevent editing if status is confirmed or refused
    if (!$reservationLogement->isModifiable()) {
        $this->addFlash('error', 'Les réservations confirmées ou refusées ne peuvent pas être modifiées');
        return $this->redirectToRoute('app_reservation_logement_show_PROP', ['id' => $reservationLogement->getId()]);
    }

    $form = $this->createForm(ReservationLogementType::class, $reservationLogement);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();

        $this->addFlash('success', 'La réservation a été modifiée avec succès');
        return $this->redirectToRoute('app_reservation_logement_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->render('reservation_logement/edit.html.twig', [
        'reservation_logement' => $reservationLogement,
        'form' => $form,
    ]);
}
    #[Route('/{id}', name: 'app_reservation_logement_delete', methods: ['POST'])]
    public function delete(Request $request, ReservationLogement $reservationLogement, EntityManagerInterface $entityManager): Response
    {
        if (!$reservationLogement->isDeletable()) {
            $this->addFlash('error', 'Cette réservation ne peut pas être supprimée');
            return $this->redirectToRoute('app_reservation_logement_show', ['id' => $reservationLogement->getId()]);
        }

        if ($this->isCsrfTokenValid('delete'.$reservationLogement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservationLogement);
            $entityManager->flush();
            $this->addFlash('success', 'La réservation a été supprimée avec succès');
        }

        return $this->redirectToRoute('app_reservation_logement_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/generate-pdf', name: 'app_reservation_logement_generate_pdf', methods: ['GET'])]
public function generatePdf(ReservationLogement $reservation): Response
{
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document metadata
    $pdf->SetCreator('Studar');
    $pdf->SetAuthor('Studar');
    $pdf->SetTitle('Contrat de Réservation #'.$reservation->getId());
    
    // Add a page
    $pdf->AddPage();
    
    // Add logo (replace with your actual logo path)
    $logoPath = $this->getParameter('kernel.project_dir').'/public/images/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 15, 15, 30, 0, 'PNG');
    }
    
    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 20, 'CONTRAT DE LOCATION', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    
    // Get owner and tenant names
    $proprietaireName = $reservation->getProprietaire() ? 
        $reservation->getProprietaire()->getNom().' '.$reservation->getProprietaire()->getPrenom() : 
        '________________';
    
    $locataireName = $reservation->getEtudiant() ? 
        $reservation->getEtudiant()->getNom().' '.$reservation->getEtudiant()->getPrenom() : 
        '________________';
    
    // Contract text with dynamic names
    $contractText = "Entre M./Mme ".$proprietaireName." (Propriétaire)\n"
        . "et M./Mme ".$locataireName." (Locataire), il est convenu ce qui suit :\n\n"
        . "Objet : Le propriétaire loue le bien au locataire.\n"
        . "Durée : La location est consentie pour une durée déterminée, renouvelable annuellement\nsans préavis de trois mois.\n"
        . "Loyer : Montant fixé, payable à l'avance.\n"
        . "Charges : Eau à la charge du propriétaire, sauf dépassement consommé par le locataire.\n"
        . "Entretien et travaux : Aucune modification sans accord du propriétaire, entretien\ndes réparations locatives à la charge du locataire.\n"
        . "Responsabilités : Le locataire ne peut tenir le propriétaire responsable des troubles\nou dommages causés par des tiers.\n"
        . "Infiltrations : Le propriétaire décline toute responsabilité en cas d'infiltrations d'eau.\n"
        . "État des lieux : Le locataire doit rendre le bien en bon état.\n"
        . "Interdictions : Pas de sous-location, interdiction d'accrocher du linge aux fenêtres,\nd'entreposer des matériaux dans les parties communes, d'avoir des animaux nuisibles, etc.\n"
        . "Sécurité : Le locataire doit assurer la garde de ses biens et respecter les règles de sécurité.\n"
        . "Produits dangereux : Interdiction d'introduire des substances inflammables ou explosives.\n"
        . "Résiliation : Défaut de paiement ou non-respect des clauses entraîne la résiliation après\nmise en demeure, avec expulsion immédiate.\n"
        . "Frais : À la charge du locataire.\n\n"
        . "La présente location est consentie pour la durée commençant le " . $reservation->getDateDebut()->format('d/m/Y') 
        . " et finissant le " . $reservation->getDateFin()->format('d/m/Y') 
        . " à propos le logement situé à " . $reservation->getIdLogement() . ".\n\n"
        . "Conditions générales:\n"
        . "- Paiement mensuel à l'avance\n"
        . "- Caution: 1 mois de loyer\n"
        . "- Durée minimum: 6 mois\n\n";
    
    // Add contract text with styling
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 6, $contractText, 0, 'L');
    
    // Signatures section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 10, 'Signature du Propriétaire', 0, 0, 'C');
    $pdf->Cell(10, 10, '', 0);
    $pdf->Cell(90, 10, 'Signature du Locataire', 0, 1, 'C');
    
    // Add signature lines
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(90, 20, str_pad('', 30, '_'), 0, 0, 'C');
    $pdf->Cell(10, 20, '', 0);
    $pdf->Cell(90, 20, str_pad('', 30, '_'), 0, 1, 'C');
    
    // Add date line
    $pdf->Cell(0, 10, 'Fait à Tunis, le '.date('d/m/Y'), 0, 1, 'C');
    
    // Add QR code (small format)
    $qrContent = 'logement:'.$reservation->getIdLogement();
    $style = [
        'border' => 0,
        'vpadding' => 'auto',
        'hpadding' => 'auto',
        'fgcolor' => [0,0,0],
        'bgcolor' => false,
        'module_width' => 1,
        'module_height' => 1
    ];
    $pdf->write2DBarcode($qrContent, 'QRCODE,L', 80, $pdf->GetY(), 50, 50, $style);
    $pdf->Cell(0, 5, 'Scannez ce code pour voir le logement concerné', 0, 1, 'C');
    
    // Output PDF
    $pdfContent = $pdf->Output('contrat_reservation_'.$reservation->getId().'.pdf', 'S');
    
    return new Response(
        $pdfContent,
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="contrat_reservation_'.$reservation->getId().'.pdf"'
        ]
    );
}

  // src/Controller/ReservationLogementController.php
// src/Controller/ReservationLogementController.php

#[Route('/statistics/owner', name: 'app_reservation_logement_statistics_owner')]
public function statisticsOwner(ReservationLogementRepository $repository): Response
{
    // Récupérer le CIN du propriétaire connecté (à adapter selon votre système d'authentification)
    $cinProprietaire = $this->getUser()->getCin(); // Adaptez cette ligne
    
    $stats = $repository->getMonthlyStatisticsForOwner($cinProprietaire);
    
    return $this->render('reservation_logement/statistics_owner.html.twig', [
        'stats' => $stats,
        'max' => !empty($stats) ? max(array_column($stats, 'count')) : 0
    ]);
}
}
