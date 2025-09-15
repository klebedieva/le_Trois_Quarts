<?php

namespace App\Entity;

use App\Repository\ContactMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: "Le prénom est requis")]
    #[Assert\Regex(pattern: "/^[a-zA-ZÀ-ÿ\s\-]+$/", message: "Le prénom ne peut contenir que des lettres, espaces et tirets")]
    private ?string $firstName = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: "Le nom est requis")]
    #[Assert\Regex(pattern: "/^[a-zA-ZÀ-ÿ\s\-]+$/", message: "Le nom ne peut contenir que des lettres, espaces et tirets")]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'email est requis")]
    #[Assert\Regex(pattern: "/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/", message: "L'email n'est pas valide")]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Regex(pattern: "/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.\-]*\d{2}){4}$/", message: "Le numéro de téléphone n'est pas valide")]
    private ?string $phone = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Veuillez sélectionner un sujet")]
    #[Assert\Choice(choices: [
        'reservation','commande','evenement_prive','suggestion','reclamation','autre'
    ], message: "Veuillez sélectionner un sujet valide")]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le message est requis")]
    #[Assert\Regex(pattern: "/^(?!.*<.*?>)[\s\S]{10,1000}$/", message: "Le message doit contenir entre 10 et 1000 caractères et ne peut pas contenir de balises HTML")]
    private ?string $message = null;

    #[ORM\Column(type: 'boolean')]
    #[Assert\IsTrue(message: "Vous devez accepter d'être contacté.")]
    private bool $consent = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isReplied = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $repliedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $replyMessage = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $repliedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): static { $this->lastName = $lastName; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(string $subject): static { $this->subject = $subject; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function isConsent(): bool { return $this->consent; }
    public function setConsent(bool $consent): static { $this->consent = $consent; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function isReplied(): bool { return $this->isReplied; }
    public function setIsReplied(bool $isReplied): static { $this->isReplied = $isReplied; return $this; }

    public function getRepliedAt(): ?\DateTimeInterface { return $this->repliedAt; }
    public function setRepliedAt(?\DateTimeInterface $repliedAt): static { $this->repliedAt = $repliedAt; return $this; }

    public function getReplyMessage(): ?string { return $this->replyMessage; }
    public function setReplyMessage(?string $replyMessage): static { $this->replyMessage = $replyMessage; return $this; }

    public function getRepliedBy(): ?User { return $this->repliedBy; }
    public function setRepliedBy(?User $repliedBy): static { $this->repliedBy = $repliedBy; return $this; }
}
