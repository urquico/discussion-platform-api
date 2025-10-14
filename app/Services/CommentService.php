<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Thread;
use App\Models\User;
use App\DTOs\CommentDTO;
use App\DTOs\ReplyDTO;
use App\DTOs\NestedReplyDTO;
use App\DTOs\ThreadDTO;
use Illuminate\Http\Request;
use App\Services\NotificationService;

/**
 * Comment Management Service
 *
 * Handles all comment and reply operations for threads, including creation, retrieval,
 * updating, deletion, and smart highlighting. Supports nested replies, vote counting,
 * and optimized pagination for large discussion threads.
 *
 * Features:
 * - Thread comment and reply management
 * - Nested reply and smart highlighting support
 * - Vote counting and engagement metrics
 * - Optimized pagination and sorting
 * - Authorization for comment actions
 *
 * @package App\Services
 * @author Christian Bangay
 * @version 1.0.0
 * @since 2025-07-31
 *
 * @see App\Models\Comment
 * @see App\Models\Thread
 * @see App\Models\User
 * @see App\DTOs\CommentDTO
 * @see App\DTOs\ReplyDTO
 * @see App\DTOs\NestedReplyDTO
 * @see App\DTOs\ThreadDTO
 */
class CommentService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get comments for a thread with reply structure and smart highlighting.
     *
     * @param Thread $thread
     * @param Request $request
     * @return array
     * @throws \Exception When authentication is required for author filter
     */
    public function getThreadComments(Thread $thread, Request $request): array
    {
        $perPage = $request->get('per_page', 10);
        $sort = $request->get('sort', 'recent');
        $highlightCommentId = $request->get('highlight_comment');
        $highlightReplyId = $request->get('highlight_reply');

        $commentsQuery = $thread->comments()
            ->whereNull('parent_id')
            ->with([
                'votes',
                'children' => function ($query) {
                    $query->with(['votes'])->orderBy('created_at', 'asc');
                },
                'children.children' => function ($query) {
                    $query->with(['votes'])->orderBy('created_at', 'asc');
                }
            ]);

        if ($request->has('author')) {
            $author = $request->get('author');

            if ($author === 'current_user') {
                if (!$request->user()) {
                    throw new \Exception('Authentication required for current_user filter.');
                }
                $author = $request->user()->name;
            }

            $commentsQuery->where('author', $author);
        }

        $this->applySorting($commentsQuery, $sort);

        if ($highlightCommentId || $highlightReplyId) {
            return $this->getCommentsWithSmartHighlighting(
                $commentsQuery,
                $thread,
                $request,
                $perPage,
                $highlightCommentId,
                $highlightReplyId
            );
        }

        $comments = $commentsQuery->paginate($perPage);

        $includeReplies = $request->get('include_replies', true);

        $transformedComments = $comments->getCollection()->map(function ($comment) use ($includeReplies, $highlightCommentId, $highlightReplyId) {
            $commentDto = $this->transformCommentWithReplies($comment, $includeReplies, $highlightCommentId, $highlightReplyId);
            return $commentDto->toArray();
        });

        return [
            'thread' => ThreadDTO::fromModel($thread)->toArray(),
            'comments' => $transformedComments->toArray(),
            'highlight_info' => [
                'comment_id' => $highlightCommentId,
                'reply_id' => $highlightReplyId,
                'has_highlight' => false,
                'found_in_current_page' => false,
            ],
            'pagination' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ];
    }

    /**
     * Create a new comment for a thread.
     *
     * @param Thread $thread
     * @param User $user
     * @param array $data
     * @return CommentDTO
     */
    public function createComment(Thread $thread, User $user, array $data): CommentDTO
    {
        $comment = $thread->comments()->create([
            'body' => $data['body'],
            'parent_id' => null,
            'author' => $user->name,
        ]);

        $comment->load('votes');
        $upvotes = $comment->votes()->where('type', 'upvote')->count();
        $downvotes = $comment->votes()->where('type', 'downvote')->count();
        $repliesCount = $comment->children()->count();

        $comment->upvotes = $upvotes;
        $comment->downvotes = $downvotes;
        $comment->vote_score = $upvotes - $downvotes;
        $comment->replies_count = $repliesCount;

        // Create notification for thread owner if commenter is not the thread author
        $this->createCommentNotification($thread, $user, $comment);

        return CommentDTO::fromModel($comment, []);
    }

    /**
     * Get replies for a specific comment with nested loading.
     *
     * @param Comment $comment
     * @param Request $request
     * @return array
     */
    public function getCommentReplies(Comment $comment, Request $request): array
    {
        $perPage = $request->get('per_page', 10);
        $sort = $request->get('sort', 'recent');

        $repliesQuery = $comment->children()->with(['votes']);

        $this->applySorting($repliesQuery, $sort);

        $replies = $repliesQuery->paginate($perPage);

        $nestedLimit = $request->get('nested_limit', 50);
        $includeNested = $request->get('include_nested', true);

        $transformedReplies = $replies->getCollection()->map(function ($reply) use ($nestedLimit, $includeNested) {
            return $this->transformReplyWithNested($reply, $nestedLimit, $includeNested);
        });

        return [
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'author' => $comment->author,
            ],
            'replies' => $transformedReplies,
            'pagination' => [
                'current_page' => $replies->currentPage(),
                'last_page' => $replies->lastPage(),
                'per_page' => $replies->perPage(),
                'total' => $replies->total(),
            ],
        ];
    }

    /**
     * Get nested replies for a specific reply with pagination.
     *
     * @param Comment $parentReply
     * @param Request $request
     * @return array
     * @throws \InvalidArgumentException When parent is not a reply
     */
    public function getNestedReplies(Comment $parentReply, Request $request): array
    {
        if ($parentReply->parent_id === null) {
            throw new \InvalidArgumentException("ID {$parentReply->id} is a top-level comment, not a reply.");
        }

        $perPage = $request->get('per_page', 10);
        $sort = $request->get('sort', 'recent');
        $offset = $request->get('offset', 0);

        $nestedRepliesQuery = $parentReply->children()->with(['votes']);

        $this->applySorting($nestedRepliesQuery, $sort);

        if ($offset > 0) {
            $nestedRepliesQuery->skip($offset);
        }

        $nestedReplies = $nestedRepliesQuery->paginate($perPage);

        $transformedNestedReplies = $nestedReplies->getCollection()->map(function ($nestedReply) use ($parentReply) {
            return $this->transformNestedReply($nestedReply, $parentReply);
        });

        return [
            'parent_reply' => [
                'id' => $parentReply->id,
                'body' => $parentReply->body,
                'author' => $parentReply->author,
            ],
            'nested_replies' => $transformedNestedReplies,
            'pagination' => [
                'current_page' => $nestedReplies->currentPage(),
                'last_page' => $nestedReplies->lastPage(),
                'per_page' => $nestedReplies->perPage(),
                'total' => $nestedReplies->total(),
            ],
        ];
    }

    /**
     * Create a reply to a top-level comment.
     *
     * @param Comment $targetComment
     * @param User $user
     * @param array $data
     * @return ReplyDTO
     * @throws \InvalidArgumentException When target is not a top-level comment
     */
    public function createReplyToComment(Comment $targetComment, User $user, array $data): ReplyDTO
    {
        if ($targetComment->parent_id !== null) {
            throw new \InvalidArgumentException("ID {$targetComment->id} is not a top-level comment.");
        }

        $reply = Comment::create([
            'body' => $data['body'],
            'thread_id' => $targetComment->thread_id,
            'parent_id' => $targetComment->id,
            'author' => $user->name,
        ]);

        $reply->load('votes');

        $upvotes = $reply->votes()->where('type', 'upvote')->count();
        $downvotes = $reply->votes()->where('type', 'downvote')->count();

        // Create notification for comment author if replier is not the comment author
        $this->createReplyNotification($targetComment, $user, $reply);

        return ReplyDTO::fromArray([
            'id' => $reply->id,
            'thread_id' => $reply->thread_id,
            'parent_id' => $reply->parent_id,
            'body' => $reply->body,
            'author' => $reply->author,
            'replying_to' => null,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'vote_score' => $upvotes - $downvotes,
            'nested_replies' => [],
            'nested_replies_count' => 0,
            'created_at' => $reply->created_at?->toISOString() ?? $reply->created_at,
            'updated_at' => $reply->updated_at?->toISOString() ?? $reply->updated_at,
        ]);
    }

    /**
     * Create a reply to a reply (nested reply).
     *
     * @param Comment $targetReply
     * @param User $user
     * @param array $data
     * @return NestedReplyDTO
     * @throws \InvalidArgumentException When target is not a reply
     */
    public function createReplyToReply(Comment $targetReply, User $user, array $data): NestedReplyDTO
    {
        if ($targetReply->parent_id === null) {
            throw new \InvalidArgumentException("ID {$targetReply->id} is a top-level comment, not a reply.");
        }

        $reply = Comment::create([
            'body' => $data['body'],
            'thread_id' => $targetReply->thread_id,
            'parent_id' => $targetReply->parent_id, // Flatten to original parent
            'author' => $user->name,
        ]);

        $reply->load('votes');

        $upvotes = $reply->votes()->where('type', 'upvote')->count();
        $downvotes = $reply->votes()->where('type', 'downvote')->count();

        // Create notification for reply author if replier is not the reply author
        $this->createReplyNotification($targetReply, $user, $reply);

        return NestedReplyDTO::fromArray([
            'id' => $reply->id,
            'body' => $reply->body,
            'author' => $reply->author,
            'replying_to' => $targetReply->author,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'vote_score' => $upvotes - $downvotes,
            'created_at' => $reply->created_at?->toISOString() ?? $reply->created_at,
            'updated_at' => $reply->updated_at?->toISOString() ?? $reply->updated_at,
        ]);
    }

    /**
     * Delete a comment if user is authorized.
     *
     * @param Comment $comment
     * @param User $user
     * @return void
     * @throws \Exception When user is not authorized
     */
    public function deleteComment(Comment $comment, User $user): void
    {
        if ($comment->author !== $user->name) {
            throw new \Exception('You can only delete your own comments.');
        }

        $comment->delete();
    }

    /**
     * Update a comment if user is authorized.
     *
     * @param Comment $comment
     * @param User $user
     * @param array $data
     * @return CommentDTO
     * @throws \Exception When user is not authorized
     */
    public function updateComment(Comment $comment, User $user, array $data): CommentDTO
    {
        if ($comment->author !== $user->name) {
            throw new \Exception('You can only edit your own comments.');
        }

        $comment->update([
            'body' => $data['body'],
        ]);

        $comment->refresh();

        $comment->load([
            'votes', // For vote counting
            'children' => function ($query) {
                $query->with('votes')->orderBy('created_at', 'asc');
            }
        ]);

        $votesCollection = collect($comment->votes);
        $upvotes = $votesCollection->where('type', 'upvote')->count();
        $downvotes = $votesCollection->where('type', 'downvote')->count();
        $repliesCount = $comment->children->count();

        $replies = [];
        $parentId = $comment->getAttribute('parent_id');
        if (is_null($parentId) && $repliesCount > 0) {
            $replies = $comment->children->map(function ($reply) {
                return $this->transformReply($reply, null);
            })->toArray();
        }

        return CommentDTO::fromArray([
            'id' => $comment->id,
            'thread_id' => $comment->thread_id,
            'parent_id' => $parentId, // Use the safely retrieved parent_id
            'body' => $comment->body,
            'author' => $comment->author,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'vote_score' => $upvotes - $downvotes,
            'replies' => $replies,
            'replies_count' => $repliesCount,
            'is_highlighted' => false,
            'created_at' => $comment->created_at?->toISOString() ?? $comment->created_at,
            'updated_at' => $comment->updated_at?->toISOString() ?? $comment->updated_at,
        ]);
    }

    /**
     * Apply sorting to query based on sort parameter.
     *
     * @param $query
     * @param string $sort
     * @return void
     */
    private function applySorting($query, string $sort): void
    {
        switch ($sort) {
            case 'popular':
                $query->withCount(['votes as upvotes' => function ($q) {
                    $q->where('type', 'upvote');
                }])->orderBy('upvotes', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'recent':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
    }

    /**
     * Get comments with smart highlighting for visibility.
     *
     * @param $commentsQuery
     * @param Thread $thread
     * @param Request $request
     * @param int $perPage
     * @param $highlightCommentId
     * @param $highlightReplyId
     * @return array
     */
    private function getCommentsWithSmartHighlighting($commentsQuery, Thread $thread, Request $request, int $perPage, $highlightCommentId, $highlightReplyId): array
    {
        $currentPage = $request->get('page', 1);
        $includeReplies = $request->get('include_replies', true);

        // First, try normal pagination
        $comments = $commentsQuery->paginate($perPage);
        $foundHighlight = false;
        $highlightLocation = null;
        $highlightedItem = null;

        // Check if highlighted comment is in current page
        if ($highlightCommentId) {
            $foundHighlight = $comments->getCollection()->contains('id', $highlightCommentId);
            if (!$foundHighlight) {
                $highlightedItem = $this->findHighlightedComment($thread, $highlightCommentId, $commentsQuery);
            }
        }

        // Check if highlighted reply is in current page (within replies of current comments)
        if ($highlightReplyId && !$foundHighlight && $includeReplies) {
            foreach ($comments->getCollection() as $comment) {
                $replyFound = $comment->children()->where('id', $highlightReplyId)->exists();
                if ($replyFound) {
                    $foundHighlight = true;
                    break;
                }
            }

            if (!$foundHighlight) {
                $highlightedItem = $this->findHighlightedReply($thread, $highlightReplyId);
            }
        }

        // If highlight not found, include it in results
        $extraItem = null;
        if (!$foundHighlight && $highlightedItem) {
            $extraItem = $highlightedItem;
            $highlightLocation = $this->calculateHighlightLocation($commentsQuery, $highlightedItem, $perPage);
        }

        // Transform comments
        $transformedComments = $comments->getCollection()->map(function ($comment) use ($includeReplies, $highlightCommentId, $highlightReplyId) {
            $commentDto = $this->transformCommentWithReplies($comment, $includeReplies, $highlightCommentId, $highlightReplyId);
            return $commentDto->toArray();
        });

        // Add extra highlighted item if needed
        if ($extraItem) {
            $extraCommentDto = $this->transformCommentWithReplies($extraItem, $includeReplies, $highlightCommentId, $highlightReplyId);
            $transformedComments->prepend($extraCommentDto->toArray());
        }

        return [
            'thread' => ThreadDTO::fromModel($thread)->toArray(),
            'comments' => $transformedComments->toArray(),
            'highlight_info' => [
                'comment_id' => $highlightCommentId,
                'reply_id' => $highlightReplyId,
                'has_highlight' => (bool)($highlightCommentId || $highlightReplyId),
                'found_in_current_page' => $foundHighlight,
                'included_from_other_page' => !$foundHighlight && $extraItem !== null,
                'natural_location' => $highlightLocation,
                'note' => !$foundHighlight && $extraItem ? 'Highlighted item included from different page for visibility' : null,
            ],
            'pagination' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
                'showing_extra_item' => $extraItem !== null,
            ],
        ];
    }

    /**
     * Find highlighted comment by ID.
     *
     * @param Thread $thread
     * @param $commentId
     * @param $baseQuery
     * @return mixed
     */
    private function findHighlightedComment(Thread $thread, $commentId, $baseQuery)
    {
        // Clone the base query to maintain filters and sorting
        $query = clone $baseQuery;
        return $query->where('id', $commentId)->first();
    }

    /**
     * Find highlighted reply and its parent comment.
     *
     * @param Thread $thread
     * @param $replyId
     * @return mixed
     */
    private function findHighlightedReply(Thread $thread, $replyId)
    {
        $reply = Comment::where('thread_id', $thread->id)
            ->where('id', $replyId)
            ->whereNotNull('parent_id')
            ->first();

        if ($reply && $reply->parent_id) {
            // Return the parent comment so we can show the reply in context
            return Comment::where('id', $reply->parent_id)->first();
        }

        return null;
    }

    /**
     * Calculate where the highlighted item would naturally appear.
     *
     * @param $commentsQuery
     * @param $highlightedItem
     * @param int $perPage
     * @return array|null
     */
    private function calculateHighlightLocation($commentsQuery, $highlightedItem, int $perPage)
    {
        if (!$highlightedItem) return null;

        // Count comments that would appear before this one with current sorting
        $query = clone $commentsQuery;
        $countBefore = $query->where('id', '<', $highlightedItem->id)->count();

        $naturalPage = intval(ceil(($countBefore + 1) / $perPage));
        $positionInPage = ($countBefore % $perPage) + 1;

        return [
            'natural_page' => $naturalPage,
            'position_in_page' => $positionInPage,
            'url_to_natural_location' => "?page={$naturalPage}",
        ];
    }

    /**
     * Transform comment with replies and vote counts.
     *
     * @param $comment
     * @param bool $includeReplies
     * @param $highlightCommentId
     * @param $highlightReplyId
     * @return CommentDTO
     */
    private function transformCommentWithReplies($comment, bool $includeReplies, $highlightCommentId, $highlightReplyId): CommentDTO
    {
        // Use collection filtering instead of query filtering to avoid N+1 queries
        $votesCollection = collect($comment->votes);
        $upvotes = $votesCollection->where('type', 'upvote')->count();
        $downvotes = $votesCollection->where('type', 'downvote')->count();
        $repliesCount = $comment->children->count();

        // Load complete reply structure if requested
        $replies = [];
        if ($includeReplies && $repliesCount > 0) {
            $replies = $comment->children->map(function ($reply) use ($highlightReplyId) {
                return $this->transformReply($reply, $highlightReplyId);
            })->toArray();
        }

        // Set calculated values on the comment model for DTO creation
        $comment->upvotes = $upvotes;
        $comment->downvotes = $downvotes;
        $comment->vote_score = $upvotes - $downvotes;
        $comment->replies_count = $repliesCount;

        // Check if this comment is highlighted
        $isHighlighted = $highlightCommentId && $comment->id == $highlightCommentId;

        // Remove thread and parent relationships to avoid additional queries for performance
        $comment->setRelation('thread', null);
        $comment->setRelation('parent', null);

        return CommentDTO::fromModel($comment, $replies, $isHighlighted);
    }

    /**
     * Transform reply with nested replies.
     *
     * @param $reply
     * @param $highlightReplyId
     * @return array
     */
    private function transformReply($reply, $highlightReplyId)
    {
        // Use collection filtering for performance
        $votesCollection = collect($reply->votes);
        $replyUpvotes = $votesCollection->where('type', 'upvote')->count();
        $replyDownvotes = $votesCollection->where('type', 'downvote')->count();
        $nestedRepliesCount = $reply->children->count();

        // Load ALL nested replies from preloaded data
        $nestedReplies = [];
        if ($nestedRepliesCount > 0) {
            $nestedReplies = $reply->children->map(function ($nestedReply) use ($reply, $highlightReplyId) {
                // Use collection filtering for nested replies too
                $nestedVotesCollection = collect($nestedReply->votes);
                $nestedUpvotes = $nestedVotesCollection->where('type', 'upvote')->count();
                $nestedDownvotes = $nestedVotesCollection->where('type', 'downvote')->count();

                return [
                    'id' => $nestedReply->id,
                    'body' => $nestedReply->body,
                    'author' => $nestedReply->author,
                    'replying_to' => $reply->author,
                    'upvotes' => $nestedUpvotes,
                    'downvotes' => $nestedDownvotes,
                    'vote_score' => $nestedUpvotes - $nestedDownvotes,
                    'is_highlighted' => $highlightReplyId && $nestedReply->id == $highlightReplyId,
                    'created_at' => $nestedReply->created_at,
                    'updated_at' => $nestedReply->updated_at,
                ];
            })->toArray();
        }

        return [
            'id' => $reply->id,
            'body' => $reply->body,
            'author' => $reply->author,
            'replying_to' => null,
            'upvotes' => $replyUpvotes,
            'downvotes' => $replyDownvotes,
            'vote_score' => $replyUpvotes - $replyDownvotes,
            'nested_replies' => $nestedReplies,
            'nested_replies_count' => $nestedRepliesCount,
            'is_highlighted' => $highlightReplyId && $reply->id == $highlightReplyId,
            'created_at' => $reply->created_at,
            'updated_at' => $reply->updated_at,
        ];
    }

    /**
     * Transform reply with smart nested loading.
     *
     * @param $reply
     * @param int $nestedLimit
     * @param bool $includeNested
     * @return array
     */
    private function transformReplyWithNested($reply, int $nestedLimit, bool $includeNested): array
    {
        // Use preloaded votes
        $upvotes = $reply->votes->where('type', 'upvote')->count();
        $downvotes = $reply->votes->where('type', 'downvote')->count();
        $nestedRepliesCount = $reply->children->count();

        // Smart loading logic using preloaded data
        $nestedReplies = [];
        $hasMoreNested = false;
        $loadMoreUrl = null;

        if ($includeNested && $nestedRepliesCount > 0) {
            if ($nestedRepliesCount <= $nestedLimit) {
                // Use all preloaded nested replies
                $nestedRepliesData = $reply->children;
            } else {
                // Take only first N nested replies from preloaded data
                $nestedRepliesData = $reply->children->take($nestedLimit);
                $hasMoreNested = true;
                $loadMoreUrl = "/api/replies/{$reply->id}/nested?offset={$nestedLimit}";
            }

            // Transform nested replies
            $nestedReplies = $nestedRepliesData->map(function ($nestedReply) use ($reply) {
                return $this->transformNestedReply($nestedReply, $reply);
            })->toArray();
        }

        return [
            'id' => $reply->id,
            'body' => $reply->body,
            'author' => $reply->author,
            'replying_to' => null,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'vote_score' => $upvotes - $downvotes,
            'nested_replies' => $nestedReplies,
            'nested_replies_count' => $nestedRepliesCount,
            'nested_replies_shown' => count($nestedReplies),
            'nested_replies_remaining' => $hasMoreNested ? $nestedRepliesCount - count($nestedReplies) : 0,
            'has_more_nested' => $hasMoreNested,
            'load_more_url' => $loadMoreUrl,
            'created_at' => $reply->created_at,
            'updated_at' => $reply->updated_at,
        ];
    }

    /**
     * Transform nested reply with target author info.
     *
     * @param $nestedReply
     * @param $parentReply
     * @return array
     */
    private function transformNestedReply($nestedReply, $parentReply): array
    {
        // Use preloaded votes
        $upvotes = $nestedReply->votes->where('type', 'upvote')->count();
        $downvotes = $nestedReply->votes->where('type', 'downvote')->count();

        return [
            'id' => $nestedReply->id,
            'body' => $nestedReply->body,
            'author' => $nestedReply->author,
            'replying_to' => $parentReply->author,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'vote_score' => $upvotes - $downvotes,
            'created_at' => $nestedReply->created_at,
            'updated_at' => $nestedReply->updated_at,
        ];
    }

    /**
     * Create a notification for reply creation.
     *
     * @param Comment $targetComment
     * @param User $replier
     * @param Comment $reply
     * @return void
     */
    private function createReplyNotification(Comment $targetComment, User $replier, Comment $reply): void
    {
        // Don't notify if the replier is the target comment author
        if ($replier->name === $targetComment->author) {
            return;
        }

        // Find the target comment author user
        $targetCommentAuthor = User::where('name', $targetComment->author)->first();
        if (!$targetCommentAuthor) {
            return;
        }

        // Create reply notification
        $this->notificationService->createReplyNotification(
            $targetCommentAuthor,
            $replier,
            $targetComment->body,
            $targetComment->id,
            $targetComment->thread_id
        );
    }

    /**
     * Create a notification for comment creation.
     *
     * @param Thread $thread
     * @param User $commenter
     * @param Comment $comment
     * @return void
     */
    private function createCommentNotification(Thread $thread, User $commenter, Comment $comment): void
    {
        // Don't notify if the commenter is the thread author
        if ($commenter->name === $thread->author) {
            return;
        }

        // Find the thread author user
        $threadAuthor = User::where('name', $thread->author)->first();
        if (!$threadAuthor) {
            return;
        }

        // Create comment notification
        $this->notificationService->createCommentNotification(
            $threadAuthor,
            $commenter,
            $thread->title,
            $thread->id
        );
    }
}
