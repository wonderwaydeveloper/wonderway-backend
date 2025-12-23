<?php

namespace App\CQRS;

use App\CQRS\Commands\CommandInterface;
use Illuminate\Container\Container;

class CommandBus
{
    private array $handlers = [];

    public function __construct(
        private Container $container
    ) {
    }

    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = get_class($command);
        
        if (!isset($this->handlers[$commandClass])) {
            throw new \Exception("No handler registered for command: {$commandClass}");
        }
        
        $handlerClass = $this->handlers[$commandClass];
        $handler = $this->container->make($handlerClass);
        
        return $handler->handle($command);
    }
}