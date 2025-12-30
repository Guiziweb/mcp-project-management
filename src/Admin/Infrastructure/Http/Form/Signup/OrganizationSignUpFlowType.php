<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Form\Signup;

use App\Admin\Infrastructure\Dto\OrganizationSignUp;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Multi-step form for organization self-service signup.
 *
 * Steps:
 * 1. Organization: name, size (slug auto-generated from name)
 * 2. Provider: type, URL
 */
final class OrganizationSignUpFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            ->addStep('organization', OrganizationStepType::class)
            ->addStep('provider', ProviderStepType::class);

        $builder->add('navigator', NavigatorFlowType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrganizationSignUp::class,
            'step_property_path' => 'currentStep',
        ]);
    }
}
