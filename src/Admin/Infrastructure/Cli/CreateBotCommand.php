<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Cli;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\OrganizationRepository;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\OAuth\Infrastructure\Security\TokenService;
use App\Shared\Domain\UserCredential;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates a bot token for automation integrations (n8n, etc.).
 */
#[AsCommand(
    name: 'app:create-bot',
    description: 'Create a bot token for n8n or other automation integrations'
)]
class CreateBotCommand extends Command
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly OrganizationRepository $organizationRepository,
        private readonly UserRepository $userRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('organization', 'o', InputOption::VALUE_REQUIRED, 'Organization slug')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Bot user email (e.g., bot@company.com)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'Redmine API key')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orgSlug = $input->getOption('organization');
        $email = $input->getOption('email');
        $apiKey = $input->getOption('api-key');

        if (!$orgSlug || !$email || !$apiKey) {
            $io->error('Required options: --organization, --email, --api-key');

            return Command::FAILURE;
        }

        $organization = $this->organizationRepository->findBySlug($orgSlug);
        if (null === $organization) {
            $io->error(sprintf('Organization "%s" not found', $orgSlug));

            return Command::FAILURE;
        }

        // Find or create bot user
        $user = $this->userRepository->findByEmail($email);
        if (null === $user) {
            $user = new User($email, 'bot:'.$email, $organization, $this->clock->now());
            $user->setRoles([User::ROLE_ORG_ADMIN]);
            $this->userRepository->save($user);
            $io->note(sprintf('Created new bot user: %s', $email));
        } elseif ($user->getOrganization()->getId() !== $organization->getId()) {
            $io->error(sprintf('User %s belongs to a different organization', $email));

            return Command::FAILURE;
        }

        // Build credentials
        $providerUrl = $organization->getProviderUrl();

        $credentials = [
            'provider' => UserCredential::PROVIDER_REDMINE,
            'org_config' => $providerUrl ? ['url' => $providerUrl] : [],
            'user_credentials' => ['api_key' => $apiKey],
        ];

        $token = $this->tokenService->createAccessToken($user, $credentials);

        $io->success('Bot token created successfully!');

        $io->section('Bot Details');
        $io->table(['Property', 'Value'], [
            ['Email', $email],
            ['Organization', $organization->getName()],
            ['URL', $providerUrl ?? 'N/A'],
        ]);

        $io->section('Access Token');
        $io->writeln($token);

        $io->note([
            'Store this token securely.',
            'Use it in the Authorization header: Bearer <token>',
            'Token expires in 24 hours. Use refresh flow for long-lived access.',
        ]);

        return Command::SUCCESS;
    }
}
