<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Command;

use Freema\N8nBundle\Service\RequestTracker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'n8n:cleanup', description: 'Cleanup expired N8n requests')]
final class CleanupCommand extends Command
{
    public function __construct(
        private readonly RequestTracker $requestTracker,
        private readonly int $maxAgeSeconds,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expiredCount = $this->requestTracker->clearExpiredRequests($this->maxAgeSeconds);

        if ($expiredCount > 0) {
            $io->success(\sprintf('Cleaned up %d expired N8n requests', $expiredCount));
        } else {
            $io->info('No expired N8n requests found');
        }

        return Command::SUCCESS;
    }
}
