<?php

namespace App\Domain\Civ\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Brücke zwischen Portfolio-Login und dem CIV-Abend (eigener Container hinter nginx).
 *
 *  - /portfolio/civ/display  → Bildschirm   (normales Portfolio-Passwort reicht)
 *  - /portfolio/civ/admin    → Spielleitung (eigener Benutzer "civ_admin")
 *  - /portfolio/civ-auth/... → wird von nginx (auth_request) bei jedem Aufruf der CIV-Subdomains gefragt
 */
class CivController extends AbstractController
{
    use TargetPathTrait;

    private const ROLES = [
        'display' => 'ROLE_PORTFOLIO_VIEWER',
        'admin'   => 'ROLE_CIV_ADMIN',
    ];

    #[Route('/portfolio/civ/{target}', name: 'civ_open', requirements: ['target' => 'display|admin'], methods: ['GET'])]
    public function open(string $target, Request $request): Response
    {
        if (!$this->isGranted(self::ROLES[$target])) {
            // Als Gast eingeloggt, aber für die Spielleitung braucht es den Admin-Benutzer:
            // Ziel merken und zum Login mit dem Admin-Benutzer schicken.
            $this->saveTargetPath($request->getSession(), 'main', $request->getUri());

            return $this->redirectToRoute('portfolio_login', ['as' => 'civ']);
        }

        $url = $this->getParameter($target === 'admin' ? 'civ.admin_url' : 'civ.display_url');

        return new RedirectResponse($url);
    }

    #[Route('/portfolio/civ-auth/{target}', name: 'civ_auth', requirements: ['target' => 'display|admin'], methods: ['GET'])]
    public function auth(string $target): Response
    {
        $ok = $this->isGranted(self::ROLES[$target]);

        $response = new Response('', $ok ? Response::HTTP_NO_CONTENT : Response::HTTP_UNAUTHORIZED);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
