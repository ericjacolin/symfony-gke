<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

/**
 * Custom authentication success handler
 */
class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    /** @var RouterInterface $router The Router service */
    private $router;

   /**
    * Constructor
    * @param RouterInterface   $router
    */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

   /**
    * This is called when an interactive authentication attempt succeeds. This
    * is called by authentication listeners inheriting from AbstractAuthenticationListener.
    * @param Request        $request
    * @param TokenInterface $token
    * @return Response The response to return
    */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        // Flash message
        $request->getSession()->getFlashBag()->add('error', 'Incorrect email or password');
        // The failure redirection path
        $path = !is_null($request->get('_failure_path')) ? $request->get('_failure_path') : '/';
        return new RedirectResponse($path);
    }
}
