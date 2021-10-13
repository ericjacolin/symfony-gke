<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Exception\AuthenticationException;


/**
 * Sets session variables after login
 */
class AuthenticationListener
{
    /**
     * onAuthenticationSuccess
     *
     * @param InteractiveLoginEvent $event
     */
    public function onAuthenticationSuccess(
        InteractiveLoginEvent $event
    ) {
        $request = $event->getRequest();
        $user = $event->getAuthenticationToken()->getUser();
        // Initiate session
        // your code here
    }

    /**
     * onAuthenticationFailure
     *
     * @param AuthenticationFailureEvent $event
     */
    public function onAuthenticationFailure(
        AuthenticationFailureEvent $event
    ) {
        $token = $event->getAuthenticationToken();
        $username = $token->getUsername();

        // your code here
    }
}
