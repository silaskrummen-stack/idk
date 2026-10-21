<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Eingeloggt, aber mit dem falschen Benutzer (z. B. lan_admin aus der LAN-Night, der das Portfolio nicht sehen darf):
 * statt "Access Denied" zum Portfolio-Login schicken und danach zurück zur gewünschten Seite.
 */
class PortfolioAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    use TargetPathTrait;

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ($request->isMethod('GET') && $request->hasSession()) {
            $this->saveTargetPath($request->getSession(), 'main', $request->getUri());
        }

        return new RedirectResponse($this->urls->generate('portfolio_login'));
    }
}
