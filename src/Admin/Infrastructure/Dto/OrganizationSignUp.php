<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Dto;

use App\Admin\Infrastructure\Validator\UniqueOrganizationName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for organization self-service signup wizard.
 */
final class OrganizationSignUp
{
    public const SIZE_CHOICES = [
        'Solo (1 personne)' => 'solo',
        'Petite équipe (2-10)' => 'small',
        'PME (11-50)' => 'medium',
        'Grande entreprise (50+)' => 'large',
    ];

    // Step 1: Organization
    #[Assert\NotBlank(groups: ['organization'], message: 'Le nom est requis')]
    #[Assert\Length(min: 2, max: 100, groups: ['organization'])]
    #[UniqueOrganizationName(groups: ['organization'])]
    public ?string $name = null;

    #[Assert\NotBlank(groups: ['organization'], message: 'La taille est requise')]
    #[Assert\Choice(callback: [self::class, 'getSizeValues'], groups: ['organization'])]
    public ?string $size = null;

    // Step 2: Provider (choices validated dynamically via form, not hardcoded here)
    #[Assert\NotBlank(groups: ['provider'], message: 'Choisissez un provider')]
    public ?string $providerType = null;

    /**
     * @return array<string>
     */
    public static function getSizeValues(): array
    {
        return array_values(self::SIZE_CHOICES);
    }

    // URL is required only for self-hosted providers (redmine, jira)
    #[Assert\Url(groups: ['provider'], message: 'URL invalide')]
    #[Assert\Expression(
        expression: "this.providerType == 'monday' or (value != null and value != '')",
        message: "L'URL est requise pour ce provider",
        groups: ['provider']
    )]
    public ?string $providerUrl = null;

    // Track current step (used by FormFlow)
    public string $currentStep = 'organization';
}
