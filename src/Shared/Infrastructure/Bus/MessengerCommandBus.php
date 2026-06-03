<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        #[Target('command.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function dispatch(object $command): void
    {
        try {
            $this->bus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            // Unwrap so callers see the original domain exception,
            // not Messenger's wrapper.
            throw $exception->getPrevious() ?? $exception;
        }
    }
}
