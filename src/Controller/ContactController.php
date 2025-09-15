<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Form\ContactMessageType;
use App\Service\SymfonyEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SymfonyEmailService $emailService
    ) {}

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        $msg = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $msg);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($msg);
            $this->em->flush();

            // Send notification to admin
            try {
                $this->emailService->sendNotificationToAdmin(
                    $msg->getEmail(),
                    $msg->getFirstName() . ' ' . $msg->getLastName(),
                    $msg->getSubject(),
                    $msg->getMessage()
                );
            } catch (\Exception $e) {
                // Log error but don't prevent saving
                error_log('Error sending notification to admin: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Merci! Votre message a été envoyé avec succès.');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('pages/contact.html.twig', [
            'contactForm' => $form->createView(),
        ]);
    }
}
