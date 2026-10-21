<?php

namespace App\Domain\LanNight\Controller;

use App\Domain\LanNight\LanNightAwards;
use App\Domain\LanNight\LanNightGuess;
use App\Domain\LanNight\LanNightPrice;
use App\Domain\LanNight\LanNightQuiz;
use App\Domain\LanNight\LanNightStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * LAN-Night: zwei Teams spielen einen Abend lang um Punkte.
 *
 *  - /lan-night            → Bildschirm (öffentlich, zeigt nur den Spielstand)
 *  - /lan-night/admin      → Spielleitung (Benutzer "lan_admin", hat KEINEN Zugriff aufs Portfolio)
 *  - /lan-night/mitmachen  → Handy-Seite für Quiz und Awards (öffentlich, per QR-Code)
 *  - /lan-night/api/...    → Stand lesen (öffentlich, ohne Lösungen) / ändern (nur Spielleitung) / Quiz-Stimme (öffentlich)
 */
class LanNightController extends AbstractController
{
    use TargetPathTrait;

    private const ADMIN_ROLE = 'ROLE_LAN_ADMIN';

    public function __construct(private readonly LanNightStore $store)
    {
    }

    #[Route('/lan-night', name: 'lan_display', methods: ['GET'])]
    public function display(): Response
    {
        return $this->render('lan-night/display.html.twig');
    }

    #[Route('/lan-night/mitmachen', name: 'lan_phone', methods: ['GET'])]
    #[Route('/lan-night/quiz', name: 'lan_quiz', methods: ['GET'])]
    public function phone(): Response
    {
        return $this->render('lan-night/quiz.html.twig');
    }

    #[Route('/lan-night/admin', name: 'lan_admin', methods: ['GET'])]
    public function admin(Request $request): Response
    {
        if (!$this->isGranted(self::ADMIN_ROLE)) {
            $this->saveTargetPath($request->getSession(), 'main', $request->getUri());

            return $this->redirectToRoute('portfolio_login', ['as' => 'lan']);
        }

        return $this->render('lan-night/admin.html.twig');
    }

    #[Route('/lan-night/api/state', name: 'lan_state', methods: ['GET'])]
    public function state(Request $request): JsonResponse
    {
        if ($request->query->has('display')) {
            $this->store->pingDisplay();
        }

        $voter = $request->query->get('voter');
        // Admin-Ansicht nur, wenn die Spielleitung sie ausdrücklich anfragt – sonst bekäme der Fernseher
        // im selben Browser (eingeloggt) die Admin-Daten statt der Bildschirm-Daten
        $asAdmin = $request->query->has('admin') && $this->isGranted(self::ADMIN_ROLE);

        return $this->stateResponse($this->store->read(), is_string($voter) ? $voter : null, $asAdmin);
    }

    #[Route('/lan-night/api/action', name: 'lan_action', methods: ['POST'])]
    public function action(Request $request): JsonResponse
    {
        // Für alle außer der Spielleitung sieht es aus, als gäbe es das nicht
        if (!$this->isGranted(self::ADMIN_ROLE)) {
            return $this->json(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }
        // Nur JSON (kein Formular von fremden Seiten)
        if ($request->getContentTypeFormat() !== 'json') {
            return $this->json(['error' => 'JSON erwartet'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body) || !is_string($body['action'] ?? null)) {
            return $this->json(['error' => 'Ungültige Anfrage'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $state = $this->store->apply($body['action'], $body);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->stateResponse($state, asAdmin: true);
    }

    /** Quiz: ein Handy loggt die Antwort seines Teams ein – ohne Login, nur für die gerade laufende Frage. */
    #[Route('/lan-night/api/vote', name: 'lan_vote', methods: ['POST'])]
    public function vote(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if ($request->getContentTypeFormat() !== 'json' || !is_array($body)) {
            return $this->json(['error' => 'Ungültige Anfrage'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $state = $this->store->vote($body);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->stateResponse($state);
    }

    /** Guess the Game: ein Handy tippt für sein Team – ohne Login, nur für das gerade gezeigte Bild. */
    #[Route('/lan-night/api/guess', name: 'lan_guess', methods: ['POST'])]
    public function guess(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if ($request->getContentTypeFormat() !== 'json' || !is_array($body)) {
            return $this->json(['error' => 'Ungültige Anfrage'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $state = $this->store->guessSubmit($body);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->stateResponse($state);
    }

    /** Guess the Price: ein Handy tippt einen Preis für sein Team – ohne Login, nur für den aktuellen Gegenstand. */
    #[Route('/lan-night/api/price', name: 'lan_price', methods: ['POST'])]
    public function price(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if ($request->getContentTypeFormat() !== 'json' || !is_array($body)) {
            return $this->json(['error' => 'Ungültige Anfrage'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $state = $this->store->priceSubmit($body);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->stateResponse($state);
    }

    /** Bild für Guess the Price – erst, wenn der Gegenstand dran ist (die Spielleitung darf alle sehen). */
    #[Route('/lan-night/price-image/{item}', name: 'lan_price_image', requirements: ['item' => '\d+'], methods: ['GET'])]
    public function priceImage(int $item): Response
    {
        $state = $this->store->read();
        if (!$this->isGranted(self::ADMIN_ROLE) && !LanNightPrice::imageVisible($state['price'], $item)) {
            throw $this->createNotFoundException();
        }
        $file = $this->store->priceImage($item) ?? throw $this->createNotFoundException();

        return $this->imageResponse($file);
    }

    /** Bild für Guess the Game – nur Bilder, die schon gezeigt werden (die Spielleitung darf alle sehen). */
    #[Route('/lan-night/guess-image/{game}/{picture}', name: 'lan_guess_image', requirements: ['game' => '\d+', 'picture' => '\d+'], methods: ['GET'])]
    public function guessImage(int $game, int $picture): Response
    {
        $state = $this->store->read();
        if (!$this->isGranted(self::ADMIN_ROLE) && !LanNightGuess::imageVisible($state['guess'], $game, $picture)) {
            throw $this->createNotFoundException();
        }
        $file = $this->store->guessImages($game)[$picture] ?? null;
        if (!$file) {
            throw $this->createNotFoundException();
        }

        return $this->imageResponse($file);
    }

    private function imageResponse(string $file): BinaryFileResponse
    {
        $response = new BinaryFileResponse($file);
        // Typ selbst setzen (symfony/mime ist nicht installiert)
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'avif' => 'image/avif'];
        $response->headers->set('Content-Type', $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream');
        $response->headers->set('Cache-Control', 'private, max-age=600');

        return $response;
    }

    /** Award-Stimme vom Handy – ohne Login, nur solange die Abstimmung offen ist. */
    #[Route('/lan-night/api/award-vote', name: 'lan_award_vote', methods: ['POST'])]
    public function awardVote(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if ($request->getContentTypeFormat() !== 'json' || !is_array($body)) {
            return $this->json(['error' => 'Ungültige Anfrage'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $state = $this->store->awardVote($body);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->stateResponse($state, (string) $body['voter']);
    }

    /**
     * Admin-Ansicht (alles) nur für das Admin-Panel; Bildschirm und Handys bekommen immer die öffentliche
     * Ansicht ohne Lösungen, Zwischenstände und fremde Stimmen – auch wenn der Browser eingeloggt ist.
     */
    private function stateResponse(array $state, ?string $voter = null, bool $asAdmin = false): JsonResponse
    {
        if ($asAdmin) {
            $state['awards'] = LanNightAwards::adminView($state);
            $state['quiz'] = LanNightQuiz::adminView($state['quiz']);
            $state['guess'] = LanNightGuess::adminView($state['guess'], $this->store->guessCounts($state));
            $state['price'] = LanNightPrice::adminView($state['price'], $this->store->priceImages($state));
            $state['displaySeenAt'] = $this->store->displaySeenAt();
        } else {
            $state['awards'] = LanNightAwards::publicView($state, $voter);
            $state['quiz'] = LanNightQuiz::publicView($state['quiz']);
            $state['guess'] = LanNightGuess::publicView($state['guess'], $this->store->guessCounts($state));
            $state['price'] = LanNightPrice::publicView($state['price'], $this->store->priceImages($state));
        }
        $state['serverTime'] = microtime(true);

        return $this->json($state, headers: ['Cache-Control' => 'no-store']);
    }
}
