<?php

namespace App\Controller\Api;

use App\Entity\GalleryImage;
use App\Repository\GalleryImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

/**
 * Gallery API Controller
 * 
 * RESTful API endpoints for gallery image management:
 * - List gallery images with optional filtering by category
 * - Get single gallery image by ID
 * 
 * All endpoints return active images only and include full image URLs.
 */
#[Route('/api/gallery', name: 'api_gallery_')]
class GalleryController extends AbstractController
{
    public function __construct(
        private GalleryImageRepository $galleryRepository
    ) {}

    /**
     * List gallery images with optional filtering
     * 
     * Returns active gallery images with optional filtering by category.
     * Supports pagination via limit parameter (1-100 images).
     * 
     * @param Request $request HTTP request containing optional query parameters (limit, category)
     * @return JsonResponse List of gallery images with metadata
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gallery',
        summary: 'Get gallery images',
        description: 'Retrieve a list of active gallery images',
        tags: ['Gallery'],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                description: 'Maximum number of images to return',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50)
            ),
            new OA\Parameter(
                name: 'category',
                description: 'Filter by category',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['terrasse', 'interieur', 'plats', 'ambiance'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gallery images retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'title', type: 'string', example: 'Terrasse conviviale'),
                                new OA\Property(property: 'description', type: 'string', example: 'Un espace agréable pour vos repas en extérieur'),
                                new OA\Property(property: 'imageUrl', type: 'string', example: '/static/img/terrasse_2.jpg'),
                                new OA\Property(property: 'category', type: 'string', example: 'terrasse'),
                                new OA\Property(property: 'displayOrder', type: 'integer', example: 1),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00')
                            ]
                        )),
                        new OA\Property(property: 'total', type: 'integer', example: 10)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid parameters')
                    ]
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            // Extract query parameters
            $defaultLimit = $this->getParameter('gallery.default_limit');
            $maxLimit = $this->getParameter('gallery.max_limit');
            $limit = (int) $request->query->get('limit', $defaultLimit);
            $category = $request->query->get('category'); // Optional category filter

            // Validate limit parameter (must be between 1 and max_limit)
            if ($limit < 1 || $limit > $maxLimit) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: "La limite doit être comprise entre 1 et {$maxLimit}");
                return $this->json($response->toArray(), 400);
            }

            // Validate category if provided (must be one of the allowed categories)
            if ($category && !in_array($category, ['terrasse', 'interieur', 'plats', 'ambiance'])) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Catégorie invalide. Doit être: terrasse, interieur, plats, ambiance');
                return $this->json($response->toArray(), 400);
            }

            // Fetch images from repository (filtered by category if provided)
            if ($category) {
                $images = $this->galleryRepository->findByCategory($category);
            } else {
                $images = $this->galleryRepository->findAllActive(); // All active images
            }

            // Apply limit to results
            $images = array_slice($images, 0, $limit);

            // Format response data
            $req = $request; // capture for closure
            $data = array_map(function (GalleryImage $image) use ($req) {
                return [
                    'id' => $image->getId(),
                    'title' => $image->getTitle(),
                    'description' => $image->getDescription(),
                    'imageUrl' => $req->getSchemeAndHttpHost() . '/static/img/' . $image->getImagePath(),
                    'category' => $image->getCategory(),
                    'displayOrder' => $image->getDisplayOrder(),
                    'createdAt' => $image->getCreatedAt()->format('c')
                ];
            }, $images);

            $response = new \App\DTO\ApiResponseDTO(success: true, data: [
                'items' => $data,
                'total' => count($data)
            ]);
            return $this->json($response->toArray(), 200);

        } catch (\Exception $e) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de la récupération des images de la galerie');
            return $this->json($response->toArray(), 500);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/gallery/{id}',
        summary: 'Get gallery image by ID',
        description: 'Retrieve a specific gallery image by its ID',
        tags: ['Gallery'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Gallery image ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gallery image retrieved successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'title', type: 'string', example: 'Terrasse conviviale'),
                            new OA\Property(property: 'description', type: 'string', example: 'Un espace agréable pour vos repas en extérieur'),
                            new OA\Property(property: 'imageUrl', type: 'string', example: '/static/img/terrasse_2.jpg'),
                            new OA\Property(property: 'category', type: 'string', example: 'terrasse'),
                            new OA\Property(property: 'displayOrder', type: 'integer', example: 1),
                            new OA\Property(property: 'isActive', type: 'boolean', example: true),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00'),
                            new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00')
                        ])
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Gallery image not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Gallery image not found')
                    ]
                )
            )
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $image = $this->galleryRepository->find($id);

            if (!$image || !$image->isIsActive()) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Image de galerie introuvable');
                return $this->json($response->toArray(), 404);
            }

            $data = [
                'id' => $image->getId(),
                'title' => $image->getTitle(),
                'description' => $image->getDescription(),
                'imageUrl' => $request->getSchemeAndHttpHost() . '/static/img/' . $image->getImagePath(),
                'category' => $image->getCategory(),
                'displayOrder' => $image->getDisplayOrder(),
                'isActive' => $image->isIsActive(),
                'createdAt' => $image->getCreatedAt()->format('c'),
                'updatedAt' => $image->getUpdatedAt() ? $image->getUpdatedAt()->format('c') : null
            ];

            $response = new \App\DTO\ApiResponseDTO(success: true, data: $data);
            return $this->json($response->toArray(), 200);

        } catch (\Exception $e) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de la récupération de l\'image de la galerie');
            return $this->json($response->toArray(), 500);
        }
    }
}
