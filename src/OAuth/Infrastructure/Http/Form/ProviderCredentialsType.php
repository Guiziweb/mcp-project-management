<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Http\Form;

use App\Mcp\Infrastructure\Adapter\AdapterRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form for user credentials.
 *
 * The provider is determined by the user's organization.
 * This form only shows user-level fields (api_key, email for Jira, etc.).
 */
class ProviderCredentialsType extends AbstractType
{
    public function __construct(
        private readonly AdapterRegistry $adapterRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $providerType = $options['provider_type'];

        // Get user-level fields for this provider
        $fields = $this->adapterRegistry->getUserFields($providerType);

        foreach ($fields as $fieldName => $fieldConfig) {
            $type = $this->getFormType($fieldConfig['type']);
            $constraints = [];

            if ($fieldConfig['required'] ?? true) {
                $constraints[] = new NotBlank([
                    'message' => sprintf('Please enter %s', strtolower($fieldConfig['label'])),
                ]);
            }

            $builder->add($fieldName, $type, [
                'label' => $fieldConfig['label'],
                'required' => $fieldConfig['required'] ?? true,
                'constraints' => $constraints,
                'attr' => [
                    'placeholder' => $fieldConfig['placeholder'],
                ],
                'help' => $fieldConfig['help'] ?? null,
            ]);
        }

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['provider_type']);
        $resolver->setAllowedTypes('provider_type', 'string');
    }

    private function getFormType(string $type): string
    {
        return match ($type) {
            'url' => UrlType::class,
            'email' => EmailType::class,
            default => TextType::class,
        };
    }
}
