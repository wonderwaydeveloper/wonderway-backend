<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'gif_url' => $this->gif_url,
            'likes_count' => $this->likes_count ?? 0,
            'comments_count' => $this->comments_count ?? 0,
            'is_liked' => $this->when(auth()->check(), fn () => $this->isLikedBy(auth()->id())),
            'is_draft' => $this->is_draft,
            'reply_settings' => $this->reply_settings,
            'user' => new UserResource($this->whenLoaded('user')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'hashtags' => $this->whenLoaded('hashtags'),
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
