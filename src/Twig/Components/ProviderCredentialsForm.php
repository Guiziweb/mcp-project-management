<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Form\ProviderCredentialsType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live Component for provider credentials form.
 *
 * Rebuilds form dynamically based on selected provider.
 */
#[AsLiveComponent]
final class ProviderCredentialsForm
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    /** @var array<string, mixed> */
    #[LiveProp]
    public array $initialFormData = [];

    #[LiveProp(writable: true)]
    public string $selectedProvider = 'redmine';

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(
            ProviderCredentialsType::class,
            $this->initialFormData,
            ['selected_provider' => $this->selectedProvider]
        );
    }
}
