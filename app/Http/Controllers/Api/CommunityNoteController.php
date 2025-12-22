<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityNote;
use App\Models\Post;
use App\Services\CommunityNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityNoteController extends Controller
{
    public function __construct(
        private CommunityNoteService $communityNoteService
    ) {}

    public function store(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|min:10|max:500',
            'sources' => 'nullable|array|max:3',
            'sources.*' => 'url|max:255',
        ]);

        try {
            $note = $this->communityNoteService->createNote(
                $post,
                $request->user(),
                $request->only(['content', 'sources'])
            );

            return response()->json([
                'message' => 'Community note created successfully',
                'note' => $note->load('author:id,name,username'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function vote(Request $request, CommunityNote $note): JsonResponse
    {
        $request->validate([
            'vote_type' => 'required|in:helpful,not_helpful',
        ]);

        $result = $this->communityNoteService->voteOnNote(
            $note,
            $request->user(),
            $request->vote_type
        );

        return response()->json([
            'message' => $result['voted'] ? 'Vote recorded' : 'Vote removed',
            'vote_type' => $result['vote_type'],
            'helpful_votes' => $note->fresh()->helpful_votes,
            'not_helpful_votes' => $note->fresh()->not_helpful_votes,
        ]);
    }

    public function index(Post $post): JsonResponse
    {
        $notes = $this->communityNoteService->getNotesForPost($post);

        return response()->json([
            'notes' => $notes,
        ]);
    }

    public function pending(): JsonResponse
    {
        $notes = $this->communityNoteService->getPendingNotes();

        return response()->json($notes);
    }
}