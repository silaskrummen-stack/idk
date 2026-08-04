<?php

namespace App\Domain\ProjectDashboard\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectDashboardController extends AbstractController
{
    #[Route('/projects', name: 'project_dashboard')]
    public function index(): Response
    {
        $projects = require __DIR__ . '/../lib/test.php';

        return $this->render('project-dashboard/project-dashboard.html.twig', [
            'projects' => $projects,
        ]);
    }
}
