<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Security\JwtTokenValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates a bot token with embedded Redmine credentials.
 *
 * Stateless architecture: the token contains all credentials,
 * no database storage required.
 */
#[AsCommand(
    name: 'app:create-bot',
    description: 'Create an admin bot JWT token with embedded Redmine credentials for n8n integration'
)]
class CreateBotCommand extends Command
{
    public function __construct(
        private readonly JwtTokenValidator $tokenValidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Bot user email (e.g., bot@admin.com)')
            ->addOption('redmine-url', null, InputOption::VALUE_REQUIRED, 'Redmine instance URL')
            ->addOption('redmine-api-key', null, InputOption::VALUE_REQUIRED, 'Redmine admin API key')
            ->addOption('jwt-expiry', null, InputOption::VALUE_OPTIONAL, 'JWT token expiry (e.g., "+1 year", "+30 days")', '+1 year')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email');
        $redmineUrl = $input->getOption('redmine-url');
        $redmineApiKey = $input->getOption('redmine-api-key');
        $jwtExpiry = $input->getOption('jwt-expiry');

        if (!$email || !$redmineUrl || !$redmineApiKey) {
            $io->error('All options are required: --email, --redmine-url, --redmine-api-key');

            return Command::FAILURE;
        }

        // Calculate expiry in seconds
        $expiresIn = (new \DateTimeImmutable($jwtExpiry))->getTimestamp() - time();

        // Generate long-lived JWT token with embedded credentials
        $token = $this->tokenValidator->createTokenWithCredentials(
            userId: $email,
            redmineUrl: rtrim($redmineUrl, '/'),
            redmineApiKey: $redmineApiKey,
            expiresIn: $expiresIn,
            extraClaims: [
                'role' => 'admin',
                'is_bot' => true,
                'type' => 'access',
            ]
        );

        $io->success('Bot token generated successfully!');

        $io->section('Bot Details');
        $io->table(
            ['Property', 'Value'],
            [
                ['Email', $email],
                ['Role', 'admin'],
                ['Is Bot', 'true'],
                ['Redmine URL', $redmineUrl],
                ['JWT Expiry', $jwtExpiry],
                ['Expires At', (new \DateTimeImmutable($jwtExpiry))->format('Y-m-d H:i:s')],
            ]
        );

        $io->section('JWT Token (copy this for n8n)');
        $io->writeln($token);

        $io->note([
            'This token grants admin access with embedded Redmine credentials.',
            'Store it securely in your n8n environment variables.',
            'Use it in the Authorization header: Bearer <token>',
            'No database storage required - credentials are in the token.',
        ]);

        return Command::SUCCESS;
    }
}