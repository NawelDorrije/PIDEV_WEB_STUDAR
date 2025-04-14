<?php
namespace App\Controller\Admin;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get filter parameters from request
        $roleFilter = $request->query->get('role');
        $blockedFilter = $request->query->get('blocked');

        // Create query builder
        $queryBuilder = $utilisateurRepository->createQueryBuilder('u');

        // Apply filters
        if ($roleFilter) {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', $roleFilter);
        }

        if ($blockedFilter !== null) {
            $queryBuilder->andWhere('u.blocked = :blocked')
                ->setParameter('blocked', (bool)$blockedFilter);
        }

        // Order by name by default
        $queryBuilder->orderBy('u.nom', 'ASC');

        // Paginate the query
        $utilisateurs = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin/dashboard.html.twig', [
            'utilisateur' => $utilisateurs,
            'roleFilter' => $roleFilter,
            'blockedFilter' => $blockedFilter,
        ]);
    }

    #[Route('/statistique', name: 'app_admin_statistique')]
    public function statistique(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // This now just renders the template without data
        return $this->render('admin/statistique.html.twig');
    }

    #[Route('/api/user-stats', name: 'app_admin_api_user_stats')]
    public function getUserStats(UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get user count by role
        $userStats = $utilisateurRepository->getUserCountByRole();

        // Prepare data for the chart
        $labels = [];
        $data = [];
        $colors = ['#f35525', '#3f51b5', '#4caf50', '#ffc107', '#9c27b0'];

        foreach ($userStats as $stat) {
            $labels[] = $stat['role']->value; // Get the enum value
            $data[] = $stat['count'];
        }

        return $this->json([
            'labels' => $labels,
            'data' => $data,
            'colors' => array_slice($colors, 0, count($labels))
        ]);
    }
    #[Route('/user/{id}/toggle-block', name: 'admin_toggle_block', methods: ['POST'])]
public function toggleBlock($id, UtilisateurRepository $utilisateurRepository, EntityManagerInterface $em): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');
    
    $user = $utilisateurRepository->find($id);
    if (!$user) {
        return $this->json(['success' => false, 'message' => 'User not found']);
    }

    $user->setBlocked(!$user->isBlocked());
    $em->flush();

    return $this->json([
        'success' => true,
        'blocked' => $user->isBlocked()
    ]);
}
}