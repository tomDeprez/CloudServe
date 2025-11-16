<?php

namespace App\Controller;

use App\Repository\FileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private FileRepository $fileRepository,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('app_login_page');
    }

    #[Route('/login-page', name: 'app_login_page')]
    public function loginPage(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('auth/login.html.twig');
    }

    #[Route('/register-page', name: 'app_register_page')]
    public function registerPage(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(): Response
    {
        // Pas de vérification d'authentification ici
        // L'authentification sera vérifiée côté JavaScript
        // Données par défaut pour le rendu initial
        return $this->render('dashboard/index.html.twig', [
            'user' => null,
            'files' => [],
            'quotaPercent' => 0,
        ]);
    }

    #[Route('/logout-action', name: 'app_logout_action')]
    public function logout(): Response
    {
        // Cette méthode ne sera jamais appelée car Symfony intercepte la requête
        return $this->redirectToRoute('app_login_page');
    }

    #[Route('/viewer/{id}', name: 'app_viewer')]
    public function viewer(int $id): Response
    {
        // Authentification vérifiée côté JavaScript
        return $this->render('viewer/index.html.twig', [
            'fileId' => $id,
        ]);
    }
}
