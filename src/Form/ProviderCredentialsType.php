<?php

declare(strict_types=1);

namespace App\Form;

use App\Domain\Model\UserCredential;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class ProviderCredentialsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('provider', ChoiceType::class, [
                'choices' => [
                    'Redmine' => UserCredential::PROVIDER_REDMINE,
                    'Jira' => UserCredential::PROVIDER_JIRA,
                ],
                'expanded' => true,
                'label' => false,
                'data' => UserCredential::PROVIDER_REDMINE,
                'attr' => ['class' => 'provider-selector'],
            ])
            ->add('provider_url', UrlType::class, [
                'label' => 'URL',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter the URL']),
                    new Url(['message' => 'Please enter a valid URL']),
                ],
                'attr' => [
                    'placeholder' => 'https://redmine.example.com',
                ],
                'help' => 'The full URL of your instance (without trailing slash)',
            ])
            ->add('provider_email', EmailType::class, [
                'label' => 'Jira Email',
                'required' => false,
                'attr' => [
                    'placeholder' => 'your-email@company.com',
                ],
                'help' => 'The email address associated with your Atlassian account',
            ])
            ->add('provider_api_key', TextType::class, [
                'label' => 'API Key',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your API key']),
                ],
                'attr' => [
                    'placeholder' => 'Your API key',
                ],
                'help' => 'Find your API key in your account settings',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Authorize Access',
            ]);

        // Add dynamic validation for Jira email
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $form->getData();

            if (UserCredential::PROVIDER_JIRA === ($data['provider'] ?? null)) {
                $email = $data['provider_email'] ?? '';
                if (empty($email)) {
                    $form->get('provider_email')->addError(
                        new \Symfony\Component\Form\FormError('Jira requires an email address')
                    );
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
