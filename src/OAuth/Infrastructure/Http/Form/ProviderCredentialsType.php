<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form for Redmine API key credential.
 */
class ProviderCredentialsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('api_key', TextType::class, [
            'label' => 'API Key',
            'constraints' => [
                new NotBlank(['message' => 'Please enter your API key']),
            ],
            'attr' => [
                'placeholder' => 'Your Redmine API key',
            ],
            'help' => 'My account → API access key (right column)',
        ]);
    }
}
