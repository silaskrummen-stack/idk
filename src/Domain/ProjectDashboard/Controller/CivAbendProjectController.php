<?php

namespace App\Domain\ProjectDashboard\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CivAbendProjectController extends AbstractProjectPageController
{
    #[Route('/projects/civ-abend', name: 'project_civ_abend')]
    public function index(): Response
    {
        return $this->renderProject('project_civ_abend', 'projects/civ-abend.html.twig');
    }
}
