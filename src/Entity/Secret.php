<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\SecretRepository;
use sgoranov\IdentityLinkShared\Security\PasswordHashGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[ORM\Entity(repositoryClass: SecretRepository::class)]
#[ORM\Table(name: 'secret')]
#[OA\Schema(
    schema: "Secret",
    title: "Secret",
    description: "Represents a client's secret, including password hint and expiration info.",
    properties: [
        new OA\Property(
            property: "id",
            description: "Unique identifier of the secret",
            type: "string",
            format: "uuid"
        ),
        new OA\Property(
            property: "password",
            description: "Plaintext password (write-only)",
            type: "string",
            maxLength: 50,
            writeOnly: true
        ),
        new OA\Property(
            property: "passwordHint",
            description: "Hint to help recall the password",
            type: "string",
            maxLength: 500
        ),
        new OA\Property(
            property: "expirationDateTime",
            description: "Expiration timestamp for the secret",
            type: "string",
            format: "date-time"
        ),
        new OA\Property(
            property: "client",
            ref: "#/components/schemas/Client",
            description: "Client to which the secret belongs"
        ),
        new OA\Property(
            property: "isSystem",
            description: "Indicates whether the secret is managed by the system and cannot be updated or deleted through the API",
            type: "boolean",
            readOnly: true
        )
    ]
)]
class Secret
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: "doctrine.uuid_generator")]
    private ?string $id = null;

    #[Ignore]
    #[ORM\Column(name: 'password', length: 100)]
    private string $hashedPassword;

    #[Groups(['create', 'secret_issue_response'])]
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(min: 1, max: 50, groups: ['create'])]
    private string $password;

    #[Groups(['create', 'update', 'secret_issue_request', 'response_without_password'])]
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(min: 1, max: 500, groups: ['create', 'update'])]
    #[ORM\Column(length: 500)]
    private ?string $passwordHint = null;

    #[Groups(['create', 'response_without_password'])]
    #[Assert\NotNull(groups: ['create'])]
    #[Assert\GreaterThanOrEqual("now", message: "The date must be greater than or equal to now.", groups: ['create'])]
    #[ORM\Column]
    private \DateTime $expirationDateTime;

    #[Groups(['secret_issue_request', 'secret_issue_response'])]
    #[Assert\NotBlank(groups: ['secret_issue_request'])]
    #[Assert\Choice(
        choices: ['1d', '1w', '1m', '3m', '9m', '1y', '2y'],
        message: 'Choose one of: 1d, 1w, 1m, 3m, 9m, 1y, 2y.',
        groups: ['secret_issue_request']
    )]
    private ?string $expirationPeriod = null;

    #[Groups(['create', 'secret_issue_request', 'response_without_password'])]
    #[Assert\NotBlank(groups: ['create'])]
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'secrets')]
    private Client $client;

    #[Groups(['response_without_password', 'secret_issue_response'])]
    #[ORM\Column(options: ['default' => false])]
    private bool $isSystem = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
        $this->hashedPassword = PasswordHashGenerator::create($password);
    }

    public function getPasswordHint(): ?string
    {
        return $this->passwordHint;
    }

    public function setPasswordHint(string $passwordHint): void
    {
        $this->passwordHint = $passwordHint;
    }

    public function getExpirationDateTime(): \DateTime
    {
        return $this->expirationDateTime;
    }

    public function setExpirationDateTime(\DateTime $expirationDateTime): void
    {
        $this->expirationDateTime = $expirationDateTime;
    }

    public function getExpirationPeriod(): ?string
    {
        return $this->expirationPeriod;
    }

    public function setExpirationPeriod(?string $expirationPeriod): void
    {
        $this->expirationPeriod = $expirationPeriod;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function getIsSystem(): bool
    {
        return $this->isSystem;
    }
}
