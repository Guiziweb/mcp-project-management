<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Validator;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Repository\OrganizationRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that an organization name generates a unique slug.
 */
final class UniqueOrganizationNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueOrganizationName) {
            throw new UnexpectedTypeException($constraint, UniqueOrganizationName::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $slug = Organization::generateSlugFromName($value);

        if ($this->organizationRepository->findBySlug($slug)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
