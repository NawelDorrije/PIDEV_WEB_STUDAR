<?php

namespace App\Controller;

use App\Entity\Report;
use App\Entity\Utilisateur;
use App\Entity\Message;
use App\Repository\ReportRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\MessageRepository;
use App\Service\ChatbotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/report')]
#[IsGranted('ROLE_ADMIN')]
class ReportController extends AbstractController
{
    #[Route('/', name: 'admin_report_index', methods: ['GET'])]
    public function index(Request $request, ReportRepository $reportRepository, UtilisateurRepository $utilisateurRepository): Response
    {
        $search = $request->query->get('searchReport', '');
        $userCin = $request->query->get('user_filter', '');
        $statut = $request->query->get('statut_filter', '');
        $date = $request->query->get('date_filter', '');

        $reports = $reportRepository->findByFilters($search, $userCin, $statut, $date);
        $users = $utilisateurRepository->findAll();

        return $this->render('admin/report/index.html.twig', [
            'reports' => $reports,
            'users' => $users,
            'search' => $search,
            'selected_user' => $userCin,
            'selected_statut' => $statut,
            'selected_date' => $date,
        ]);
    }

    #[Route('/{id}/analyze', name: 'admin_report_analyze', methods: ['POST'])]
    public function adminReportAnalyze(Request $request, Report $report, ChatbotService $chatbotService, EntityManagerInterface $entityManager, ReportRepository $reportRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('analyze' . $report->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], 403);
        }

        if ($report->isResolved()) {
            return new JsonResponse(['error' => 'Ce signalement est déjà résolu.'], 400);
        }

        $messageContent = $report->getMessage() ? $report->getMessage()->getContent() : null;
        $reason = $report->getReason();
        $result = $chatbotService->analyzeReport($reason, $messageContent);

        // Store the analysis result and timestamp
        $report->setIsLegitimate($result['isLegitimate']);
        $report->setAnalyzedAt(new \DateTime());
        $entityManager->persist($report);
        $entityManager->flush();

        // Check for 3 legitimate reports against the message sender
        $senderCin = null;
        $messageId = null;
        if ($report->getMessage() && $report->getMessage()->getSenderCin()) {
            $sender = $report->getMessage()->getSenderCin();
            $senderCin = $sender->getCin();
            $messageId = $report->getMessage()->getId();

            if ($result['isLegitimate']) {
                $legitimateReportCount = $reportRepository->countLegitimateReportsByUser($sender);

                if ($legitimateReportCount >= 3 && !$sender->isBlocked()) {
                    $sender->setBlocked(true);
                    $entityManager->persist($sender);
                    $entityManager->flush();
                    $this->addFlash('success', 'Utilisateur ' . $sender->getCin() . ' bloqué automatiquement (3 signalements légitimes).');
                }
            }
        }

        return new JsonResponse([
            'reportId' => $report->getId(),
            'isLegitimate' => $result['isLegitimate'],
            'reason' => $result['reason'],
            'message' => $result['message'],
            'reportReason' => $reason,
            'senderCin' => $senderCin,
            'messageId' => $messageId,
        ]);
    }

    #[Route('/{id}/resolve', name: 'admin_report_resolve', methods: ['POST'])]
    public function resolve(Request $request, Report $report, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('resolve' . $report->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_report_index');
        }

        if ($report->isResolved()) {
            $this->addFlash('error', 'Ce signalement est déjà résolu.');
            return $this->redirectToRoute('admin_report_index');
        }

        $report->setIsResolved(true);
        $entityManager->persist($report);
        $entityManager->flush();

        $this->addFlash('success', 'Signalement résolu avec succès.');
        return $this->redirectToRoute('admin_report_index');
    }

    #[Route('/block/{cin}', name: 'admin_user_block', methods: ['POST'])]
    public function blockUser(Request $request, string $cin, UtilisateurRepository $utilisateurRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('block' . $cin, $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], 403);
        }

        $user = $utilisateurRepository->findOneBy(['cin' => $cin]);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé.'], 404);
        }

        if ($user->isBlocked()) {
            return new JsonResponse(['error' => 'Cet utilisateur est déjà bloqué.'], 400);
        }

        $user->setBlocked(true);
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Utilisateur bloqué avec succès.']);
    }

    #[Route('/message/{id}/delete', name: 'admin_message_delete', methods: ['POST'])]
    public function deleteMessage(Request $request, Message $message, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_message' . $message->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide.'], 403);
        }

        $entityManager->remove($message);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Message supprimé avec succès.']);
    }
}