<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Service\SymfonyEmailService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_MODERATOR')]
class ContactMessageCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;
    private SymfonyEmailService $emailService;

    public function __construct(EntityManagerInterface $entityManager, SymfonyEmailService $emailService)
    {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
    }

    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message de contact')
            ->setEntityLabelInPlural('Messages de contact')
            ->setPageTitle('index', 'Gestion des messages de contact')
            ->setPageTitle('detail', 'Détails du message')
            ->setPageTitle('edit', 'Modifier le message')
            ->setPageTitle('new', 'Nouveau message')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig']);
    }

    public function configureFields(string $pageName): iterable
    {
        $subjectChoices = [
            'Réservation' => 'reservation',
            'Commande' => 'commande',
            'Événement privé' => 'evenement_prive',
            'Suggestion' => 'suggestion',
            'Réclamation' => 'reclamation',
            'Autre' => 'autre',
        ];

        $replyField = TextareaField::new('replyMessage', 'Réponse')
        ->hideOnForm()
        ->formatValue(fn ($v) => $v ? nl2br($v) : '')
        ->renderAsHtml()
        ->addCssClass('ea-reply');

        return [
            IdField::new('id')
                ->hideOnForm()
                ->hideOnIndex(),
            
            TextField::new('firstName', 'Prénom')
                ->setRequired(true)
                ->setHelp('Prénom de la personne qui a envoyé le message'),
            
            TextField::new('lastName', 'Nom')
                ->setRequired(true)
                ->setHelp('Nom de famille de la personne'),
            
            EmailField::new('email', 'Email')
                ->setRequired(true)
                ->setHelp('Adresse email de contact'),
            
            TelephoneField::new('phone', 'Téléphone')
                ->setRequired(false)
                ->setHelp('Numéro de téléphone (optionnel)'),
            
            ChoiceField::new('subject', 'Sujet')
                ->setRequired(true)
                ->setChoices($subjectChoices)
                ->setHelp('Sujet du message'),
            
            TextareaField::new('message', 'Message')
                ->setRequired(true)
                ->setHelp('Contenu du message')
                ->setMaxLength(2000)
                ->setNumOfRows(6)
                ->formatValue(function ($value, $entity) use ($pageName) {
                    if (!$value) {
                        return '';
                    }
                    $text = nl2br(htmlspecialchars($value)); // preserve line breaks and escape HTML
                    // Only truncate on index page
                    if ($pageName === Crud::PAGE_INDEX) {
                        $maxLength = 80; // character count for message
                        if (mb_strlen($value) > $maxLength) {
                            return mb_substr($text, 0, $maxLength) . '...';
                        }
                    }
                    return $text;
                })
                ->renderAsHtml(), // enable HTML rendering for <br> tags
            
            BooleanField::new('consent', 'Consentement')
                ->setHelp('La personne a accepté d\'être contactée')
                ->hideOnForm()
                ->renderAsSwitch(false),
            
            DateTimeField::new('createdAt', 'Date de réception')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setHelp('Date et heure de réception du message'),
            
            BooleanField::new('isReplied', 'Répondu')
                ->hideOnForm()
                ->renderAsSwitch(false)
                ->setHelp('Indique si une réponse a été envoyée'),
            
            DateTimeField::new('repliedAt', 'Date de réponse')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setHelp('Date et heure de la réponse'),
            
            TextareaField::new('replyMessage', 'Réponse')
                ->hideOnForm()
                ->setHelp('Message de réponse envoyé au client')
                ->formatValue(function ($value, $entity) use ($pageName) {
                    if (!$value) {
                        return '<em style="color: #6c757d; font-style: italic;">Aucune réponse envoyée</em>';
                    }
                    $text = nl2br(htmlspecialchars($value));
                    if ($pageName === Crud::PAGE_INDEX) {
                        $maxLength = 80;
                        if (mb_strlen($value) > $maxLength) {
                            return mb_substr($text, 0, $maxLength) . '...';
                        }
                    }
                    return $text;
                })
                ->renderAsHtml(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $replyAction = Action::new('reply', 'Répondre')
            ->setIcon('fa fa-reply')
            ->setCssClass('btn btn-soft-success btn-sm')
            ->linkToCrudAction('reply')
            ->displayIf(function ($entity) {
                return $entity instanceof ContactMessage && $entity->getId() !== null && !$entity->isReplied();
            });

        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $replyAction)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action
                    ->setIcon('fa fa-eye')
                    ->setLabel('Voir')
                    ->setCssClass('btn btn-soft-info btn-sm')
                    ->displayIf(function ($entity) {
                        return $entity instanceof ContactMessage && $entity->getId() !== null;
                    });
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action
                    ->setIcon('fa fa-trash')
                    ->setLabel('Supprimer')
                    // Keep EasyAdmin's expected class so the modal submits the delete form
                    ->setCssClass('action-delete btn btn-soft-danger btn-sm')
                    ->displayIf(function ($entity) {
                        return $entity instanceof ContactMessage && $entity->getId() !== null;
                    });
            })
            // Only admins can delete contact messages
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('firstName', 'Prénom'))
            ->add(TextFilter::new('lastName', 'Nom'))
            ->add(TextFilter::new('email', 'Email'))
            ->add(ChoiceFilter::new('subject', 'Sujet')
                ->setChoices([
                    'Réservation' => 'reservation',
                    'Commande' => 'commande',
                    'Événement privé' => 'evenement_prive',
                    'Suggestion' => 'suggestion',
                    'Réclamation' => 'reclamation',
                    'Autre' => 'autre',
                ]))
            ->add(BooleanFilter::new('consent', 'Consentement'))
            ->add(BooleanFilter::new('isReplied', 'Répondu'))
            ->add(DateTimeFilter::new('createdAt', 'Date de réception'));
    }

    public function reply(Request $request): Response
    {
        $entityId = $request->query->get('entityId');
        if (!$entityId) {
            $this->addFlash('error', 'ID du message non trouvé.');
            return $this->redirectToRoute('admin_contact_message_index');
        }

        $contactMessage = $this->entityManager->getRepository(ContactMessage::class)->find($entityId);
        if (!$contactMessage) {
            $this->addFlash('error', 'Message non trouvé.');
            return $this->redirectToRoute('admin_contact_message_index');
        }

        if ($request->isMethod('POST')) {
            $subject = $request->request->get('subject');
            $message = $request->request->get('message');

            if (empty($subject) || empty($message)) {
                $this->addFlash('error', 'Le sujet et le message sont obligatoires.');
                return $this->redirectToRoute('admin', [
                    'crudAction' => 'reply',
                    'crudControllerFqcn' => 'App\\Controller\\Admin\\ContactMessageCrudController',
                    'entityId' => $entityId
                ]);
            }

            try {
                $contactMessage->setIsReplied(true);
                $contactMessage->setRepliedAt(new \DateTime());
                $contactMessage->setReplyMessage($message);
                $contactMessage->setRepliedBy($this->getUser());
                $this->entityManager->flush();

                $clientName = $contactMessage->getFirstName() . ' ' . $contactMessage->getLastName();
                $subjectLabels = [
                    'reservation' => 'Réservation',
                    'commande' => 'Commande',
                    'evenement_prive' => 'Événement privé',
                    'suggestion' => 'Suggestion',
                    'reclamation' => 'Réclamation',
                    'autre' => 'Autre'
                ];
                $subjectLabel = $subjectLabels[$contactMessage->getSubject()] ?? $contactMessage->getSubject();
                $emailSubject = "Re: Votre message concernant " . $subjectLabel;

                $emailSent = $this->emailService->sendReplyToClient(
                    $contactMessage->getEmail(),
                    $clientName,
                    $emailSubject,
                    $message
                );

                if ($emailSent) {
                    $this->addFlash('success', 'Réponse envoyée par email avec succès !');
                } else {
                    $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.');
                }

                return $this->redirectToRoute('admin_contact_message_index');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la sauvegarde: ' . $e->getMessage());
            }
        }

        return $this->render('admin/contact_message/reply.html.twig', [
            'contactMessage' => $contactMessage,
        ]);
    }
}
