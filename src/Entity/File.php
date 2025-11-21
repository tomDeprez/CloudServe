<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileRepository::class)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    private ?string $storedName = null;

    #[ORM\Column(length: 100)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'bigint')]
    private ?string $size = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20, options: ['default' => 'file'])]
    private string $type = 'file'; // 'file' ou 'folder'

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?File $parent = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null; // Contenu pour les fichiers texte éditables

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null; // Chemin vers la miniature

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hash = null; // Hash SHA256 du fichier pour détecter les doublons

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $originalHash = null; // Hash SHA256 du fichier AVANT compression

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $processing = false; // Indique si le fichier est en cours de traitement (compression, miniatures)

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->processing = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;
        return $this;
    }

    public function getStoredName(): ?string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): static
    {
        $this->storedName = $storedName;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(string $size): static
    {
        $this->size = $size;
        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    public function getParent(): ?File
    {
        return $this->parent;
    }

    public function setParent(?File $parent): static
    {
        $this->parent = $parent;
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

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): static
    {
        $this->hash = $hash;
        return $this;
    }

    public function getOriginalHash(): ?string
    {
        return $this->originalHash;
    }

    public function setOriginalHash(?string $originalHash): static
    {
        $this->originalHash = $originalHash;
        return $this;
    }

    public function isProcessing(): bool
    {
        return $this->processing;
    }

    public function setProcessing(bool $processing): static
    {
        $this->processing = $processing;
        return $this;
    }

    public function isEditable(): bool
    {
        // Fichiers éditables : txt, md, json, xml, csv, etc.
        $editableExtensions = ['txt', 'md', 'json', 'xml', 'csv', 'log', 'html', 'css', 'js', 'php', 'yml', 'yaml'];
        $extension = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
        return in_array($extension, $editableExtensions);
    }

    public function getFileType(): string
    {
        if ($this->isFolder()) {
            return 'folder';
        }

        $extension = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
        $mimeType = $this->mimeType;

        // Images
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']) ||
            str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        // Audio - check MIME type first for ambiguous extensions like .ogg
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        if (in_array($extension, ['mp3', 'wav', 'flac', 'm4a', 'aac'])) {
            return 'audio';
        }

        // Vidéos
        if (in_array($extension, ['mp4', 'webm', 'avi', 'mov', 'mkv']) ||
            str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        // OGG can be both audio or video, default to video if mime type not available
        if ($extension === 'ogg') {
            return 'video';
        }

        // PDF
        if ($extension === 'pdf' || $mimeType === 'application/pdf') {
            return 'pdf';
        }

        // Texte
        if ($this->isEditable() || str_starts_with($mimeType, 'text/')) {
            return 'text';
        }

        return 'other';
    }
}
