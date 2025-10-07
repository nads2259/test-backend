<?php

namespace App\Entity;

use App\Repository\UploadedDependencyFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UploadedDependencyFileRepository::class)]
class UploadedDependencyFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $filePath;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $debrickedUploadId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $vulnerabilityCount = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $scanResultPayload = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private string $storedFilename;

    #[ORM\OneToMany(mappedBy: 'uploadedFile', targetEntity: ScanResult::class, cascade: ['persist', 'remove'])]
    private Collection $scanResults;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->scanResults = new ArrayCollection();
    }

    // --- Relationship accessors ---
    public function getScanResults(): Collection
    {
        return $this->scanResults;
    }

    public function addScanResult(ScanResult $scanResult): self
    {
        if (!$this->scanResults->contains($scanResult)) {
            $this->scanResults[] = $scanResult;
            $scanResult->setUploadedFile($this);
        }

        return $this;
    }

    public function removeScanResult(ScanResult $scanResult): self
    {
        if ($this->scanResults->removeElement($scanResult)) {
            if ($scanResult->getUploadedFile() === $this) {
                $scanResult->setUploadedFile(null);
            }
        }

        return $this;
    }

    // --- Other getters/setters (unchanged) ---
    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(string $storedFilename): self
    {
        $this->storedFilename = $storedFilename;
        return $this;
    }

    public function getFullPath(string $uploadDir): string
    {
        return rtrim($uploadDir, '/') . '/' . $this->storedFilename;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $filename): self
    {
        $this->originalFilename = $filename;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDebrickedUploadId(): ?string
    {
        return $this->debrickedUploadId;
    }

    public function setDebrickedUploadId(?string $id): self
    {
        $this->debrickedUploadId = $id;
        return $this;
    }

    public function getVulnerabilityCount(): ?int
    {
        return $this->vulnerabilityCount;
    }

    public function setVulnerabilityCount(?int $count): self
    {
        $this->vulnerabilityCount = $count;
        return $this;
    }

    public function getScanResultPayload(): ?array
    {
        return $this->scanResultPayload;
    }

    public function setScanResultPayload(?array $payload): self
    {
        $this->scanResultPayload = $payload;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $msg): self
    {
        $this->errorMessage = $msg;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
