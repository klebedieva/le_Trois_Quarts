<?php

namespace App\Service;

use App\DTO\ContactCreateRequest;
use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Contact Service
 *
 * Encapsulates creation and persistence of ContactMessage entities.
 * Controllers should delegate to this service instead of calling persist()/flush() directly.
 */
class ContactService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Create and persist a ContactMessage from validated DTO.
     *
     * Side effects:
     * - Creates new ContactMessage entity
     * - Persists entity to database (persist + flush)
     *
     * @param ContactCreateRequest $dto Validated contact form request DTO
     * @return ContactMessage Persisted contact message entity
     */
    public function createContactMessage(ContactCreateRequest $dto): ContactMessage
    {
        $message = new ContactMessage();
        $message->setFirstName($dto->firstName);
        $message->setLastName($dto->lastName);
        $message->setEmail($dto->email);
        $message->setPhone($dto->phone);
        $message->setSubject($dto->subject);
        $message->setMessage($dto->message);
        $message->setConsent((bool) ($dto->consent ?? false));

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * Persist a ContactMessage entity coming from legacy form handling.
     *
     * This method is used for form-based submissions where entity is pre-populated.
     *
     * Side effects:
     * - Persists entity to database (persist + flush)
     *
     * @param ContactMessage $message Pre-populated contact message entity from form
     * @return ContactMessage Persisted contact message entity
     */
    public function createContactMessageFromEntity(ContactMessage $message): ContactMessage
    {
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }
}
