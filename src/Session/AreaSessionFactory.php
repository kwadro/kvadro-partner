<?php

namespace App\Session;

use App\EventSubscriber\SplitSessionSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AreaSessionFactory implements SessionFactoryInterface
{
    public function __construct(
        private SessionFactoryInterface $inner,
        private RequestStack $requestStack,
    ) {
    }

    public function createSession(): SessionInterface
    {
        $session = $this->inner->createSession();

        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
        $isAdminArea = $request && str_starts_with($request->getPathInfo(), SplitSessionSubscriber::ADMIN_PATH_PREFIX);

        $session->setName(
            $isAdminArea
                ? SplitSessionSubscriber::ADMIN_SESSION_COOKIE
                : SplitSessionSubscriber::USER_SESSION_COOKIE
        );

        return $session;
    }
}
