<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Secret;
use sgoranov\IdentityLinkShared\Serializer\Deserializer;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\SerializerInterface;

#[OA\Tag(name: 'Secret')]
#[Route('/api/v1', name: 'api_v1_')]
final class SecretController extends AbstractController
{

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $entityManager,
        private readonly Deserializer $deserializer,
    )
    {
    }

    #[Route('/secret/{id}', name: 'fetch_secret', methods: 'GET')]
    #[OA\Get(
        path: '/api/v1/secret/{id}',
        summary: 'Fetch a secret by ID',
        tags: ['Secret'],
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
                description: 'Secret data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(property: 'secret', ref: '#/components/schemas/Secret')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Secret not found')
        ]
    )]
    #[IsGranted('clients.secrets.read')]
    public function fetch(#[MapEntity(id: 'id')] Secret $secret): Response
    {
        return new JsonResponse([
            'response' => ['secret' => json_decode($this->serializer->serialize($secret, 'json'))]
        ]);
    }

    #[Route('/secret', name: 'create_secret', methods: 'POST')]
    #[OA\Post(
        path: '/api/v1/secret',
        summary: 'Create a new secret',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Secret')
        ),
        tags: ['Secret'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Secret created (password omitted)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(property: 'secret', ref: '#/components/schemas/Secret')
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
    #[IsGranted('clients.secrets.write')]
    public function create(): Response
    {
        $secret = new Secret();
        if (!$this->deserializer->deserialize($secret, ['create'])) {
            return $this->deserializer->respondWithError();
        }

        $this->entityManager->persist($secret);
        $this->entityManager->flush();

        $context = (new ObjectNormalizerContextBuilder())
            ->withGroups('response_without_password')
            ->toArray();

        return new JsonResponse([
            'response' => ['secret' => json_decode($this->serializer->serialize($secret, 'json', $context))]
        ], Response::HTTP_CREATED);
    }

    #[Route('/secret/{id}', name: 'update_secret', methods: 'PUT')]
    #[OA\Put(
        path: '/api/v1/secret/{id}',
        summary: 'Update a secret',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Secret')
        ),
        tags: ['Secret'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Secret updated'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Secret not found')
        ]
    )]
    #[IsGranted('clients.secrets.write')]
    public function update(#[MapEntity(id: 'id')] Secret $secret): Response
    {
        if ($secret->getIsSystem()) {
            return new JsonResponse([
                'error' => 'System secrets cannot be updated.'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$this->deserializer->deserialize($secret, ['update'])) {
            return $this->deserializer->respondWithError();
        }

        $this->entityManager->persist($secret);
        $this->entityManager->flush();

        return new JsonResponse([
            'response' => ['secret' => json_decode($this->serializer->serialize($secret, 'json'))]
        ]);
    }

    #[Route('/secret/{id}', name: 'delete_secret', methods: 'DELETE')]
    #[OA\Delete(
        path: '/api/v1/secret/{id}',
        summary: 'Delete a secret',
        tags: ['Secret'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Secret deleted'),
            new OA\Response(response: 404, description: 'Secret not found')
        ]
    )]
    #[IsGranted('clients.secrets.delete')]
    public function delete(#[MapEntity(id: 'id')] Secret $secret): Response
    {
        if ($secret->getIsSystem()) {
            return new JsonResponse([
                'error' => 'System secrets cannot be deleted.'
            ], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($secret);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/secret/issue', name: 'issue_secret', methods: 'POST')]
    #[OA\Post(
        path: '/api/v1/secret/issue',
        summary: 'Issue a new secret (auto-generates password)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'expirationPeriod',
                        description: 'Relative expiration period',
                        type: 'string',
                        enum: ['1d', '1w', '1m', '3m', '9m', '1y', '2y']
                    ),
                    new OA\Property(
                        property: 'client',
                        ref: '#/components/schemas/Client',
                        description: 'Client to which the secret belongs'
                    ),
                    new OA\Property(
                        property: 'passwordHint',
                        description: 'Optional hint to help recall the password',
                        type: 'string',
                        maxLength: 500
                    )
                ],
                type: 'object'
            )
        ),
        tags: ['Secret'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Secret issued (includes the generated password, which is not returned again)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(
                                    property: 'secret',
                                    allOf: [
                                        new OA\Schema(ref: '#/components/schemas/Secret'),
                                        new OA\Schema(
                                            properties: [
                                                new OA\Property(
                                                    property: 'password',
                                                    description: 'Generated password (64 hex characters). Returned only in this response.',
                                                    type: 'string',
                                                    writeOnly: false
                                                )
                                            ],
                                            type: 'object'
                                        )
                                    ]
                                )
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
    #[IsGranted('clients.secrets.write')]
    public function issue(): Response
    {
        $secret = new Secret();

        if (!$this->deserializer->deserialize($secret, ['secret_issue_request'])) {
            return $this->deserializer->respondWithError();
        }

        $secret->setPassword(bin2hex(random_bytes(32)));
        if (!$secret->getPasswordHint()) {
            $secret->setPasswordHint('auto generated');
        }

        $period = $secret->getExpirationPeriod();
        $interval = new \DateInterval([
            '1d' => 'P1D',
            '1w' => 'P1W',
            '1m' => 'P1M',
            '3m' => 'P3M',
            '9m' => 'P9M',
            '1y' => 'P1Y',
            '2y' => 'P2Y',
        ][$period]);
        $secret->setExpirationDateTime((new \DateTime())->add($interval));

        $this->entityManager->persist($secret);
        $this->entityManager->flush();

        $context = (new ObjectNormalizerContextBuilder())
            ->withGroups(['response_without_password', 'secret_issue_response'])
            ->toArray();

        return new JsonResponse([
            'response' => ['secret' => json_decode($this->serializer->serialize($secret, 'json', $context))]
        ], Response::HTTP_CREATED);
    }
}
