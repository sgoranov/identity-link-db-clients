<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use sgoranov\IdentityLinkShared\Validator\UniqueEntry;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use SortDirection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
#[OA\Schema(
    schema: "Group",
    title: "Group",
    description: "Permission or access group that can be assigned to clients.",
    required: ["name"],
    properties: [
        new OA\Property(
            property: "id",
            description: "Unique identifier for the group",
            type: "string",
            format: "uuid"
        ),
        new OA\Property(
            property: "name",
            description: "Group name (must be unique)",
            type: "string",
            maxLength: 100
        ),
        new OA\Property(
            property: "isSystem",
            description: "Indicates whether the group is managed by the system and cannot be updated or deleted through the API",
            type: "boolean",
            readOnly: true
        ),
        new OA\Property(
            property: 'scopes',
            description: 'Scopes issued for this group and their protected-resource audiences',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/GroupScope')
        )
    ]
)]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: "doctrine.uuid_generator")]
    private ?string $id = null;

    #[Groups(['create', 'update'])]
    #[UniqueEntry(groups: ['create', 'update'])]
    #[Assert\NotBlank(groups: ['create', 'update'])]
    #[Assert\Length(min: 1, max: 100, groups: ['create', 'update'])]
    #[Assert\Regex(pattern: '/^([\.\w0-9_ :-])+$/u', groups: ['create', 'update'])]
    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSystem = false;

    #[Ignore]
    #[ORM\ManyToMany(targetEntity: Client::class, mappedBy: "groups")]
    private Collection $clients;

    /** @var Collection<int, GroupScope> */
    #[ORM\OneToMany(
        targetEntity: GroupScope::class,
        mappedBy: 'group',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['audience' => SortDirection::Ascending])]
    private Collection $scopes;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->scopes = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIsSystem(): bool
    {
        return $this->isSystem;
    }

    /** @return Collection<int, GroupScope> */
    public function getScopes(): Collection
    {
        return $this->scopes;
    }

    public function addScope(GroupScope $scope): void
    {
        if (!$this->scopes->contains($scope)) {
            $this->scopes->add($scope);
            $scope->setGroup($this);
        }
    }

    public function removeScope(GroupScope $scope): void
    {
        $this->scopes->removeElement($scope);
    }
}
