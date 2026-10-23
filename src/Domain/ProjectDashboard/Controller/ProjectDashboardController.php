<?php

namespace App\Domain\ProjectDashboard\Controller;

use App\Domain\ProjectDashboard\ProjectCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectDashboardController extends AbstractController
{
    #[Route('/projects', name: 'project_dashboard')]
    public function index(ProjectCatalog $catalog): Response
    {
        return $this->render('project-dashboard/project-dashboard.html.twig', [
            'projects' => $catalog->all(),
        ]);
    }
    /*
    docker compose -f compose.yml -f compose.prod.yml up -d
    */
}
