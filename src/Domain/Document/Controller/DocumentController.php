<?php

namespace App\Domain\Document\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocumentController  extends AbstractController
{
    #[Route('/documents', name: 'documents')]
    public function index(): Response
    {
        $documents = require __DIR__ . '/../lib/documents.php';

        return $this->render('documents/documents.html.twig', [
            'documents' => $documents,
        ]);
    }
}
