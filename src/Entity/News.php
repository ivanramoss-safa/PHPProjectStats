<?php

namespace App\Entity;

use App\Repository\NewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: NewsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class News
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    private ?User $author = null;
    
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $playerIds = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $teamIds = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $leagueIds = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $coachIds = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $venueIds = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $fixtureIds = [];

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $category = null; 

    #[ORM\Column]
    private bool $featured = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getPlayerIds(): array
    {
        return $this->playerIds ?? [];
    }

    public function setPlayerIds(?array $playerIds): static
    {
        $this->playerIds = $playerIds;
        return $this;
    }

    public function getTeamIds(): array
    {
        return $this->teamIds ?? [];
    }

    public function setTeamIds(?array $teamIds): static
    {
        $this->teamIds = $teamIds;
        return $this;
    }

    public function getLeagueIds(): array
    {
        return $this->leagueIds ?? [];
    }

    public function setLeagueIds(?array $leagueIds): static
    {
        $this->leagueIds = $leagueIds;
        return $this;
    }

    public function getCoachIds(): array
    {
        return $this->coachIds ?? [];
    }

    public function setCoachIds(?array $coachIds): static
    {
        $this->coachIds = $coachIds;
        return $this;
    }

    public function getVenueIds(): array
    {
        return $this->venueIds ?? [];
    }

    public function setVenueIds(?array $venueIds): static
    {
        $this->venueIds = $venueIds;
        return $this;
    }

    public function getFixtureIds(): array
    {
        return $this->fixtureIds ?? [];
    }

    public function setFixtureIds(?array $fixtureIds): static
    {
        $this->fixtureIds = $fixtureIds;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;
        return $this;
    }
}
