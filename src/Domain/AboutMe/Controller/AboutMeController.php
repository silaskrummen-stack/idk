<?php

namespace App\Domain\AboutMe\Controller;

use App\Domain\AboutMe\AboutProfile;
use App\Domain\ProjectDashboard\ProjectCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AboutMeController  extends AbstractController
{
    #[Route('/about_me', name: 'about_me')]
    public function index(ProjectCatalog $catalog, AboutProfile $profile): Response
    {
        return $this->render('about-me/about-me.html.twig', [
            // Titel → Link, für die aufklappbaren Kompetenzen und Stärken
            'project_urls' => $catalog->urls(),
            'profile' => $profile->all(),
        ]);
    }
}
