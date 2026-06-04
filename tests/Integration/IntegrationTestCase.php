<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Bus\QueryBus;
use App\Tests\Support\RecordedDomainEvents;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for cross-context integration tests.
 *
 * Boots the real kernel (test env), so commands/queries/events run through the
 * real Messenger buses against a real PostgreSQL database. The event bus is
 * synchronous in test (see config/packages/messenger.yaml), so event-driven
 * flows execute inline without a worker.
 *
 * The schema is rebuilt from ORM metadata before every test — there are no
 * migrations in this MVP, and a fresh schema keeps tests isolated. The test
 * database itself must already exist (the test-integration Make target creates
 * it via doctrine:database:create).
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected CommandBus $commandBus;
    protected QueryBus $queryBus;
    protected EventBus $eventBus;
    protected RecordedDomainEvents $recordedEvents;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->commandBus = $container->get(CommandBus::class);
        $this->queryBus = $container->get(QueryBus::class);
        $this->eventBus = $container->get(EventBus::class);
        $this->recordedEvents = $container->get(RecordedDomainEvents::class);

        $this->resetSchema();
        $this->recordedEvents->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    private function resetSchema(): void
    {
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        if ($metadata === []) {
            return;
        }

        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }
}
