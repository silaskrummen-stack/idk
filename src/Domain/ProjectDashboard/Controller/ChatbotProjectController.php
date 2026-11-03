<?php

namespace App\Domain\ProjectDashboard\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChatbotProjectController extends AbstractProjectPageController
{
    #[Route('/projects/chatbot', name: 'project_chatbot')]
    public function index(): Response
    {
        return $this->renderProject('project_chatbot', 'projects/chatbot.html.twig');
    }
}
