<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Form\Signup;

use App\Admin\Infrastructure\Dto\OrganizationSignUp;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Organization signup form: name, size, and Redmine URL.
 */
final class OrganizationStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de votre organisation',
                'attr' => [
                    'placeholder' => 'Mon Agence',
                    'class' => 'w-full bg-neutral-900 border border-neutral-700 rounded-lg px-4 py-3 text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-neutral-500 transition-colors',
                    'autofocus' => true,
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-neutral-300 mb-2'],
            ])
            ->add('size', ChoiceType::class, [
                'label' => 'Taille de votre équipe',
                'choices' => OrganizationSignUp::SIZE_CHOICES,
                'placeholder' => 'Sélectionnez...',
                'attr' => [
                    'class' => 'w-full bg-neutral-900 border border-neutral-700 rounded-lg px-4 py-3 text-neutral-100 focus:outline-none focus:border-neutral-500 transition-colors',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-neutral-300 mb-2 mt-4'],
            ])
            ->add('redmineUrl', UrlType::class, [
                'label' => 'URL Redmine',
                'default_protocol' => null,
                'attr' => [
                    'placeholder' => 'https://redmine.exemple.com',
                    'class' => 'w-full bg-neutral-900 border border-neutral-700 rounded-lg px-4 py-3 text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-neutral-500 transition-colors',
                    'type' => 'url',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-neutral-300 mb-2 mt-4'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
        ]);
    }
}
