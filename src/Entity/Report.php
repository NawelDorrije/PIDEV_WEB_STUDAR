<?php
namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'reported_by_cin', referencedColumnName: 'cin', onDelete: 'CASCADE', nullable: false)]
    #[Assert\NotNull(message: 'Un signalement doit être associé à un utilisateur.')]
    private Utilisateur $reportedBy;

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(name: 'message_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    #[Assert\NotNull(message: 'Un signalement doit être associé à un message.')]
    private Message $message;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La raison du signalement ne peut pas être vide.')]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isResolved = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $isLegitimate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $analyzedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isResolved = false;
        $this->isLegitimate = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReportedBy(): Utilisateur
    {
        return $this->reportedBy;
    }

    public function setReportedBy(Utilisateur $reportedBy): self
    {
        $this->reportedBy = $reportedBy;
        return $this;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function setIsResolved(bool $isResolved): self
    {
        $this->isResolved = $isResolved;
        return $this;
    }

    public function getIsLegitimate(): ?bool
    {
        return $this->isLegitimate;
    }

    public function setIsLegitimate(?bool $isLegitimate): self
    {
        $this->isLegitimate = $isLegitimate;
        return $this;
    }

    public function getAnalyzedAt(): ?\DateTimeInterface
    {
        return $this->analyzedAt;
    }

    public function setAnalyzedAt(?\DateTimeInterface $analyzedAt): self
    {
        $this->analyzedAt = $analyzedAt;
        return $this;
    }
}