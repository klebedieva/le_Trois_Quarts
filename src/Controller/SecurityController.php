<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Security Controller
 * 
 * Handles authentication (login/logout) for the admin panel.
 * Uses Symfony's security component for authentication processing.
 * 
 * Note: The logout route is handled by Symfony's firewall configuration,
 * this method should never actually execute.
 */
class SecurityController extends AbstractController
{
    /**
     * Display login form and handle authentication
     * 
     * If user is already authenticated, redirects to admin dashboard.
     * Otherwise, displays login form with error messages (if any) and last entered username.
     * 
     * @param AuthenticationUtils $authenticationUtils Symfony authentication utilities
     * @return Response Rendered login form or redirect to admin if already logged in
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect to admin if user is already authenticated
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        // Get authentication error if login attempt failed
        $error = $authenticationUtils->getLastAuthenticationError();
        // Get last username entered for convenience (pre-fill form)
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Logout endpoint
     * 
     * This method should never execute - logout is handled by Symfony's firewall
     * configuration. The route is intercepted by the security component.
     * 
     * @return void This method should never return
     * @throws \LogicException If somehow this method executes (shouldn't happen)
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
