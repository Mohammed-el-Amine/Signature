<?php

namespace App\Entity;

use App\Repository\HtmlSignatureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HtmlSignatureRepository::class)]
class HtmlSignature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user_id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $job_title = null;

    #[ORM\Column(length: 255)]
    private ?string $organization = null;

    #[ORM\Column(length: 255)]
    private ?string $adress = null;

    #[ORM\Column(length: 255)]
    private ?string $postal_code = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $phone = null;

    #[ORM\ManyToMany(targetEntity: Logo::class)]
    private Collection $logo_id;

    #[ORM\Column(nullable: true)]
    private array $social_link = [];

    #[ORM\Column(type: Types::TEXT)]
    private ?string $html_code = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $baniere = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $create_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $update_at;

    public function __construct()
    {
        $this->logo_id = new ArrayCollection();
        $this->create_at = new \DateTimeImmutable();
        $this->update_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?User
    {
        return $this->user_id;
    }

    public function setUserId(?User $user_id): self
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->job_title;
    }

    public function setJobTitle(string $job_title): self
    {
        $this->job_title = $job_title;

        return $this;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(string $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getAdress(): ?string
    {
        return $this->adress;
    }

    public function setAdress(string $adress): self
    {
        $this->adress = $adress;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postal_code;
    }

    public function setPostalCode(string $postal_code): self
    {
        $this->postal_code = $postal_code;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * @return Collection<int, logo>
     */
    public function getLogoId(): Collection
    {
        return $this->logo_id;
    }

    public function addLogoId(logo $logoId): self
    {
        if (!$this->logo_id->contains($logoId)) {
            $this->logo_id->add($logoId);
        }

        return $this;
    }

    public function removeLogoId(logo $logoId): self
    {
        $this->logo_id->removeElement($logoId);

        return $this;
    }

    public function getSocialLink(): array
    {
        return $this->social_link;
    }

    public function setSocialLink(?array $social_link = []): self
    {
        $this->social_link = $social_link;

        return $this;
    }

    public function getHtmlCode(): ?string
    {
        return $this->html_code;
    }

    public function setHtmlCode(string $html_code): self
    {
        $this->html_code = $html_code;

        return $this;
    }

    public function getBaniere(): ?string
    {
        return $this->baniere;
    }

    public function setBaniere(?string $baniere): self
    {
        $this->baniere = $baniere;

        return $this;
    }

    public function getCreateAt(): ?\DateTimeImmutable
    {
        return $this->create_at;
    }

    public function getUpdateAt(): ?\DateTimeImmutable
    {
        return $this->update_at;
    }
}
