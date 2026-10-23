<?php

namespace App\Domain\Document\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class DocumentController extends AbstractController
{
    public function __construct(
        // Liegt bewusst außerhalb von public/: nginx würde Dateien dort ohne Login ausliefern
        #[Autowire('%kernel.project_dir%/documents')] private readonly string $dir,
    ) {
    }

    #[Route('/documents', name: 'documents')]
    public function index(): Response
    {
        return $this->render('documents/documents.html.twig', [
            'documents' => $this->documents(),
        ]);
    }

    /** Liefert ein PDF nur hinter dem Portfolio-Login aus (access_control ^/). */
    #[Route('/documents/{file}', name: 'document_file', requirements: ['file' => '[a-z0-9-]+\.pdf'], methods: ['GET'])]
    public function file(string $file): BinaryFileResponse
    {
        // Nur Dateien aus der Liste, kein freier Zugriff auf den Ordner
        if (!in_array($file, array_column($this->documents(), 'file'), true) || !is_file($this->dir . '/' . $file)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($this->dir . '/' . $file);
        // Typ selbst setzen (symfony/mime ist nicht installiert)
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');
        // inline, damit die Vorschau im iframe funktioniert; der Download-Button nutzt das download-Attribut
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file);

        return $response;
    }

    private function documents(): array
    {
        return require __DIR__ . '/../lib/documents.php';
    }
}
