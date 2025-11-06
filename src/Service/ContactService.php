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
     * @param ContactCreateRequest $dto Validated request DTO
     * @return ContactMessage Persisted entity
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

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * Persist a ContactMessage entity coming from legacy form handling.
     */
    public function createContactMessageFromEntity(ContactMessage $message): ContactMessage
    {
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }
}
