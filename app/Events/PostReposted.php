<?php

namespace App\Events;

use App\Models\Post;
use App\Models\Repost;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostReposted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Post $post,
        public User $user,
        public Repost $repost,
        public bool $isQuote
    ) {
    }
}
