<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Form\Signup;

use App\Admin\Infrastructure\Dto\OrganizationSignUp;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Single-step form for organization signup with Redmine.
 */
final class OrganizationSignUpFlowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('organization', OrganizationStepType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrganizationSignUp::class,
        ]);
    }
}
