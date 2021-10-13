<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use App\Form\User\LoginForm;

class SecurityController extends AbstractController
{
    /** @var RouterInterface $router The Router service */
    private $router;

    /** @var AuthenticationUtils $this->auth Authentication Utilities */
    private $auth;

    public function __construct(
        AuthenticationUtils $auth,
        RouterInterface $router
    ) {
        $this->auth = $auth;
        $this->router = $router;
    }


    /**
     * Display login form
     */
    public function loginEmbeddedShowAction(
        Request $request,
        $site_domain,
        $token = null
    ) {
        // Evaluate the Target paths - your code here
        // $target_path = ...
        // $failure_path = ...

        $form = $this->createForm(LoginForm::class, [
            'site_domain' => $site_domain,
            // Target paths
            '_target_path' => $target_path,
            '_failure_path' => $failure_path,
        ]);

        return $this->render('security/login.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
