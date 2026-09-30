<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClientRepository;
use sgoranov\IdentityLinkShared\Validator\JsonChoice;
use sgoranov\IdentityLinkShared\Validator\UniqueEntry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'client')]
#[OA\Schema(
    schema: "Client",
    title: "Client",
    description: "OAuth2 Client entity used for identity link authorization",
    required: ["audience"],
    properties: [
        new OA\Property(
            property: "id",
            description: "Client UUID",
            type: "string",
            format: "uuid"
        ),
        new OA\Property(
            property: "name",
            description: "Unique client name",
            type: "string",
            maxLength: 100
        ),
        new OA\Property(
            property: "description",
            description: "Client description",
            type: "string",
            maxLength: 3000
        ),
        new OA\Property(
            property: "audience",
            description: "HTTP or HTTPS URL identifying the resource server",
            type: "string",
            format: "uri",
            maxLength: 3000
        ),
        new OA\Property(
            property: "redirectUri",
            description: "Client redirect URIs",
            type: "array",
            items: new OA\Items(
                type: "string",
                format: "uri",
                maxLength: 3000
            )
        ),
        new OA\Property(
            property: "grantTypes",
            description: "Allowed OAuth2 grant types",
            type: "array",
            items: new OA\Items(
                type: "string",
                enum: ["client_credentials", "password", "authorization_code", "refresh_token", "implicit"]
            )
        ),
        new OA\Property(
            property: "groups",
            description: "Group IDs assigned to the client",
            type: "array",
            items: new OA\Items(type: "string")
        ),
        new OA\Property(
            property: "secrets",
            description: "Secret keys associated with the client",
            type: "array",
            items: new OA\Items(type: "string")
        ),
        new OA\Property(
            property: "isPublic",
            description: "Indicates whether the client is public",
            type: "boolean"
        ),
        new OA\Property(
            property: "isSystem",
            description: "Indicates whether the client is managed by the system and cannot be updated or deleted through the API",
            type: "boolean",
            readOnly: true
        ),
        new OA\Property(
            property: "consentRequired",
            description: "Indicates whether the OAuth client requires user consent to be granted via the consent screen during the authorization flow",
            type: "boolean"
        ),
        new OA\Property(
            property: "applicationUrl",
            description: "Application homepage URL",
            type: "string",
            format: "uri",
            nullable: true
        ),
        new OA\Property(
            property: "termsOfServiceUrl",
            description: "Terms of Service URL",
            type: "string",
            format: "uri",
            nullable: true
        ),
        new OA\Property(
            property: "privacyPolicyUrl",
            description: "Privacy Policy URL",
            type: "string",
            format: "uri",
            nullable: true
        ),
        new OA\Property(
            property: "logoUrl",
            description: "Application logo URL",
            type: "string",
            format: "uri",
            nullable: true
        )
    ]
)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: "doctrine.uuid_generator")]
    private ?string $id = null;

    #[Groups(['create', 'update'])]
    #[Assert\NotBlank(groups: ['create'])]
    #[UniqueEntry(groups: ['create', 'update'])]
    #[Assert\Length(min: 1, max: 100, groups: ['create', 'update'])]
    #[Assert\Regex(pattern: '/^([\w0-9_-])+$/u', groups: ['create', 'update'])]
    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    #[Groups(['create', 'update'])]
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(min: 1, max: 3000, groups: ['create', 'update'])]
    #[ORM\Column(length: 3000)]
    private string $description;

    #[Groups(['create'])]
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Url(protocols: ['http', 'https'], groups: ['create'])]
    #[Assert\Length(min: 1, max: 3000, groups: ['create'])]
    #[ORM\Column(length: 3000)]
    private string $audience;

    #[Groups(['create', 'update'])]
    #[Assert\Count(
        min: 1,
        max: 50,
        maxMessage: 'You cannot specify more than {{ limit }} redirect URIs',
        groups: ['create', 'update']
    )]
    #[Assert\All([
        new Assert\Url(groups: ['create', 'update']),
        new Assert\Length(min: 1, max: 3000, groups: ['create', 'update'])
    ])]
    #[ORM\Column(name: 'redirect_uri', type: 'json')]
    private array $redirectUri = [];

    #[Groups(['create', 'update'])]
    #[Assert\Count(
        min: 0,
        max: 50,
        maxMessage: 'You cannot specify more than {{ limit }} groups',
        groups: ['create', 'update']
    )]
    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: "clients")]
    #[ORM\JoinTable(name: "client_group")]
    private Collection $groups;

    #[Groups(['create', 'update'])]
    #[JsonChoice(
        choices: ['client_credentials', 'password', 'authorization_code', 'refresh_token', 'implicit'],
        groups: ['create', 'update']
    )]
    #[ORM\Column(type: 'json')]
    private array $grantTypes = [];

    #[Groups(['create', 'update'])]
    #[Assert\Count(
        min: 0,
        max: 50,
        maxMessage: 'You cannot specify more than {{ limit }} secrets',
        groups: ['create', 'update']
    )]
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Secret::class, cascade: ['persist', 'remove'])]
    private Collection $secrets;

    #[Groups(['create'])]
    #[Assert\NotNull(groups: ['create'])]
    #[ORM\Column]
    private bool $isPublic;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSystem = false;

    #[Groups(['create', 'update'])]
    #[ORM\Column(options: ['default' => false])]
    private bool $consentRequired = false;

    #[Groups(['create', 'update'])]
    #[Assert\Url(groups: ['create', 'update'])]
    #[Assert\Length(min: 1, max: 3000, groups: ['create', 'update'])]
    #[ORM\Column(length: 3000, nullable: true)]
    private ?string $applicationUrl = null;

    #[Groups(['create', 'update'])]
    #[Assert\Url(groups: ['create', 'update'])]
    #[Assert\Length(min: 1, max: 3000, groups: ['create', 'update'])]
    #[ORM\Column(length: 3000, nullable: true)]
    private ?string $termsOfServiceUrl = null;

    #[Groups(['create', 'update'])]
    #[Assert\Url(groups: ['create', 'update'])]
    #[Assert\Length(min: 1, max: 3000, groups: ['create', 'update'])]
    #[ORM\Column(length: 3000, nullable: true)]
    private ?string $privacyPolicyUrl = null;

    #[Groups(['create', 'update'])]
    #[Assert\Url(groups: ['create', 'update'])]
    #[Assert\Length(min: 1, max: 3000, groups: ['create', 'update'])]
    #[ORM\Column(length: 3000, nullable: true)]
    private ?string $logoUrl = null;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->secrets = new ArrayCollection();
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function setAudience(string $audience): void
    {
        $this->audience = $audience;
    }

    public function getRedirectUri(): array
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(array $redirectUri): void
    {
        $this->redirectUri = $redirectUri;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function setGroups(Collection $groups): void
    {
        $this->groups = $groups;
    }

    public function getGrantTypes(): array
    {
        return $this->grantTypes;
    }

    public function setGrantTypes(array $grantTypes): void
    {
        $this->grantTypes = $grantTypes;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
    }

    public function getIsSystem(): bool
    {
        return $this->isSystem;
    }

    public function getSecrets(): Collection
    {
        return $this->secrets;
    }

    public function setSecrets(Collection $secrets): void
    {
        $this->secrets = $secrets;
    }

    public function isConsentRequired(): bool
    {
        return $this->consentRequired;
    }

    public function setConsentRequired(bool $consentRequired): void
    {
        $this->consentRequired = $consentRequired;
    }

    public function getApplicationUrl(): ?string
    {
        return $this->applicationUrl;
    }

    public function setApplicationUrl(?string $applicationUrl): void
    {
        $this->applicationUrl = $applicationUrl;
    }

    public function getTermsOfServiceUrl(): ?string
    {
        return $this->termsOfServiceUrl;
    }

    public function setTermsOfServiceUrl(?string $termsOfServiceUrl): void
    {
        $this->termsOfServiceUrl = $termsOfServiceUrl;
    }

    public function getPrivacyPolicyUrl(): ?string
    {
        return $this->privacyPolicyUrl;
    }

    public function setPrivacyPolicyUrl(?string $privacyPolicyUrl): void
    {
        $this->privacyPolicyUrl = $privacyPolicyUrl;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): void
    {
        $this->logoUrl = $logoUrl;
    }
}
