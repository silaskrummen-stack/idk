<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Öffentliche Startseite von silas-krummen.de.
 * Öffentlich erreichbar, verlinkt auf Portfolio, LAN-Night und CIV-Abend – die schützen sich selbst per Login.
 */
class LandingController extends AbstractController
{
    #[Route('/', name: 'landing', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('landing/index.html.twig');
    }
}
