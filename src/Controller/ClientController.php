<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\DTO\Client\AuthRequest;
use App\Entity\Client;
use App\Repository\ClientRepository;
use sgoranov\IdentityLinkShared\Serializer\Deserializer;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1', name: 'api_v1_')]
final class ClientController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface    $serializer,
        private readonly EntityManagerInterface $entityManager,
        private readonly Deserializer           $deserializer,
        private readonly ClientRepository       $repository,
        private readonly ValidatorInterface     $validator,
    )
    {
    }

    #[Route('/client/{id}/scope', name: 'get_client_scopes', methods: 'GET')]
    #[OA\Get(
        path: '/api/v1/client/{id}/scope',
        summary: 'Fetch scopes granted to a client for an audience',
        tags: ['Client'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'UUID of the client',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'audience',
                description: 'Protected-resource audience',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uri', maxLength: 3000)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Scopes fetched successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(
                                    property: 'scopes',
                                    type: 'array',
                                    items: new OA\Items(type: 'string')
                                )
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid audience'),
            new OA\Response(response: 404, description: 'Client not found')
        ]
    )]
    #[IsGranted('clients.read')]
    public function getScopes(
        #[MapEntity(id: 'id')] Client $client,
        Request $request,
    ): Response {
        $audience = $request->query->getString('audience');
        $violations = $this->validator->validate($audience, [
            new Assert\NotBlank(),
                new Assert\Url(protocols: ['http', 'https']),
            new Assert\Length(min: 1, max: 3000),
        ]);

        if (count($violations) > 0) {
            return new JsonResponse([
                'error' => 'Invalid audience. ' . $violations[0]->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'response' => [
                'scopes' => $this->repository->getScopes($client, $audience),
            ],
        ]);
    }

    #[Route('/client/{id}', name: 'fetch_client', methods: 'GET')]
    #[OA\Get(
        path: '/api/v1/client/{id}',
        summary: 'Fetch client by ID',
        tags: ['Client'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Client data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(property: 'client', ref: '#/components/schemas/Client')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Client not found')
        ]
    )]
    #[IsGranted('clients.read')]
    public function fetch(#[MapEntity(id: 'id')] Client $client): Response
    {
        return new JsonResponse([
            'response' => ['client' => json_decode($this->serializer->serialize($client, 'json'))]
        ]);
    }

    #[Route('/client', name: 'create_client', methods: 'POST')]
    #[OA\Post(
        path: '/api/v1/client',
        summary: 'Create a new client',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Client')
        ),
        tags: ['Client'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Client created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(property: 'client', ref: '#/components/schemas/Client')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Validation error')
        ]
    )]
    #[IsGranted('clients.write')]
    public function create(): Response
    {
        $client = new Client();
        if (!$this->deserializer->deserialize($client, ['create'])) {
            return $this->deserializer->respondWithError();
        }

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return new JsonResponse([
            'response' => ['client' => json_decode($this->serializer->serialize($client, 'json'))]
        ], Response::HTTP_CREATED);
    }

    #[Route('/client/{id}', name: 'update_client', methods: 'PUT')]
    #[OA\Put(
        path: '/api/v1/client/{id}',
        summary: 'Update an existing client',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Client')
        ),
        tags: ['Client'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Client updated'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Client not found')
        ]
    )]
    #[IsGranted('clients.write')]
    public function update(#[MapEntity(id: 'id')] Client $client): Response
    {
        if ($client->getIsSystem()) {
            return new JsonResponse([
                'error' => 'System clients cannot be updated.'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$this->deserializer->deserialize($client, ['update'])) {
            return $this->deserializer->respondWithError();
        }

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return new JsonResponse([
            'response' => ['client' => json_decode($this->serializer->serialize($client, 'json'))]
        ]);
    }

    #[Route('/client/{id}', name: 'delete_client', methods: 'DELETE')]
    #[OA\Delete(
        path: '/api/v1/client/{id}',
        summary: 'Delete a client',
        tags: ['Client'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema:
                new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Client deleted'),
            new OA\Response(response: 404, description: 'Client not found')
        ]
    )]
    #[IsGranted('clients.delete')]
    public function delete(#[MapEntity(id: 'id')] Client $client): Response
    {
        if ($client->getIsSystem()) {
            return new JsonResponse([
                'error' => 'System clients cannot be deleted.'
            ], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($client);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/auth', name: 'auth', methods: 'POST')]
    #[OA\Post(
        path: '/api/v1/auth',
        summary: 'Authenticate client',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AuthRequest')
        ),
        tags: ['Client'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentication successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(property: 'client', ref: '#/components/schemas/Client')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Authentication failed')
        ]
    )]
    #[IsGranted('clients.auth')]
    public function auth(): Response
    {
        $authRequest = new AuthRequest();
        if (!$this->deserializer->deserialize($authRequest)) {
            return $this->deserializer->respondWithError();
        }

        $client = $this->repository->getClientByIdAndSecret($authRequest->getId(),
            $authRequest->getSecret(), $authRequest->getGrantType());

        if ($client === null) {
            return new JsonResponse([
                'error' => 'Auth failed. Please ensure your name and secret are correct. If you continue to experience issues, contact support.'
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'response' => ['client' => json_decode($this->serializer->serialize($client, 'json'))]
        ]);
    }
}
