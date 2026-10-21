<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Öffentliche Startseite von silas-krummen.de.
 * Verrät absichtlich nicht, was sonst auf der Domain läuft (Portfolio, CIV-Abend, LAN-Party …) –
 * wer etwas Bestimmtes sehen soll, bekommt einen direkten Link.
 */
class LandingController extends AbstractController
{
    #[Route('/', name: 'landing', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('landing/index.html.twig');
    }
}
