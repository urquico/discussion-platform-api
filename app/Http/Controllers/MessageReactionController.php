<?php

namespace App\Http\Controllers;

use App\Models\MessageReaction;
use App\Models\ChatMessage;
use App\DTOs\ApiResponse;
use App\Events\MessageReactionUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MessageReactionController extends Controller
{
    /**
     * Add or update a reaction to a message.
     *
     * @param Request $request
     * @param int $messageId
     * @return JsonResponse
     */
    public function addReaction(Request $request, int $messageId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reaction_type' => 'required|string|in:heart,angry,wow',
            ]);

            $user = Auth::user();
            $message = ChatMessage::findOrFail($messageId);

            // Check if user has access to this message (is member of the chat room)
            $isMember = $message->chatRoom->users()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$isMember) {
                return ApiResponse::error('You are not authorized to react to this message.', 403)->toJsonResponse();
            }

            // Find existing reaction or create new one
            $reaction = MessageReaction::updateOrCreate(
                [
                    'message_id' => $messageId,
                    'user_id' => $user->id,
                ],
                [
                    'reaction_type' => $validated['reaction_type'],
                ]
            );

            // Load the reaction with user data
            $reaction->load('user:id,name');

            // Broadcast the reaction update
            broadcast(new MessageReactionUpdated($message, $reaction, 'added'))->toOthers();

            return ApiResponse::success(
                [
                    'reaction' => [
                        'id' => $reaction->id,
                        'user_id' => $reaction->user_id,
                        'user_name' => $reaction->user->name,
                        'reaction_type' => $reaction->reaction_type,
                        'emoji' => $reaction->getEmoji(),
                        'created_at' => $reaction->created_at,
                    ],
                    'reactions_summary' => $message->getReactionsSummary(),
                ],
                'Reaction added successfully'
            )->toJsonResponse();

        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                422
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'No query results for model [App\Models\ChatMessage].' ? 404 : 500;

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }

    /**
     * Remove a reaction from a message.
     *
     * @param int $messageId
     * @return JsonResponse
     */
    public function removeReaction(int $messageId): JsonResponse
    {
        try {
            $user = Auth::user();
            $message = ChatMessage::findOrFail($messageId);

            // Check if user has access to this message (is member of the chat room)
            $isMember = $message->chatRoom->users()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$isMember) {
                return ApiResponse::error('You are not authorized to remove reactions from this message.', 403)->toJsonResponse();
            }

            // Find and delete the user's reaction
            $reaction = MessageReaction::where('message_id', $messageId)
                ->where('user_id', $user->id)
                ->first();

            if (!$reaction) {
                return ApiResponse::error('No reaction found to remove.', 404)->toJsonResponse();
            }

            $reaction->delete();

            // Broadcast the reaction update
            broadcast(new MessageReactionUpdated($message, $reaction, 'removed'))->toOthers();

            return ApiResponse::success(
                [
                    'reactions_summary' => $message->getReactionsSummary(),
                ],
                'Reaction removed successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'No query results for model [App\Models\ChatMessage].' ? 404 : 500;

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }

    /**
     * Get all reactions for a message.
     *
     * @param int $messageId
     * @return JsonResponse
     */
    public function getReactions(int $messageId): JsonResponse
    {
        try {
            $user = Auth::user();
            $message = ChatMessage::findOrFail($messageId);

            // Check if user has access to this message (is member of the chat room)
            $isMember = $message->chatRoom->users()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$isMember) {
                return ApiResponse::error('You are not authorized to view reactions for this message.', 403)->toJsonResponse();
            }

            $reactionsWithUsers = $message->getReactionsWithUsers();
            $reactionsSummary = $message->getReactionsSummary();

            return ApiResponse::success(
                [
                    'reactions' => $reactionsWithUsers,
                    'reactions_summary' => $reactionsSummary,
                ],
                'Reactions retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'No query results for model [App\Models\ChatMessage].' ? 404 : 500;

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }
}