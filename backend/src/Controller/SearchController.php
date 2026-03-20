<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Search\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/search', name: 'api_search_')]
class SearchController extends AbstractController
{
    private SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    #[Route('', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $query = trim($request->query->getString('query'));

        if (!$query) {
            return new JsonResponse(['error' => 'Missing query'], Response::HTTP_BAD_REQUEST);
        }

        $results = $this->searchService->search($query, $user);

        return new JsonResponse($results, Response::HTTP_OK);
    }
}
