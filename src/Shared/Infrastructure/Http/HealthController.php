<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'ok';
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $dbStatus = 'unavailable';
        }

        return new JsonResponse([
            'service' => 'pizza-house',
            'status'  => $dbStatus === 'ok' ? 'ok' : 'degraded',
            'checks'  => [
                'database' => $dbStatus,
            ],
            'time'    => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
