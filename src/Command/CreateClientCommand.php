<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Secret;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-client',
    description: 'Create a private client with an auto-generated secret and attach groups.'
)]
final class CreateClientCommand extends AbstractCommand
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ) {
        parent::__construct($validator);
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Unique client name.')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Client description.')
            ->addOption('audience', null, InputOption::VALUE_REQUIRED, 'HTTP or HTTPS resource-server audience.')
            ->addOption(
                'redirect-uri',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Redirect URI. May be repeated; defaults to the audience.',
            )
            ->addOption(
                'grant-type',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'OAuth2 grant type. May be repeated; defaults to client_credentials.',
            )
            ->addOption(
                'group',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Group name to attach. Must be provided at least once and may be repeated.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach (['name', 'description', 'audience'] as $option) {
            if (!$this->isValidStringOption($input, $option)) {
                $io->error(sprintf('The --%s option is required.', $option));

                return Command::INVALID;
            }
        }

        $groupNames = array_values(array_unique($input->getOption('group')));
        if ($groupNames === []) {
            $io->error('At least one --group option is required.');

            return Command::INVALID;
        }

        $groups = $this->resolveGroupNames($io, $groupNames);
        if ($groups === null) {
            return Command::INVALID;
        }

        $name = $input->getOption('name');
        $description = $input->getOption('description');
        $audience = $input->getOption('audience');
        $redirectUris = array_values(array_unique($input->getOption('redirect-uri')));
        if ($redirectUris === []) {
            $redirectUris = [$audience];
        }
        $grantTypes = array_values(array_unique($input->getOption('grant-type')));
        if ($grantTypes === []) {
            $grantTypes = ['client_credentials'];
        }

        try {
            [$client, $password] = $this->entityManager->wrapInTransaction(
                fn (): array => $this->createClient(
                    $io,
                    $name,
                    $description,
                    $audience,
                    $redirectUris,
                    $grantTypes,
                    $groups,
                )
            );
        } catch (ValidationFailedException) {
            return Command::INVALID;
        }

        $io->success(sprintf('Client "%s" was created.', $client->getName()));
        $io->definitionList(
            ['Client ID' => $client->getId()],
            ['Client secret' => $password],
        );
        $io->warning('Store the client secret securely; it will not be shown again.');

        return Command::SUCCESS;
    }

    private function createClient(
        SymfonyStyle $io,
        string $name,
        string $description,
        string $audience,
        array $redirectUris,
        array $grantTypes,
        array $groups,
    ): array
    {
        $client = new Client();
        $client->setName($name);
        $client->setDescription($description);
        $client->setAudience($audience);
        $client->setRedirectUri($redirectUris);
        $client->setGrantTypes($grantTypes);
        $client->setGroups(new ArrayCollection($groups));
        $client->setIsPublic(false);

        $this->assertEntityIsValid($io, $client);

        $secret = new Secret();
        $secret->setClient($client);
        $secret->setPasswordHint('auto generated');
        $secret->setExpirationPeriod('1y');

        $this->assertEntityIsValid($io, $secret, ['secret_issue_request']);

        $password = bin2hex(random_bytes(32));
        $secret->setPassword($password);
        $secret->setExpirationDateTime((new \DateTime())->add(new \DateInterval('P1Y')));

        $client->setSecrets(new ArrayCollection([$secret]));
        $this->entityManager->persist($client);

        return [$client, $password];
    }

    private function resolveGroupNames(SymfonyStyle $io, array $groupNames): ?array
    {
        $groups = [];

        foreach ($groupNames as $groupName) {
            if (!is_string($groupName) || trim($groupName) === '') {
                $io->error('Group names must not be empty.');

                return null;
            }

            $group = $this->groupRepository->findOneBy(['name' => $groupName]);
            if (!$group instanceof Group) {
                $io->error(sprintf('Group "%s" was not found.', $groupName));

                return null;
            }

            $groups[] = $group;
        }

        return $groups;
    }
}
