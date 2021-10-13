<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Doctrine\ORM\EntityManager;
use App\Entity\User;

/**
 * Custom authentication success handler
 */
class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    /** @var RouterInterface $router The Router service */
    private $router;

    /** @var SessionInterface $session The Session service */
    private $session;

    /**
    * Constructor
    * @param RouterInterface   $router
    */
    public function __construct(RouterInterface $router, SessionInterface $session)
    {
        $this->router = $router;
        $this->session = $session;
    }

    /**
    * This is called when an interactive authentication attempt succeeds. This
    * is called by authentication listeners inheriting from AbstractAuthenticationListener.
    * @param Request        $request
    * @param TokenInterface $token
    * @return Response The response to return
    */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        // Session variables are set by the AuthenticationListener

        // The failure redirection path
        $path = !is_null($request->get('_target_path')) ? $request->get('_target_path') : '/';
        return new RedirectResponse($path);
    }
}
