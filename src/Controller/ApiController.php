<?php
// src/Controller/ApiController.php
namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\LogementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
  /**
 * @Route("/api/proprietaires/{id}/logements", name="api_proprietaire_logements")
 */
public function getLogementsByProprietaire($id, LogementRepository $logementRepository): JsonResponse
{
    $logements = $logementRepository->findBy(['proprietaire' => $id]);

    $data = [];

    foreach ($logements as $logement) {
        $data[] = [
            'id' => $logement->getId(),
            'adresse' => $logement->getAdresse()
        ];
    }

    return new JsonResponse($data);
}

}