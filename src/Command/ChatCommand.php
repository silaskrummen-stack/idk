<?php

namespace App\Command;

use App\Ai\PortfolioAssistant;
use App\Ai\PortfolioKnowledge;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:chat', description: 'Portfolio-Assistent im Terminal testen (exit zum Beenden)')]
final class ChatCommand extends Command
{
    public function __construct(
        private readonly PortfolioAssistant $assistant,
        private readonly PortfolioKnowledge $knowledge,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('prompt', null, InputOption::VALUE_NONE, 'Nur den Systemprompt anzeigen, nichts an das Modell schicken');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $prompt = $this->knowledge->systemPrompt();

        if ($input->getOption('prompt')) {
            $io->writeln($prompt);
            $io->note(\sprintf('%d Zeichen, grob %d Tokens (Faustregel: 1 Token ≈ 4 Zeichen).', mb_strlen($prompt), (int) ceil(mb_strlen($prompt) / 4)));

            return Command::SUCCESS;
        }

        $io->title('Portfolio-Assistent');
        $io->text(\sprintf('Systemprompt: ca. %d Tokens. Tippe eine Frage, „exit“ beendet.', (int) ceil(mb_strlen($prompt) / 4)));

        $history = [];
        while (true) {
            $question = trim((string) $io->ask('Du'));
            if ('' === $question) {
                continue;
            }
            if (\in_array(strtolower($question), ['exit', 'quit', 'q'], true)) {
                return Command::SUCCESS;
            }

            $start = microtime(true);
            $firstChunkAt = null;
            $answer = '';
            $io->write('<info>Assistent:</info> ');
            try {
                foreach ($this->assistant->stream($question, $history) as $chunk) {
                    $firstChunkAt ??= microtime(true);
                    $io->write($chunk);
                    $answer .= $chunk;
                }
            } catch (\Throwable $e) {
                $io->newLine();
                $io->error($e->getMessage());
                continue;
            }

            $io->newLine();
            $io->writeln(\sprintf(
                '<comment>(erstes Wort nach %.1f s, fertig nach %.1f s)</comment>',
                ($firstChunkAt ?? microtime(true)) - $start,
                microtime(true) - $start,
            ));
            $io->newLine();

            $history[] = ['role' => 'user', 'content' => $question];
            $history[] = ['role' => 'assistant', 'content' => $answer];
        }
    }
}
