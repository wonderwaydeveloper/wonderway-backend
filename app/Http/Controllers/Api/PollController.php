<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'question' => 'required|string|max:280',
            'options' => 'required|array|min:2|max:4',
            'options.*' => 'required|string|max:100',
            'duration_hours' => 'required|integer|min:1|max:168', // 1 hour to 7 days
        ]);

        $post = Post::findOrFail($request->post_id);
        $this->authorize('update', $post);

        DB::transaction(function () use ($request, $post) {
            $poll = Poll::create([
                'post_id' => $post->id,
                'question' => $request->question,
                'ends_at' => now()->addHours($request->duration_hours),
            ]);

            foreach ($request->options as $optionText) {
                PollOption::create([
                    'poll_id' => $poll->id,
                    'text' => $optionText,
                ]);
            }
        });

        return response()->json([
            'message' => 'Poll created successfully',
            'poll' => $post->fresh()->poll->load('options'),
        ], 201);
    }

    public function vote(Poll $poll, PollOption $option)
    {
        if ($poll->isExpired()) {
            return response()->json(['error' => 'Poll has expired'], 400);
        }

        if ($option->poll_id !== $poll->id) {
            return response()->json(['error' => 'Invalid option for this poll'], 400);
        }

        $user = auth()->user();

        if ($poll->hasVoted($user->id)) {
            return response()->json(['error' => 'You have already voted'], 400);
        }

        DB::transaction(function () use ($poll, $option, $user) {
            // Create vote
            $poll->votes()->create([
                'poll_option_id' => $option->id,
                'user_id' => $user->id,
            ]);

            // Update counters
            $option->increment('votes_count');
            $poll->increment('total_votes');
        });

        return response()->json([
            'message' => 'Vote recorded successfully',
            'results' => $poll->results(),
            'total_votes' => $poll->fresh()->total_votes,
        ]);
    }

    public function results(Poll $poll)
    {
        return response()->json([
            'poll' => $poll->load('options'),
            'results' => $poll->results(),
            'total_votes' => $poll->total_votes,
            'is_expired' => $poll->isExpired(),
            'user_voted' => $poll->hasVoted(auth()->id()),
            'user_vote' => $poll->getUserVote(auth()->id()),
        ]);
    }
}
