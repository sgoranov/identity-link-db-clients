<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\AppFixtures;
use App\Entity\GroupScope;
use App\Repository\ClientRepository;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use sgoranov\IdentityLinkShared\Security\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class ClientControllerTest extends WebTestCase
{
    public function testGetScopesForAudience(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        $router = $container->get(RouterInterface::class);
        $group = $container->get(GroupRepository::class)
            ->findOneBy(['name' => AppFixtures::GROUP_NAME]);
        $clientEntity = $container->get(ClientRepository::class)
            ->findOneBy(['name' => AppFixtures::CLIENT_NAME]);

        $groupScope = new GroupScope();
        $groupScope->setGroup($group);
        $groupScope->setAudience('https://example.com/orders');
        $groupScope->setScope('orders:read');
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($groupScope);
        $entityManager->flush();

        $client->request('GET', $router->generate('api_v1_get_client_scopes', [
            'id' => $clientEntity->getId(),
            'audience' => 'https://example.com/orders',
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['orders:read'],
            json_decode($client->getResponse()->getContent(), true)['response']['scopes']
        );
    }

    public function testGetScopesForHttpAudience(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        $router = $container->get(RouterInterface::class);
        $group = $container->get(GroupRepository::class)
            ->findOneBy(['name' => AppFixtures::GROUP_NAME]);
        $clientEntity = $container->get(ClientRepository::class)
            ->findOneBy(['name' => AppFixtures::CLIENT_NAME]);

        $groupScope = new GroupScope();
        $groupScope->setGroup($group);
        $groupScope->setAudience('http://localhost:8000');
        $groupScope->setScope('orders:read');
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($groupScope);
        $entityManager->flush();

        $client->request('GET', $router->generate('api_v1_get_client_scopes', [
            'id' => $clientEntity->getId(),
            'audience' => 'http://localhost:8000',
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['orders:read'],
            json_decode($client->getResponse()->getContent(), true)['response']['scopes']
        );
    }

    public function testGetScopesRejectsMissingAudience(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        $clientEntity = $container->get(ClientRepository::class)
            ->findOneBy(['name' => AppFixtures::CLIENT_NAME]);

        $client->request('GET', $container->get(RouterInterface::class)->generate(
            'api_v1_get_client_scopes',
            ['id' => $clientEntity->getId()]
        ));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateClientWithMissingBody(): void
    {
        $client = static::createClient();
        $testUser = new User('test', ['clients.write']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('POST', $router->generate('api_v1_create_client'));
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateClientWithEmptyName(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => '',
            'description' => 'client description',
            'audience' => 'https://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['password', 'authorization_code', 'client_credentials'],
            'isPublic' => false,
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid name. This value should not be blank.',
            json_decode($response->getContent(), true)['error']);
    }

    public function testCreateClientWithInvalidName(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => '&&%$',
            'description' => 'client description',
            'audience' => 'https://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['password', 'authorization_code', 'client_credentials'],
            'isPublic' => false,
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid name. This value is not valid.',
            json_decode($response->getContent(), true)['error']);
    }

    public function testCreateClientWithExistingName(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => AppFixtures::CLIENT_NAME,
            'description' => 'client description',
            'audience' => 'https://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['password', 'authorization_code', 'client_credentials'],
            'isPublic' => false,
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(sprintf('Invalid name. The value "%s" already exists.', AppFixtures::CLIENT_NAME),
            json_decode($response->getContent(), true)['error']);
    }

    public function testCreateClientSuccessfully(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'test',
            'description' => 'client description',
            'audience' => 'https://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['password', 'authorization_code', 'client_credentials'],
            'isPublic' => false,
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('test',
            json_decode($response->getContent(), true)['response']['client']['name']);
        $this->assertSame('https://example.com/api',
            json_decode($response->getContent(), true)['response']['client']['audience']);
        $this->assertFalse(json_decode($response->getContent(), true)['response']['client']['isSystem']);
    }

    public function testCreateClientWithoutAudience(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'test_without_audience',
            'description' => 'client description',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['client_credentials'],
            'isPublic' => false,
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));

        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertSame('Invalid audience. This value should not be blank.',
            json_decode($client->getResponse()->getContent(), true)['error']);
    }

    public function testCreateClientWithHttpAudience(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'test_http_audience',
            'description' => 'client description',
            'audience' => 'http://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['client_credentials'],
            'isPublic' => false,
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));

        $this->assertSame(201, $client->getResponse()->getStatusCode());
        $this->assertSame('http://example.com/api',
            json_decode($client->getResponse()->getContent(), true)['response']['client']['audience']);
    }

    public function testCreateClientCannotSetSystemFlag(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'test_system',
            'description' => 'client description',
            'audience' => 'https://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['password', 'authorization_code', 'client_credentials'],
            'isPublic' => false,
            'isSystem' => true,
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringStartsWith('Extra attributes are not allowed',
            json_decode($response->getContent(), true)['error']);
    }

    public function testUpdateClientWithInvalidUuid()
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'new_name',
        ];

        $client->request('PUT', $router->generate('api_v1_update_client', [
            'id' => 'uuid'
        ]), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateClientSuccessfully()
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'name_new',
        ];

        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);

        $client->request('PUT', $router->generate('api_v1_update_client', [
            'id' => $entity->getId()
        ]), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('name_new',
            json_decode($response->getContent(), true)['response']['client']['name']);
    }

    public function testUpdateClientCannotChangeAudience(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);
        $originalAudience = $entity->getAudience();

        $client->request('PUT', $router->generate('api_v1_update_client', [
            'id' => $entity->getId()
        ]), [], [], [], json_encode(['audience' => 'https://changed.example.com/api']));
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringStartsWith('Extra attributes are not allowed',
            json_decode($response->getContent(), true)['error']);

        $client->getContainer()->get(EntityManagerInterface::class)->clear();
        $updatedEntity = $repository->find($entity->getId());
        $this->assertSame($originalAudience, $updatedEntity->getAudience());
    }

    public function testUpdateSystemClientIsForbidden(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);
        $this->markClientAsSystem($client->getContainer()->get(EntityManagerInterface::class), $entity->getId());

        $client->request('PUT', $router->generate('api_v1_update_client', [
            'id' => $entity->getId()
        ]), [], [], [], json_encode(['name' => 'name_new']));
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('System clients cannot be updated.',
            json_decode($response->getContent(), true)['error']);
    }

    public function testDeleteClientSuccessfully()
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);

        $client->request('DELETE', $router->generate('api_v1_delete_client', [
            'id' => $entity->getId()
        ]));
        $response = $client->getResponse();

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDeleteSystemClientIsForbidden(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);
        $this->markClientAsSystem($client->getContainer()->get(EntityManagerInterface::class), $entity->getId());

        $client->request('DELETE', $router->generate('api_v1_delete_client', [
            'id' => $entity->getId()
        ]));
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('System clients cannot be deleted.',
            json_decode($response->getContent(), true)['error']);
    }

    public function testFetchClientSuccessfully()
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);

        $client->request('GET', $router->generate('api_v1_fetch_client', [
            'id' => $entity->getId()
        ]));
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(AppFixtures::CLIENT_NAME,
            json_decode($response->getContent(), true)['response']['client']['name']);
        $this->assertFalse(json_decode($response->getContent(), true)['response']['client']['isSystem']);
    }

    public function testFetchSystemClientExposesSystemFlag(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);
        $this->markClientAsSystem($client->getContainer()->get(EntityManagerInterface::class), $entity->getId());

        $client->request('GET', $router->generate('api_v1_fetch_client', [
            'id' => $entity->getId()
        ]));
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['response']['client']['isSystem']);
    }

    public function testCreateClientWithInvalidConsentType(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'test_invalid_consent',
            'description' => 'client with invalid consent type',
            'audience' => 'https://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['authorization_code'],
            'isPublic' => false,
            'consentRequired' => 'yes', // String instead of boolean
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame('The consentRequired property must be of type bool, but string was provided.',
            json_decode($response->getContent(), true)['error']);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateClientWithDefaultConsentRequiredValue(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $content = [
            'name' => 'test_default_consent',
            'description' => 'client without explicit consent setting',
            'audience' => 'https://example.com/api',
            'redirectUri' => ['http://localhost/'],
            'grantTypes' => ['authorization_code'],
            'isPublic' => false
        ];

        $client->request('POST', $router->generate('api_v1_create_client'), [], [], [], json_encode($content));
        $response = $client->getResponse();

        $this->assertSame(201, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true)['response']['client'];

        $this->assertArrayHasKey('consentRequired', $responseData);
        $this->assertFalse($responseData['consentRequired']);
    }

    public function testConsentRequiredPersistsAfterMultipleUpdates(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $repository = $client->getContainer()->get(ClientRepository::class);
        list($entity) = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);

        $client->request('PUT', $router->generate('api_v1_update_client', [
            'id' => $entity->getId()
        ]), [], [], [], json_encode(['consentRequired' => true]));
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $client->request('PUT', $router->generate('api_v1_update_client', [
            'id' => $entity->getId()
        ]), [], [], [], json_encode(['description' => 'updated description']));
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $client->request('GET', $router->generate('api_v1_fetch_client', [
            'id' => $entity->getId()
        ]));
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true)['response']['client'];
        $this->assertTrue($responseData['consentRequired']);
    }

    public function testClientMetadataUrlsPersistAfterMultipleUpdates(): void
    {
        $client = $this->createAuthenticatedClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $repository = $client->getContainer()->get(ClientRepository::class);
        [$entity] = $repository->findBy(['name' => AppFixtures::CLIENT_NAME]);

        $metadata = [
            'applicationUrl' => 'https://example.com',
            'termsOfServiceUrl' => 'https://example.com/terms',
            'privacyPolicyUrl' => 'https://example.com/privacy',
            'logoUrl' => 'https://example.com/logo.png',
        ];

        $client->request('PUT', $router->generate('api_v1_update_client', ['id' => $entity->getId()]), [], [], [], json_encode($metadata));
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $client->request('PUT', $router->generate('api_v1_update_client', ['id' => $entity->getId()]), [], [], [], json_encode([
            'description' => 'updated description'
        ]));
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $client->request('GET', $router->generate('api_v1_fetch_client', ['id' => $entity->getId()]));
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true)['response']['client'];

        $this->assertSame('https://example.com', $responseData['applicationUrl']);
        $this->assertSame('https://example.com/terms', $responseData['termsOfServiceUrl']);
        $this->assertSame('https://example.com/privacy', $responseData['privacyPolicyUrl']);
        $this->assertSame('https://example.com/logo.png', $responseData['logoUrl']);
        $this->assertSame('updated description', $responseData['description']);
    }

    private function createAuthenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', 'test'));

        return $client;
    }

    private function markClientAsSystem(EntityManagerInterface $entityManager, string $id): void
    {
        $entityManager->getConnection()->executeStatement(
            'UPDATE client SET is_system = true WHERE id = :id',
            ['id' => $id]
        );
        $entityManager->clear();
    }
}
