<?php

namespace App\Domain\ProjectDashboard\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LanPartyProjectController extends AbstractProjectPageController
{
    #[Route('/projects/lan-party', name: 'project_lan_party')]
    public function index(): Response
    {
        return $this->renderProject('project_lan_party', 'projects/lan-party.html.twig');
    }
}
