<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Model\UserCredential;
use App\Infrastructure\Security\JwtTokenValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates a bot token with embedded provider credentials.
 *
 * Stateless architecture: the token contains all credentials,
 * no database storage required.
 */
#[AsCommand(
    name: 'app:create-bot',
    description: 'Create an admin bot JWT token with embedded credentials for n8n integration'
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
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Provider type: redmine or jira', 'redmine')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Bot user email (e.g., bot@admin.com)')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Provider URL (Redmine URL or Jira host)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key/token')
            ->addOption('provider-email', null, InputOption::VALUE_OPTIONAL, 'Provider email (required for Jira)')
            ->addOption('jwt-expiry', null, InputOption::VALUE_OPTIONAL, 'JWT token expiry (e.g., "+1 year", "+30 days")', '+1 year')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $provider = $input->getOption('provider');
        $email = $input->getOption('email');
        $url = $input->getOption('url');
        $apiKey = $input->getOption('api-key');
        $providerEmail = $input->getOption('provider-email');
        $jwtExpiry = $input->getOption('jwt-expiry');

        if (!$email || !$url || !$apiKey) {
            $io->error('Required options: --email, --url, --api-key');

            return Command::FAILURE;
        }

        if (!in_array($provider, [UserCredential::PROVIDER_REDMINE, UserCredential::PROVIDER_JIRA], true)) {
            $io->error('Provider must be "redmine" or "jira"');

            return Command::FAILURE;
        }

        if (UserCredential::PROVIDER_JIRA === $provider && !$providerEmail) {
            $io->error('Jira provider requires --provider-email option');

            return Command::FAILURE;
        }

        // Calculate expiry in seconds
        $expiresIn = (new \DateTimeImmutable($jwtExpiry))->getTimestamp() - time();

        // Build credentials array
        $credentials = [
            'provider' => $provider,
            'url' => rtrim($url, '/'),
            'key' => $apiKey,
        ];

        if ($providerEmail) {
            $credentials['email'] = $providerEmail;
        }

        // Generate long-lived JWT token with embedded credentials
        $token = $this->tokenValidator->createTokenWithCredentials(
            userId: $email,
            credentials: $credentials,
            expiresIn: $expiresIn,
            extraClaims: [
                'role' => 'admin',
                'is_bot' => true,
                'type' => 'access',
            ]
        );

        $io->success('Bot token generated successfully!');

        $io->section('Bot Details');
        $tableData = [
            ['Email', $email],
            ['Role', 'admin'],
            ['Is Bot', 'true'],
            ['Provider', $provider],
            ['URL', $url],
            ['JWT Expiry', $jwtExpiry],
            ['Expires At', (new \DateTimeImmutable($jwtExpiry))->format('Y-m-d H:i:s')],
        ];

        if ($providerEmail) {
            $tableData[] = ['Provider Email', $providerEmail];
        }

        $io->table(['Property', 'Value'], $tableData);

        $io->section('JWT Token (copy this for n8n)');
        $io->writeln($token);

        $io->note([
            'This token grants admin access with embedded credentials.',
            'Store it securely in your n8n environment variables.',
            'Use it in the Authorization header: Bearer <token>',
            'No database storage required - credentials are in the token.',
        ]);

        return Command::SUCCESS;
    }
}
