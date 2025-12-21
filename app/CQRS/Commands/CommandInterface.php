<?php

namespace App\CQRS\Commands;

interface CommandInterface
{
    public function getPayload(): array;
}