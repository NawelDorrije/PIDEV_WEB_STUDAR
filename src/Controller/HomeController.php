<?php

namespace App\Controller;

use App\Repository\GestionMeubles\MeubleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Utilisateur;

final class HomeController extends AbstractController
{
    private MeubleRepository $meubleRepository;

    public function __construct(MeubleRepository $meubleRepository)
    {
        $this->meubleRepository = $meubleRepository;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $utilisateur = $this->getUser();
        $cinAcheteur = null;
        $meubles = [];

        if ($utilisateur instanceof Utilisateur) {
            $cinAcheteur = $utilisateur->getCin();
            $meubles = $this->meubleRepository->findMeublesDisponiblesPourAcheteur($cinAcheteur);
        }

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'meubles' => $meubles,
            'cin_acheteur' => $cinAcheteur,
            'utilisateur' => $utilisateur,
        ]);
    }
}