<?php

namespace App\Entity;

use App\Repository\RankingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RankingRepository::class)]
class Ranking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rankings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'rankings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, RankingItem>
     */
    #[ORM\OneToMany(targetEntity: RankingItem::class, mappedBy: 'ranking')]
    private Collection $rankingItems;

    public function __construct()
    {
        $this->rankingItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /**
     * @return Collection<int, RankingItem>
     */
    public function getRankingItems(): Collection
    {
        return $this->rankingItems;
    }

    public function addRankingItem(RankingItem $rankingItem): static
    {
        if (!$this->rankingItems->contains($rankingItem)) {
            $this->rankingItems->add($rankingItem);
            $rankingItem->setRanking($this);
        }

        return $this;
    }

    public function removeRankingItem(RankingItem $rankingItem): static
    {
        if ($this->rankingItems->removeElement($rankingItem)) {
            // set the owning side to null (unless already changed)
            if ($rankingItem->getRanking() === $this) {
                $rankingItem->setRanking(null);
            }
        }

        return $this;
    }
}
