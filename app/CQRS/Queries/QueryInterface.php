<?php

namespace App\CQRS\Queries;

interface QueryInterface
{
    public function getCriteria(): array;
}