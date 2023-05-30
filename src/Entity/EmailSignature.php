<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\EmailSignatureRepository;
use App\Entity\User;

#[ORM\Entity(repositoryClass: EmailSignatureRepository::class)]
#[ORM\Table(name: "email_signature")]
class EmailSignature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 255)]
    private $name;

    #[ORM\Column(type: "string", length: 255)]
    private $jobTitle;

    #[ORM\Column(type: "string", length: 255)]
    private $organization;

    #[ORM\Column(type: "string", length: 255)]
    private $address;

    #[ORM\Column(type: "string", length: 255)]
    private $city;

    #[ORM\Column(type: "string", length: 255)]
    private $postalCode;

    #[ORM\Column(type: "string", length: 255)]
    private $email;

    #[ORM\Column(type: "string", length: 255)]
    private $phone;

    #[ORM\Column(type: "text")]
    private $signatureHtml;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false)]
    private $user;

    public function getId(): ?int
    {
        return $this->id;
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
        return $this->jobTitle;
    }

    public function setJobTitle(string $jobTitle): self
    {
        $this->jobTitle = $jobTitle;

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

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

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;

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

    public function getSignatureHtml(): ?string
    {
        return $this->signatureHtml;
    }

    public function setSignatureHtml(string $signatureHtml): self
    {
        $this->signatureHtml = $signatureHtml;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
