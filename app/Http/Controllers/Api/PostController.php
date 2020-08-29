<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Post;
use App\PostLike;
use App\PostReport;
use App\PostVote;
use Illuminate\Http\Request;
use App\Http\Resources\Post as PostResource;
use App\Http\Resources\PostReport as PostReportResource;
use App\Http\Resources\PostVote as PostVoteResource;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /**
     * Get Post Detail
     *
     * @param $id
     * @return mixed
     */
    public function view($id) {
        $post = Post::with('like')->findOrFail($id);
        return new PostResource($post);
    }

    /**
     * Like Post
     *
     * @param $id
     * @return PostResource|\Illuminate\Http\JsonResponse
     */
    public function like($id) {
        $userId = auth()->id();
        $post = Post::with([
            'like' => function ($query) use ($userId) {
                $query->where('post_likes.user_id', $userId);
            }
        ])->findOrFail($id);


        if (!is_null($post->like)) {
            return response()->json([
                'code' => 'HAS_LIKED',
            ], 400);
        }

        $postLike = new PostLike();
        $postLike->user_id = $userId;
        $postLike->post_id = $post->id;
        $postLike->save();
        $post->load([
            'like' => function($query) use ($userId) {
                $query->where('post_likes.user_id', $userId);
            }
        ]);


        return new PostResource($post);
    }

    /**
     * Unlike Post
     *
     * @param $id
     * @return PostResource|\Illuminate\Http\JsonResponse
     */
    public function unlike($id) {
        $userId = auth()->id();
        $post = Post::with([
            'like' => function ($query) use ($userId) {
                $query->where('post_likes.user_id', $userId);
            }
        ])->findOrFail($id);

        if (is_null($post->like)) {
            return response()->json([
                'code' => 'NOT_LIKED',
            ], 400);
        }
        $post->like->delete();
        $post->refresh();
        return new PostResource($post);
    }

    /**
     * Get user liked posts
     *
     * @param Request $request
     * @param $userId
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function userLiked(Request $request, $userId)
    {

        $user = auth()->user();
        if ($user->getKey() != (int) $userId) {
            return response()->json([
                'code' => 'NOT_AUTHORIZED'
            ], 403);
        }

        $pageSize = (int) $request->query('page_size', 20);

        $posts = Post::select('posts.*')
            ->with([
                'like' => function($query) use($userId) {
                    $query->where('post_likes.user_id', $userId);
                }
            ])
            ->join('post_likes', 'posts.id', '=', 'post_likes.post_id')
            ->where('post_likes.user_id', $userId)
            ->paginate($pageSize);

        return PostResource::collection($posts);
    }

    /**
     * Report post
     *
     * @param Request $request
     * @param $id
     * @return PostReportResource|\Illuminate\Http\JsonResponse
     */
    public function report(Request $request, $id) {
        $post = Post::findOrFail($id);
        $user = auth()->user();
        $report = $post->reports()->where('user_id', $user->getKey())->first();
        if (!is_null($report)) {
            return response()->json([
                'code' => 'HAS_REPORTED',
            ], 400);
        }

        $validatedData = $request->validate([
            'description' => 'required|max:255',
        ]);

        $report = new PostReport();
        $report->post_id = $post->id;
        $report->user_id = $user->id;
        $report->description = $validatedData['description'];
        $report->save();

        return new PostReportResource($report);

    }

    /**
     * Vote Post
     *
     * @param Request $request
     * @param $id
     * @return PostVoteResource
     */
    public function vote(Request $request, $id)
    {
        $user = auth()->user();

        $validatedDate = $request->validate([
            'vote_type' => [
                'required',
                Rule::in([
                    PostVote::DOWN_VOTE,
                    PostVote::UP_VOTE
                ])
            ]
        ]);

        $vote = PostVote::where('user_id', $user->getKey())
            ->where('post_id', $id)
            ->firstOrNew();

        if ($vote->id) {
            if ($vote->vote_type !== $validatedDate['vote_type']) {
                $vote->vote_type = $validatedDate['vote_type'];
                $vote->save();
            }
        } else {
            $vote->vote_type = $validatedDate['vote_type'];
            $vote->post_id = $id;
            $vote->user_id = $user->getKey();
            $vote->save();
        }

        return new PostVoteResource($vote);

    }
}