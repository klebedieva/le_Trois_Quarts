<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Entity\Review;
use App\Repository\ContactMessageRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\CrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[IsGranted('ROLE_MODERATOR')]
    public function index(): Response
    {
        // Get reviews statistics for dashboard
        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $contactRepository = $this->entityManager->getRepository(ContactMessage::class);
        
        $totalReviews = $reviewRepository->count([]);
        $approvedReviews = $reviewRepository->count(['isApproved' => true]);
        $pendingReviews = $reviewRepository->count(['isApproved' => false]);
        
        // Get contact messages statistics
        $totalMessages = $contactRepository->count([]);
        $repliedMessages = $contactRepository->count(['isReplied' => true]);
        $pendingMessages = $contactRepository->count(['isReplied' => false]);
        
        // Get average rating
        $avgRating = $reviewRepository->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.isApproved = :approved')
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();
        
        $avgRating = $avgRating ? round($avgRating, 1) : 0;
        
        // Get recent reviews
        $recentReviews = $reviewRepository->findBy(
            ['isApproved' => true],
            ['createdAt' => 'DESC'],
            4
        );

        return $this->render('admin/dashboard.html.twig', [
            'totalReviews' => $totalReviews,
            'approvedReviews' => $approvedReviews,
            'pendingReviews' => $pendingReviews,
            'avgRating' => $avgRating,
            'recentReviews' => $recentReviews,
            'totalMessages' => $totalMessages,
            'repliedMessages' => $repliedMessages,
            'pendingMessages' => $pendingMessages,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Le Trois Quarts - Administration')
            ->setFaviconPath('favicon.ico')
            ->setTextDirection('ltr')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Avis', 'fas fa-comments', Review::class);
        yield MenuItem::linkToCrud('Messages de contact', 'fas fa-envelope', ContactMessage::class);
        yield MenuItem::linkToUrl('Retour au site', 'fas fa-external-link-alt', '/');
    }

    public function configureCrudControllers(): iterable
    {
        yield Review::class => ReviewCrudController::class;
        yield ContactMessage::class => ContactMessageCrudController::class;
    }
}
