<?php

namespace App\Entity;

use App\EntityListener\ActivityListener;
use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\EntityListeners([ActivityListener::class])]
#[ORM\UniqueConstraint(columns: ['name', 'starting_date', 'place_id', 'organizer_id'])]
#[UniqueEntity(fields: ['name', 'starting_date', 'place'], message: "Une sortie identique est déjà proposée !")]
class Activity
{

    private const IS_ARCHIVED = false;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Assert\Type(type: 'string', message: "Veuillez renseigner une chaine de caractère")]
    #[Assert\NotBlank(message: "Veuillez remplir ce champ")]
    #[Assert\Length(
        min: 3,
        minMessage: "C'est trop court, il faut {{ limit }} caractère minimum",
        max: 30,
        maxMessage: "C'est trop long, il faut {{ limit }} caractère maximum",
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\Type(type: "\DateTimeInterface", message: 'Veuillez saisir une date valide')]
    #[Assert\NotBlank(message: "Veuillez remplir ce champ")]
    #[Assert\GreaterThan(value: 'today', message: "Veuillez saisir une date postérieure à celle d'aujourd'hui")]
    private ?\DateTimeInterface $starting_date = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(type: 'int', message: "Veuillez renseigner un nombre")]
    #[Assert\Range(min:0, max: 24, notInRangeMessage: "L'activité ne peut pas durer plus de 24 heures")]
    private ?int $duration_hours = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\Type(type: "\DateTimeInterface", message: 'Veuillez saisir une date valide')]
    #[Assert\NotBlank(message: "Veuillez remplir ce champ")]
    #[Assert\LessThan(propertyPath: 'starting_date', message: "Veuillez saisir une date antérieure à celle du départ de l'activité")]
    private ?\DateTimeInterface $registration_limit_date = null;

    #[ORM\Column]
    #[Assert\Type(type: 'int', message: "Veuillez renseigner un nombre")]
    #[Assert\NotBlank(message: "Veuillez remplir ce champ")]
    private ?int $registration_max_nb = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Type(type: 'string', message: "Veuillez renseigner une chaine de caractère")]
    #[Assert\Length(
        max: 500,
        maxMessage: "C'est trop long, il faut {{ limit }} caractère maximum",
    )]
    private ?string $description = null;

    #[ORM\Column(length: 250, nullable: true)]
    #[Assert\Type(type: 'string', message: "Veuillez renseigner une chaine de caractère")]
    #[Assert\Length(
        max: 250,
        maxMessage: "C'est trop long, il faut {{ limit }} caractère maximum",
    )]
    private ?string $photo_url = null;

    #[ORM\Column]
    #[Assert\Type('bool')]
    private ?bool $is_archived = self::IS_ARCHIVED;

    #[ORM\ManyToOne(inversedBy: 'activities_organizer')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $organizer = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(name: 'place_id', nullable: false)]
    private ?Place $place = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(name: 'state_id', nullable: false)]
    private ?State $state = null;

    /**
     * @var Collection<int, Registration>
     */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'activity')]
    private Collection $registrations;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancelReason = null;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStartingDate(): ?\DateTimeInterface
    {
        return $this->starting_date;
    }

    public function setStartingDate(\DateTimeInterface $starting_date): static
    {
        $this->starting_date = $starting_date;

        return $this;
    }

    public function getDurationHours(): ?int
    {
        return $this->duration_hours;
    }

    public function setDurationHours(?int $duration): static
    {
        $this->duration_hours = $duration;

        return $this;
    }

    public function getRegistrationLimitDate(): ?\DateTimeInterface
    {
        return $this->registration_limit_date;
    }

    public function setRegistrationLimitDate(\DateTimeInterface $registration_limit_date): static
    {
        $this->registration_limit_date = $registration_limit_date;

        return $this;
    }

    public function getRegistrationMaxNb(): ?int
    {
        return $this->registration_max_nb;
    }

    public function setRegistrationMaxNb(int $registration_max_nb): static
    {
        $this->registration_max_nb = $registration_max_nb;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photo_url;
    }

    public function setPhotoUrl(?string $photo_url): static
    {
        $this->photo_url = $photo_url;

        return $this;
    }

    public function isArchived(): ?bool
    {
        return $this->is_archived;
    }

    public function setArchived(bool $is_archived): static
    {
        $this->is_archived = $is_archived;

        return $this;
    }

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function setOrganizer(?User $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): static
    {
        $this->place = $place;

        return $this;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(?State $state): static
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return Collection<int, Registration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addRegistration(Registration $registration): static
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations->add($registration);
            $registration->setActivity($this);
        }

        return $this;
    }

    public function removeRegistration(Registration $registration): static
    {
        if ($this->registrations->removeElement($registration)) {
            // set the owning side to null (unless already changed)
            if ($registration->getActivity() === $this) {
                $registration->setActivity(null);
            }
        }

        return $this;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function setCancelReason(?string $cancelReason): static
    {
        $this->cancelReason = $cancelReason;

        return $this;
    }
}
