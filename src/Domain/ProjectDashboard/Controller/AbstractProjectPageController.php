<?php

namespace App\Domain\ProjectDashboard\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gemeinsame Basis für die Projekte mit eigener Seite (LAN-Party, CIV-Abend, krummen.de).
 * Titel, Datum, Kategorie usw. kommen weiterhin aus lib/test.php, damit Karte und Seite gleich bleiben.
 */
abstract class AbstractProjectPageController extends AbstractController
{
    protected function renderProject(string $route, string $template, array $parameters = []): Response
    {
        $pages = array_values(array_filter(
            require __DIR__ . '/../lib/test.php',
            fn (array $project) => isset($project['route'])
        ));

        $index = array_search($route, array_column($pages, 'route'), true);

        if ($index === false) {
            throw $this->createNotFoundException(sprintf('Kein Projekt mit der Route "%s" in lib/test.php.', $route));
        }

        return $this->render($template, $parameters + [
            'project' => $pages[$index],
            // Unten auf der Seite geht es reihum zum nächsten großen Projekt
            'next_project' => $pages[($index + 1) % count($pages)],
        ]);
    }
}
