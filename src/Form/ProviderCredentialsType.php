<?php

declare(strict_types=1);

namespace App\Form;

use App\Infrastructure\Provider\ProviderRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class ProviderCredentialsType extends AbstractType
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = $this->providerRegistry->getFormChoices();
        $selectedProvider = $options['selected_provider'] ?? array_values($choices)[0] ?? 'redmine';

        $builder->add('provider', ChoiceType::class, [
            'choices' => $choices,
            'expanded' => true,
            'label' => false,
            'data' => $selectedProvider,
        ]);

        // Add credential fields based on selected provider
        $fields = $this->providerRegistry->getCredentialFields($selectedProvider) ?? [];

        foreach ($fields as $fieldName => $fieldConfig) {
            $type = $this->getFormType($fieldConfig['type']);
            $constraints = [];

            if ($fieldConfig['required'] ?? true) {
                $constraints[] = new NotBlank(['message' => sprintf('Please enter %s', strtolower($fieldConfig['label']))]);
            }

            if ('url' === $fieldConfig['type']) {
                $constraints[] = new Url(['message' => 'Please enter a valid URL']);
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

        $builder->add('submit', SubmitType::class, [
            'label' => 'Authorize Access',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // CSRF disabled: LiveComponent uses same-origin/CORS protection instead
        // See: https://github.com/symfony/ux/issues/2527
        $resolver->setDefaults([
            'csrf_protection' => false,
            'selected_provider' => null,
        ]);
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
