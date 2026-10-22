<?php

namespace App\Domain\ProjectDashboard\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class KrummenProjectController extends AbstractProjectPageController
{
    #[Route('/projects/krummen-de', name: 'project_krummen')]
    public function index(): Response
    {
        return $this->renderProject('project_krummen', 'projects/krummen.html.twig');
    }
}
