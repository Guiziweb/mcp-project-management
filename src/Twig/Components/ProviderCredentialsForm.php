<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Form\ProviderCredentialsType;
use App\Infrastructure\Security\OAuthAuthorizationCodeStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live Component for provider credentials form.
 *
 * Handles dynamic field switching and form submission.
 */
#[AsLiveComponent]
final class ProviderCredentialsForm extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    /** @var array<string, mixed> */
    #[LiveProp]
    public array $initialFormData = [];

    #[LiveProp(writable: true, onUpdated: 'onProviderChange')]
    public string $selectedProvider = 'redmine';

    public function onProviderChange(): void
    {
        $this->initialFormData = ['provider' => $this->selectedProvider];
        $this->resetForm();
    }

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly OAuthAuthorizationCodeStore $codeStore,
    ) {
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(
            ProviderCredentialsType::class,
            $this->initialFormData,
            ['selected_provider' => $this->selectedProvider]
        );
    }

    #[LiveAction]
    public function save(): RedirectResponse
    {
        $this->submitForm();

        $session = $this->requestStack->getSession();
        $data = $this->getForm()->getData();

        // Generate authorization code with credentials
        $authCode = bin2hex(random_bytes(32));
        $authData = [
            'user_id' => $session->get('google_user_email'),
            'client_id' => $session->get('oauth_client_id'),
            'redirect_uri' => $session->get('oauth_redirect_uri'),
            'provider' => $data['provider'],
            'provider_url' => rtrim($data['url'], '/'),
            'provider_api_key' => $data['api_key'],
        ];

        if (!empty($data['email'])) {
            $authData['provider_email'] = $data['email'];
        }

        $this->codeStore->store($authCode, $authData);

        // Build redirect URL
        $redirectUri = $session->get('oauth_redirect_uri');
        $state = $session->get('oauth_state');
        $redirectUrl = $redirectUri.'?code='.$authCode;
        if ($state) {
            $redirectUrl .= '&state='.urlencode($state);
        }

        // Clear session
        foreach (['oauth_client_id', 'oauth_redirect_uri', 'oauth_state', 'google_oauth_state', 'google_user_email', 'google_user_name'] as $key) {
            $session->remove($key);
        }

        return new RedirectResponse($redirectUrl);
    }
}
