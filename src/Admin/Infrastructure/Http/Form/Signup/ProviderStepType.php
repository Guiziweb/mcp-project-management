<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Form\Signup;

use App\Mcp\Infrastructure\Adapter\AdapterRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Step 2: Provider selection with visual cards.
 */
final class ProviderStepType extends AbstractType
{
    public function __construct(
        private readonly AdapterRegistry $adapterRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('providerType', ChoiceType::class, [
                'label' => false,
                'choices' => $this->adapterRegistry->getFormChoices(),
                'expanded' => true, // Radio buttons
                'placeholder' => false,
                'attr' => ['class' => 'provider-cards'],
            ])
            ->add('providerUrl', UrlType::class, [
                'label' => 'URL de votre instance',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'placeholder' => 'https://...',
                    'class' => 'w-full bg-neutral-900 border border-neutral-700 rounded-lg px-4 py-3 text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-neutral-500 transition-colors',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-neutral-300 mb-2'],
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Pass provider cards data to the template
        $view->vars['provider_cards'] = $this->adapterRegistry->getProviderCards();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
        ]);
    }
}
