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

        // Neueste zuerst: "09/2026" → 202609, damit sich die Daten als Zahl vergleichen lassen
        usort($projects, fn (array $a, array $b) => self::sortKey($b) <=> self::sortKey($a));

        return $this->render('project-dashboard/project-dashboard.html.twig', [
            'projects' => $projects,
        ]);
    }

    private static function sortKey(array $project): int
    {
        // Projekte ohne gültiges Datum (Format MM/YYYY) landen am Ende
        if (!preg_match('~^(\d{1,2})/(\d{4})$~', trim($project['date'] ?? ''), $m)) {
            return 0;
        }

        return (int) $m[2] * 100 + (int) $m[1];
    }
    /*
    docker compose -f compose.yml -f compose.prod.yml up -d
    */
}
