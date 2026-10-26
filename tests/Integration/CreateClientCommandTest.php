<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\AppFixtures;
use App\Entity\Client;
use App\Entity\Group;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateClientCommandTest extends KernelTestCase
{
    private const AUDIENCE = 'https://example.com/orders';

    public function testCreatesClientWithGeneratedSecretAndGroups(): void
    {
        self::bootKernel();
        $secondGroup = new Group();
        $secondGroup->setName('orders');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($secondGroup);
        $entityManager->flush();

        $tester = $this->commandTester(false);
        $status = $tester->execute([
            '--name' => 'orders_service',
            '--description' => 'Orders service client',
            '--audience' => self::AUDIENCE,
            '--redirect-uri' => ['https://example.com/callback'],
            '--grant-type' => ['client_credentials', 'authorization_code', 'client_credentials'],
            '--group' => [AppFixtures::GROUP_NAME, 'orders', AppFixtures::GROUP_NAME],
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Client "orders_service" was created.', $tester->getDisplay());
        self::assertMatchesRegularExpression('/Client secret\s+[a-f0-9]{64}/', $tester->getDisplay());

        $client = static::getContainer()->get(ClientRepository::class)->findOneBy(['name' => 'orders_service']);
        self::assertInstanceOf(Client::class, $client);
        self::assertSame('Orders service client', $client->getDescription());
        self::assertSame(self::AUDIENCE, $client->getAudience());
        self::assertSame(['https://example.com/callback'], $client->getRedirectUri());
        self::assertSame(['client_credentials', 'authorization_code'], $client->getGrantTypes());
        self::assertFalse($client->isPublic());
        self::assertCount(2, $client->getGroups());
        self::assertCount(1, $client->getSecrets());

        preg_match('/Client secret\s+([a-f0-9]{64})/', $tester->getDisplay(), $matches);
        self::assertNotNull(static::getContainer()->get(ClientRepository::class)->getClientByIdAndSecret(
            $client->getId(),
            $matches[1],
            'client_credentials',
        ));
    }

    public function testUsesAudienceAsDefaultRedirectUri(): void
    {
        $tester = $this->commandTester();

        $status = $tester->execute([
            '--name' => 'orders_service',
            '--description' => 'Orders service client',
            '--audience' => self::AUDIENCE,
            '--group' => [AppFixtures::GROUP_NAME],
        ]);

        self::assertSame(Command::SUCCESS, $status);
        $client = static::getContainer()->get(ClientRepository::class)->findOneBy(['name' => 'orders_service']);
        self::assertInstanceOf(Client::class, $client);
        self::assertSame([self::AUDIENCE], $client->getRedirectUri());
    }

    public function testCreatesClientWithHttpAudience(): void
    {
        $audience = 'http://localhost:8000';
        $tester = $this->commandTester();

        $status = $tester->execute([
            '--name' => 'local_service',
            '--description' => 'Local service client',
            '--audience' => $audience,
            '--group' => [AppFixtures::GROUP_NAME],
        ]);

        self::assertSame(Command::SUCCESS, $status);
        $client = static::getContainer()->get(ClientRepository::class)->findOneBy(['name' => 'local_service']);
        self::assertInstanceOf(Client::class, $client);
        self::assertSame($audience, $client->getAudience());
    }

    public function testRejectsUnknownGroupWithoutCreatingClient(): void
    {
        $tester = $this->commandTester();

        $status = $tester->execute([
            '--name' => 'orders_service',
            '--description' => 'Orders service client',
            '--audience' => self::AUDIENCE,
            '--group' => ['unknown'],
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('Group "unknown" was not found.', $tester->getDisplay());
        self::assertNull(static::getContainer()->get(ClientRepository::class)
            ->findOneBy(['name' => 'orders_service']));
    }

    public function testRejectsMissingGroupsWithoutCreatingClient(): void
    {
        $tester = $this->commandTester();

        $status = $tester->execute([
            '--name' => 'orders_service',
            '--description' => 'Orders service client',
            '--audience' => self::AUDIENCE,
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('At least one --group option is required.', $tester->getDisplay());
        self::assertNull(static::getContainer()->get(ClientRepository::class)
            ->findOneBy(['name' => 'orders_service']));
    }

    public function testRejectsInvalidClientWithoutPersistingSecret(): void
    {
        $tester = $this->commandTester();

        $status = $tester->execute([
            '--name' => 'invalid name',
            '--description' => 'Orders service client',
            '--audience' => self::AUDIENCE,
            '--group' => [AppFixtures::GROUP_NAME],
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('Invalid name:', $tester->getDisplay());
        self::assertNull(static::getContainer()->get(ClientRepository::class)
            ->findOneBy(['name' => 'invalid name']));
    }

    public function testRejectsInvalidGrantTypeWithoutCreatingClient(): void
    {
        $tester = $this->commandTester();

        $status = $tester->execute([
            '--name' => 'orders_service',
            '--description' => 'Orders service client',
            '--audience' => self::AUDIENCE,
            '--grant-type' => ['client_credentials', 'invalid_grant'],
            '--group' => [AppFixtures::GROUP_NAME],
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('Invalid grantTypes:', $tester->getDisplay());
        self::assertNull(static::getContainer()->get(ClientRepository::class)
            ->findOneBy(['name' => 'orders_service']));
    }

    private function commandTester(bool $bootKernel = true): CommandTester
    {
        if ($bootKernel) {
            self::bootKernel();
        }

        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:create-client'));
    }
}
