<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBus
{
    // command.bus is the default_bus (see config/packages/messenger.yaml).
    // Symfony only registers #[Target] aliases for non-default buses, so the
    // plain MessageBusInterface autowires to the default bus — i.e. command.bus.
    public function __construct(
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
