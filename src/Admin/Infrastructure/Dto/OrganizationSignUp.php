<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Dto;

use App\Admin\Infrastructure\Validator\UniqueOrganizationName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for organization signup with Redmine.
 */
final class OrganizationSignUp
{
    public const SIZE_CHOICES = [
        'Solo (1 personne)' => 'solo',
        'Petite équipe (2-10)' => 'small',
        'PME (11-50)' => 'medium',
        'Grande entreprise (50+)' => 'large',
    ];

    #[Assert\NotBlank(message: 'Le nom est requis')]
    #[Assert\Length(min: 2, max: 100)]
    #[UniqueOrganizationName]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'La taille est requise')]
    #[Assert\Choice(callback: [self::class, 'getSizeValues'])]
    public ?string $size = null;

    #[Assert\NotBlank(message: "L'URL Redmine est requise")]
    #[Assert\Url(message: 'URL invalide')]
    public ?string $redmineUrl = null;

    /**
     * @return array<string>
     */
    public static function getSizeValues(): array
    {
        return array_values(self::SIZE_CHOICES);
    }
}
