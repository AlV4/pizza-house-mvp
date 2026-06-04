<?php

declare(strict_types=1);

namespace App\Kitchen\UI\Http;

use App\Kitchen\Application\AddIngredientToRecipe\AddIngredientToRecipe;
use App\Kitchen\Application\ChangeRecipePrice\ChangeRecipePrice;
use App\Kitchen\Application\CreateRecipe\CreateRecipe;
use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Application\GetRecipe\GetRecipe;
use App\Kitchen\Application\GetRecipe\RecipeView;
use App\Kitchen\Application\ListRecipes\ListRecipes;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/kitchen')]
final class RecipeController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    #[Route('/recipes', name: 'kitchen_create_recipe', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $id = (string) new Ulid();

        try {
            $this->commandBus->dispatch(new CreateRecipe(
                id: $id,
                name: $data['name'] ?? '',
                ingredients: $data['ingredients'] ?? [],
                priceAmount: (int) ($data['priceAmount'] ?? 0),
                priceCurrency: $data['priceCurrency'] ?? '',
                cookingTimeMinutes: (int) ($data['cookingTimeMinutes'] ?? 0),
            ));
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route('/recipes/{id}/ingredients', name: 'kitchen_add_ingredient', methods: ['POST'])]
    public function addIngredient(string $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch(new AddIngredientToRecipe(
                recipeId: $id,
                name: $data['name'] ?? '',
                quantity: (float) ($data['quantity'] ?? 0),
                unit: $data['unit'] ?? '',
            ));
        } catch (RecipeNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/recipes/{id}/price', name: 'kitchen_change_price', methods: ['PATCH'])]
    public function changePrice(string $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch(new ChangeRecipePrice(
                recipeId: $id,
                newAmount: (int) ($data['amount'] ?? 0),
                currency: $data['currency'] ?? '',
            ));
        } catch (RecipeNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/recipes/{id}', name: 'kitchen_get_recipe', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try {
            /** @var RecipeView $view */
            $view = $this->queryBus->ask(new GetRecipe($id));
        } catch (RecipeNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($this->serializeRecipe($view));
    }

    #[Route('/recipes', name: 'kitchen_list_recipes', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var list<RecipeView> $views */
        $views = $this->queryBus->ask(new ListRecipes());

        return new JsonResponse(array_map($this->serializeRecipe(...), $views));
    }

    private function serializeRecipe(RecipeView $view): array
    {
        return [
            'id' => $view->id,
            'name' => $view->name,
            'price' => [
                'amount' => $view->priceAmount,
                'currency' => $view->priceCurrency,
            ],
            'cookingTimeMinutes' => $view->cookingTimeMinutes,
            'ingredients' => $view->ingredients,
        ];
    }
}
