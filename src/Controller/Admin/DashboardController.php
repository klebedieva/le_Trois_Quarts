<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Entity\MenuItem;
use App\Entity\Drink;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\Table;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\GalleryImage;
use App\Repository\ContactMessageRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReviewRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem as EaMenuItem;
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
        $reservationRepository = $this->entityManager->getRepository(Reservation::class);
        $orderRepository = $this->entityManager->getRepository(Order::class);
        
        $totalReviews = $reviewRepository->count([]);
        $approvedReviews = $reviewRepository->count(['isApproved' => true]);
        $pendingReviews = $reviewRepository->count(['isApproved' => false]);
        
        // Get contact messages statistics
        $totalMessages = $contactRepository->count([]);
        $repliedMessages = $contactRepository->count(['isReplied' => true]);
        $pendingMessages = $contactRepository->count(['isReplied' => false]);
        
        // Get reservations statistics
        $totalReservations = $reservationRepository->count([]);
        $confirmedReservations = $reservationRepository->count(['status' => 'confirmed']);
        $pendingReservations = $reservationRepository->count(['status' => 'pending']);
        $cancelledReservations = $reservationRepository->count(['status' => 'cancelled']);
        
        // Get orders statistics (only for admins)
        $totalOrders = 0;
        $pendingOrders = 0;
        $confirmedOrders = 0;
        $preparingOrders = 0;
        $deliveredOrders = 0;
        $cancelledOrders = 0;
        $totalRevenue = 0;
        
        if ($this->isGranted('ROLE_ADMIN')) {
            $totalOrders = $orderRepository->count([]);
            $pendingOrders = $orderRepository->count(['status' => 'pending']);
            $confirmedOrders = $orderRepository->count(['status' => 'confirmed']);
            $preparingOrders = $orderRepository->count(['status' => 'preparing']);
            $deliveredOrders = $orderRepository->count(['status' => 'delivered']);
            $cancelledOrders = $orderRepository->count(['status' => 'cancelled']);
            
            // Get total revenue
            $totalRevenue = $orderRepository->createQueryBuilder('o')
                ->select('SUM(o.total)')
                ->where('o.status IN (:deliveredStatuses)')
                ->setParameter('deliveredStatuses', ['delivered'])
                ->getQuery()
                ->getSingleScalarResult();
            
            $totalRevenue = $totalRevenue ? (float) $totalRevenue : 0;
        }
        
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
            'totalReservations' => $totalReservations,
            'confirmedReservations' => $confirmedReservations,
            'pendingReservations' => $pendingReservations,
            'cancelledReservations' => $cancelledReservations,
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'confirmedOrders' => $confirmedOrders,
            'preparingOrders' => $preparingOrders,
            'deliveredOrders' => $deliveredOrders,
            'cancelledOrders' => $cancelledOrders,
            'totalRevenue' => $totalRevenue,
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

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('assets/css/admin-styles.css')
            ->addJsFile('assets/js/admin.js');
    }

    public function configureMenuItems(): iterable
    {
        yield EaMenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield EaMenuItem::subMenu('Carte (Menu)', 'fa fa-utensils')->setSubItems([
            EaMenuItem::linkToCrud('Plats', 'fa fa-bowl-food', MenuItem::class),
            EaMenuItem::linkToCrud('Boissons', 'fa fa-wine-glass', Drink::class),
        ]);
        yield EaMenuItem::linkToCrud('Tables', 'fas fa-chair', Table::class);
        yield EaMenuItem::linkToCrud('Réservations', 'fas fa-calendar-check', Reservation::class);
        
        // Only show orders menu for admins
        if ($this->isGranted('ROLE_ADMIN')) {
            yield EaMenuItem::subMenu('Commandes', 'fas fa-shopping-cart')->setSubItems([
                EaMenuItem::linkToCrud('Commandes', 'fas fa-receipt', Order::class),
                EaMenuItem::linkToCrud('Articles de commande', 'fas fa-list', OrderItem::class),
            ]);
        }
        
        yield EaMenuItem::linkToCrud('Messages de contact', 'fas fa-envelope', ContactMessage::class);
        yield EaMenuItem::linkToCrud('Avis', 'fas fa-comments', Review::class);
        yield EaMenuItem::linkToCrud('Galerie', 'fas fa-images', GalleryImage::class);
        yield EaMenuItem::linkToUrl('Retour au site', 'fas fa-external-link-alt', '/');
    }

    public function configureCrudControllers(): iterable
    {
        yield Reservation::class => ReservationCrudController::class;
        yield ContactMessage::class => ContactMessageCrudController::class;
        yield Review::class => ReviewCrudController::class;
        yield MenuItem::class => MenuItemCrudController::class;
        yield Drink::class => DrinkCrudController::class;
        yield Table::class => TableCrudController::class;
        yield Order::class => OrderCrudController::class;
        yield OrderItem::class => OrderItemCrudController::class;
        yield GalleryImage::class => GalleryImageCrudController::class;
    }
}
