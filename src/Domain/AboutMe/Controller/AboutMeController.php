<?php

namespace App\Domain\AboutMe\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AboutMeController  extends AbstractController
{
    #[Route('/about_me', name: 'about_me')]
    public function index(): Response
    {
        return $this->render('about-me/about-me.html.twig');
    }
}
