<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'sentMessages')]
    #[ORM\JoinColumn(name: 'senderCin', referencedColumnName: 'cin', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $senderCin = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'receivedMessages')]
    #[ORM\JoinColumn(name: 'receiverCin', referencedColumnName: 'cin', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $receiverCin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    public function __construct()
    {
        $this->timestamp = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSenderCin(): ?Utilisateur
    {
        return $this->senderCin;
    }

    public function setSenderCin(?Utilisateur $senderCin): static
    {
        $this->senderCin = $senderCin;

        return $this;
    }

    public function getReceiverCin(): ?Utilisateur
    {
        return $this->receiverCin;
    }

    public function setReceiverCin(?Utilisateur $receiverCin): static
    {
        $this->receiverCin = $receiverCin;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;

        return $this;
    }
}