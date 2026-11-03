<?php

namespace App\Controller;

use App\Ai\PortfolioAssistant;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Schnittstelle für das Chatfenster. Liegt unter /portfolio/… und ist damit automatisch hinter dem Portfolio-Login
 * (siehe access_control in security.yaml) – nur wer das Passwort aus dem Lebenslauf hat, kann Fragen stellen.
 */
final class ChatController extends AbstractController
{
    /** Einfache Bremse pro Login-Sitzung, damit niemand die API-Kosten in die Höhe treibt */
    private const MAX_QUESTIONS_PER_HOUR = 30;

    public function __construct(
        private readonly PortfolioAssistant $assistant,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/portfolio/chat', name: 'portfolio_chat', methods: ['POST'])]
    public function ask(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('portfolio_chat', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->error('Sitzung abgelaufen – bitte lade die Seite neu.', 403);
        }

        $data = json_decode($request->getContent(), true);
        $question = trim((string) ($data['question'] ?? ''));
        $history = \is_array($data['history'] ?? null) ? $data['history'] : [];

        if ('' === $question) {
            return $this->error('Bitte stelle eine Frage.', 400);
        }
        if (mb_strlen($question) > PortfolioAssistant::MAX_QUESTION_LENGTH) {
            return $this->error(\sprintf('Bitte fasse dich etwas kürzer (höchstens %d Zeichen).', PortfolioAssistant::MAX_QUESTION_LENGTH), 400);
        }
        if (!$this->allowRequest($request)) {
            return $this->error('Du hast in der letzten Stunde schon viele Fragen gestellt. Schreib Silas gern direkt eine E-Mail!', 429);
        }

        // Antwort wird gestreamt: Der Browser zeigt jedes Stück an, sobald es ankommt.
        // Fehler VOR dem Streaming kommen oben als JSON mit Statuscode, Fehler WÄHREND des Streamings
        // als freundlicher Text am Ende der Antwort (der Statuscode ist dann schon gesendet).
        $response = new StreamedResponse(function () use ($question, $history): void {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $sentSomething = false;
            try {
                foreach ($this->assistant->stream($question, $history) as $chunk) {
                    echo $chunk;
                    flush();
                    $sentSomething = $sentSomething || '' !== $chunk;
                }
            } catch (\Throwable $e) {
                // Details nur ins Log, nie an den Browser (könnten Interna der API enthalten)
                $this->logger->error('Portfolio-Chat fehlgeschlagen: '.$e->getMessage(), ['exception' => $e]);
                echo ($sentSomething ? "\n\n" : '').'Der Assistent ist gerade nicht erreichbar. Versuch es bitte gleich nochmal.';
                flush();

                return;
            }

            if (!$sentSomething) {
                echo 'Dazu kann ich leider nichts sagen.';
            }
        });

        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-cache');
        // nginx soll die Antwort nicht puffern, sonst kommt sie doch erst am Ende als Block an
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function allowRequest(Request $request): bool
    {
        $session = $request->getSession();
        $now = time();
        $timestamps = array_filter(
            (array) $session->get('portfolio_chat_requests', []),
            static fn (int $t) => $t > $now - 3600,
        );

        if (\count($timestamps) >= self::MAX_QUESTIONS_PER_HOUR) {
            return false;
        }

        $timestamps[] = $now;
        $session->set('portfolio_chat_requests', array_values($timestamps));

        return true;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
