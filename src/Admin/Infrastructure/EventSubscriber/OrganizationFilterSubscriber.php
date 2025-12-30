<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\EventSubscriber;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Filter\OrganizationFilter;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Automatically enables the organization filter for admin requests.
 *
 * When an ORG_ADMIN user accesses the admin panel, this subscriber
 * enables the Doctrine filter to automatically scope all queries
 * to their organization.
 *
 * SUPER_ADMIN users bypass the filter to see all organizations.
 */
class OrganizationFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly UserRepository $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only apply to admin routes
        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        $session = $this->requestStack->getSession();
        $adminUserId = $session->get('admin_user_id');

        if (null === $adminUserId) {
            return;
        }

        $user = $this->userRepository->find($adminUserId);

        if (!$user instanceof User) {
            return;
        }

        // SUPER_ADMIN sees everything - don't enable filter
        if ($user->isSuperAdmin()) {
            return;
        }

        // Enable organization filter for ORG_ADMIN
        $filters = $this->em->getFilters();

        if (!$filters->isEnabled('organization')) {
            /** @var OrganizationFilter $filter */
            $filter = $filters->enable('organization');
            $filter->setOrganization($user->getOrganization());
        }
    }
}
