<?php

namespace App\Domain\Post\Repositories;

use App\Domain\Post\Entities\PostEntity;
use App\Domain\Post\ValueObjects\PostId;

interface PostDomainRepositoryInterface
{
    public function save(PostEntity $post): void;
    public function findById(PostId $id): ?PostEntity;
    public function delete(PostId $id): void;
}