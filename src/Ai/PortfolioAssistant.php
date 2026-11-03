<?php

namespace App\Ai;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Beantwortet eine Besucherfrage. Das Modell selbst ist zustandslos: Der bisherige Gesprächsverlauf
 * wird bei jeder Frage mitgeschickt (vom Browser), auf dem Server wird nichts gespeichert.
 *
 * Reihenfolge der Nachrichten: erst der feste Systemprompt (cachebar), dann der Verlauf, ganz am Ende die neue Frage.
 */
final class PortfolioAssistant
{
    public const MAX_QUESTION_LENGTH = 500;
    private const MAX_HISTORY_MESSAGES = 10;
    private const MAX_HISTORY_MESSAGE_LENGTH = 2000;
    // Inklusive interner „Denk-Tokens“ von Reasoning-Modellen – deshalb großzügiger als die eigentliche Antwort.
    // Die Länge der sichtbaren Antwort steuern die Regeln im Prompt (2–6 Sätze).
    private const MAX_ANSWER_TOKENS = 2000;

    public function __construct(
        #[Autowire(service: 'ai.agent.portfolio')]
        private readonly AgentInterface $agent,
        private readonly PortfolioKnowledge $knowledge,
        // Reasoning-Modelle „denken“ standardmäßig lange nach. Für einen Chat reicht "low".
        // Leer lassen bei Modellen, die den Parameter nicht kennen.
        #[Autowire('%env(default::AZURE_AI_REASONING_EFFORT)%')]
        private readonly ?string $reasoningEffort = null,
    ) {
    }

    /**
     * Ganze Antwort auf einmal (z. B. für Tests).
     *
     * @param list<array{role?: string, content?: string}> $history bisherige Nachrichten, älteste zuerst
     */
    public function ask(string $question, array $history = []): string
    {
        $answer = $this->agent->call($this->messages($question, $history), $this->options())->getContent();

        return \is_string($answer) ? trim($answer) : '';
    }

    /**
     * Antwort Stück für Stück, sobald das Modell sie erzeugt (Streaming). Tool-Aufrufe (z. B. projekt_details)
     * erledigt Symfony AI dabei automatisch im Hintergrund, nach außen kommt nur Antworttext.
     *
     * @param list<array{role?: string, content?: string}> $history
     *
     * @return \Generator<int, string>
     */
    public function stream(string $question, array $history = []): \Generator
    {
        $execution = $this->agent->call($this->messages($question, $history), $this->options() + ['stream' => true]);

        foreach ($execution->asStream() as $delta) {
            if ($delta instanceof TextDelta) {
                yield $delta->getText();
            }
        }
    }

    /**
     * @param list<array{role?: string, content?: string}> $history
     */
    private function messages(string $question, array $history): MessageBag
    {
        $question = trim($question);
        if ('' === $question) {
            throw new \InvalidArgumentException('Die Frage ist leer.');
        }

        $messages = new MessageBag(Message::forSystem($this->knowledge->systemPrompt()));

        foreach (\array_slice($history, -self::MAX_HISTORY_MESSAGES) as $turn) {
            $content = mb_substr(trim((string) ($turn['content'] ?? '')), 0, self::MAX_HISTORY_MESSAGE_LENGTH);
            if ('' === $content) {
                continue;
            }
            $messages->add('assistant' === ($turn['role'] ?? null) ? Message::ofAssistant($content) : Message::ofUser($content));
        }

        $messages->add(Message::ofUser(mb_substr($question, 0, self::MAX_QUESTION_LENGTH)));

        return $messages;
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        // max_completion_tokens statt max_tokens: Neuere OpenAI-Modelle (GPT-5.x) lehnen max_tokens ab,
        // andere OpenAI-kompatible Anbieter verstehen max_completion_tokens ebenfalls.
        $options = ['max_completion_tokens' => self::MAX_ANSWER_TOKENS];
        if (null !== $this->reasoningEffort && '' !== $this->reasoningEffort) {
            $options['reasoning_effort'] = $this->reasoningEffort;
        }

        return $options;
    }
}
