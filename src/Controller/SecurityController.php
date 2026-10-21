<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/portfolio/login', name: 'portfolio_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $params = [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ];

        // LAN-Night bekommt einen eigenen Login ohne Portfolio-Navigation – die Gäste sollen davon nichts sehen
        // Letzter Benutzer zählt nur nach einem Fehlversuch (dann kommt man ohne ?as= zurück) – sonst bekäme
        // jemand, der als lan_admin eingeloggt ist und ins Portfolio will, den LAN-Login statt des Portfolio-Logins
        if ($request->query->get('as') === 'lan' || ($params['error'] && $params['last_username'] === 'lan_admin')) {
            return $this->render('lan-night/login.html.twig', $params);
        }

        return $this->render('security/login.html.twig', $params);
    }

    #[Route('/portfolio/logout', name: 'portfolio_logout')]
    public function logout(): void
    {
        // intercepted by the firewall, this stays empty
    }
}
