<?php

namespace App\Controller\GestionReservation;

use App\Entity\ReservationLogement;
use App\Entity\Utilisateur;
use App\Form\ReservationLogementType;
use App\Repository\ReservationLogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TCPDF;
use TCPDF2DBarcode;

#[Route('/reservation/logement_PROP')]
final class ReservationLogementController_PROP extends AbstractController
{
    #[Route('/', name: 'app_reservation_logement_index_PROP', methods: ['GET'])]
    public function index(Request $request, ReservationLogementRepository $reservationLogementRepository): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $status = $request->query->get('status');
        
        $queryBuilder = $reservationLogementRepository->createQueryBuilder('r')
            ->where('r.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $utilisateur);
        
        if ($status) {
            $queryBuilder->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }
        
        $reservations = $queryBuilder->getQuery()->getResult();
        
        return $this->render('reservation_logement/index_PROP.html.twig', [
            'reservation_logements' => $reservations,
            'current_status' => $status
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_logement_show_PROP', methods: ['GET'])]
    public function show(ReservationLogement $reservationLogement): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationLogement->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à cette réservation.');
        }

        return $this->render('reservation_logement/show_PROP.html.twig', [
            'reservation_logement' => $reservationLogement,
        ]);
    }

    #[Route('/{id}/accept', name: 'app_reservationLogement_accept', methods: ['POST'])]
    public function accept(Request $request, ReservationLogement $reservationLogement, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationLogement->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accepter cette réservation.');
        }

        if ($this->isCsrfTokenValid('accept'.$reservationLogement->getId(), $request->request->get('_token'))) {
            $reservationLogement->setStatus('confirmée');
            $entityManager->flush();
        }
    
        return $this->redirectToRoute('app_reservation_logement_index_PROP');
    }
    
    #[Route('/{id}/reject', name: 'app_reservationLogement_reject', methods: ['POST'])]
    public function reject(Request $request, ReservationLogement $reservationLogement, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationLogement->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas rejeter cette réservation.');
        }

        if ($this->isCsrfTokenValid('reject'.$reservationLogement->getId(), $request->request->get('_token'))) {
            $reservationLogement->setStatus('refusée');
            $entityManager->flush();
        }
    
        return $this->redirectToRoute('app_reservation_logement_index_PROP');
    }

    #[Route('/{id}/generate-pdf', name: 'app_reservation_logement_generate_pdf', methods: ['GET'])]
    public function generatePdf(ReservationLogement $reservation): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservation->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce contrat.');
        }

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('Studar');
        $pdf->SetAuthor('Studar');
        $pdf->SetTitle('Contrat de Réservation #'.$reservation->getId());
        
        $pdf->AddPage();
        
        $logoPath = $this->getParameter('kernel.project_dir').'/public/images/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 15, 30, 0, 'PNG');
        }
        
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 20, 'CONTRAT DE LOCATION', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        
        $proprietaireName = $reservation->getProprietaire() ? 
            $reservation->getProprietaire()->getNom().' '.$reservation->getProprietaire()->getPrenom() : 
            '________________';
        
        $locataireName = $reservation->getEtudiant() ? 
            $reservation->getEtudiant()->getNom().' '.$reservation->getEtudiant()->getPrenom() : 
            '________________';
        
        $contractText = "Entre M./Mme ".$proprietaireName." (Propriétaire)"
            . " et M./Mme ".$locataireName." (Locataire), il est convenu ce qui suit :\n\n"
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
        
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 6, $contractText, 0, 'L');
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(90, 10, 'Signature du Propriétaire', 0, 0, 'C');
        $pdf->Cell(10, 10, '', 0);
        $pdf->Cell(90, 10, 'Signature du Locataire', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(90, 20, str_pad('', 30, '_'), 0, 0, 'C');
        $pdf->Cell(10, 20, '', 0);
        $pdf->Cell(90, 20, str_pad('', 30, '_'), 0, 1, 'C');
        
        $pdf->Cell(0, 10, 'Fait à Tunis, le '.date('d/m/Y'), 0, 1, 'C');
        
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
}