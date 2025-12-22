<?php

namespace App\Services;

use App\Models\CommunityNote;
use App\Models\CommunityNoteVote;
use App\Models\Post;
use App\Models\User;

class CommunityNoteService
{
    public function createNote(Post $post, User $author, array $data): CommunityNote
    {
        // Check if user already has a note for this post
        $existingNote = CommunityNote::where('post_id', $post->id)
            ->where('author_id', $author->id)
            ->first();

        if ($existingNote) {
            throw new \Exception('You already have a community note for this post');
        }

        return CommunityNote::create([
            'post_id' => $post->id,
            'author_id' => $author->id,
            'content' => $data['content'],
            'sources' => $data['sources'] ?? [],
        ]);
    }

    public function voteOnNote(CommunityNote $note, User $user, string $voteType): array
    {
        // Check if user already voted
        $existingVote = CommunityNoteVote::where('community_note_id', $note->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingVote) {
            if ($existingVote->vote_type === $voteType) {
                // Remove vote if same type
                $existingVote->delete();
                $this->updateVoteCounts($note);
                return ['voted' => false, 'vote_type' => null];
            } else {
                // Change vote type
                $existingVote->update(['vote_type' => $voteType]);
            }
        } else {
            // Create new vote
            CommunityNoteVote::create([
                'community_note_id' => $note->id,
                'user_id' => $user->id,
                'vote_type' => $voteType,
            ]);
        }

        $this->updateVoteCounts($note);
        $this->checkApprovalStatus($note);

        return ['voted' => true, 'vote_type' => $voteType];
    }

    private function updateVoteCounts(CommunityNote $note): void
    {
        $helpfulCount = $note->votes()->where('vote_type', 'helpful')->count();
        $notHelpfulCount = $note->votes()->where('vote_type', 'not_helpful')->count();

        $note->update([
            'helpful_votes' => $helpfulCount,
            'not_helpful_votes' => $notHelpfulCount,
        ]);
    }

    private function checkApprovalStatus(CommunityNote $note): void
    {
        if ($note->status === 'pending' && $note->shouldBeApproved()) {
            $note->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }

    public function getNotesForPost(Post $post): array
    {
        return $post->approvedCommunityNotes()
            ->with(['author:id,name,username', 'votes'])
            ->orderBy('helpful_votes', 'desc')
            ->get()
            ->toArray();
    }

    public function getPendingNotes(): array
    {
        return CommunityNote::pending()
            ->with(['post:id,content', 'author:id,name,username'])
            ->withCount('votes')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->toArray();
    }
}