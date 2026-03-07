<?php

namespace App\Entity;

use App\Repository\SupporterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SupporterRepository::class)]
#[ORM\Table(name: 'supporter')]
#[ORM\UniqueConstraint(name: 'uniq_supporter_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est deja enregistree.')]
class Supporter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column]
    private bool $agreesToCharter = false;

    #[ORM\Column]
    private bool $acceptsFutureContact = false;

    #[ORM\Column]
    private bool $isConfirmed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmationSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipHash = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function isAgreesToCharter(): bool
    {
        return $this->agreesToCharter;
    }

    public function setAgreesToCharter(bool $agreesToCharter): static
    {
        $this->agreesToCharter = $agreesToCharter;

        return $this;
    }

    public function isAcceptsFutureContact(): bool
    {
        return $this->acceptsFutureContact;
    }

    public function setAcceptsFutureContact(bool $acceptsFutureContact): static
    {
        $this->acceptsFutureContact = $acceptsFutureContact;

        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function setIsConfirmed(bool $isConfirmed): static
    {
        $this->isConfirmed = $isConfirmed;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmationSentAt(): ?\DateTimeImmutable
    {
        return $this->confirmationSentAt;
    }

    public function setConfirmationSentAt(?\DateTimeImmutable $confirmationSentAt): static
    {
        $this->confirmationSentAt = $confirmationSentAt;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(?string $ipHash): static
    {
        $this->ipHash = $ipHash;

        return $this;
    }
}
