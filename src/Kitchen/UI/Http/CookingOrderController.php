<?php

declare(strict_types=1);

namespace App\Kitchen\UI\Http;

use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusView;
use App\Kitchen\Application\GetCookingOrderStatus\GetCookingOrderStatus;
use App\Kitchen\Application\Exception\CookingOrderNotFoundException;
use App\Kitchen\Application\MarkCookingOrderReady\MarkCookingOrderReady;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/kitchen')]
final class CookingOrderController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    #[Route('/cooking-orders/{id}', name: 'kitchen_get_cooking_order', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try {
            /** @var CookingOrderStatusView $view */
            $view = $this->queryBus->ask(new GetCookingOrderStatus($id));
        } catch (CookingOrderNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($this->serialize($view));
    }

    #[Route('/cooking-orders/{id}/ready', name: 'kitchen_mark_cooking_order_ready', methods: ['POST'])]
    public function markReady(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new MarkCookingOrderReady($id));
        } catch (CookingOrderNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(CookingOrderStatusView $view): array
    {
        return [
            'id' => $view->id,
            'customerOrderId' => $view->customerOrderId,
            'recipeId' => $view->recipeId,
            'status' => $view->status,
            'startedAt' => $view->startedAt,
            'completedAt' => $view->completedAt,
        ];
    }
}
