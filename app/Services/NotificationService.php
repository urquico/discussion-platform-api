<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Create a comment notification.
     */
    public function createCommentNotification(User $recipient, User $commenter, string $threadTitle, int $threadId): Notification
    {
        return $recipient->createNotification(
            'comment',
            'New Comment',
            "{$commenter->name} commented on \"{$threadTitle}\"",
            [
                'commenter_id' => $commenter->id,
                'commenter_name' => $commenter->name,
                'thread_id' => $threadId,
                'thread_title' => $threadTitle,
            ],
            "/threads/{$threadId}",
            'comment-icon',
            $commenter->id
        );
    }

    /**
     * Create a reply notification.
     */
    public function createReplyNotification(User $recipient, User $replier, string $commentBody, int $commentId, int $threadId): Notification
    {
        // Truncate comment body for display
        $truncatedComment = strlen($commentBody) > 50 ? substr($commentBody, 0, 50) . '...' : $commentBody;
        
        return $recipient->createNotification(
            'reply',
            'New Reply',
            "{$replier->name} replied to your comment: \"{$truncatedComment}\"",
            [
                'replier_id' => $replier->id,
                'replier_name' => $replier->name,
                'comment_id' => $commentId,
                'comment_body' => $commentBody,
                'thread_id' => $threadId,
            ],
            "/threads/{$threadId}",
            'reply-icon',
            $replier->id
        );
    }

    /**
     * Create a vote notification.
     */
    public function createVoteNotification(User $recipient, User $voter, string $contentType, string $contentTitle, int $contentId, string $voteType = 'upvote'): Notification
    {
        // Determine vote action text and icon
        $voteAction = $voteType === 'upvote' ? 'liked' : 'disliked';
        $voteEmoji = $voteType === 'upvote' ? '👍' : '👎';
        $voteIcon = $voteType === 'upvote' ? 'thumbs-up-icon' : 'thumbs-down-icon';
        
        // Determine action URL based on content type
        $actionUrl = "/{$contentType}s/{$contentId}";
        if ($contentType === 'review') {
            // For review votes, navigate to the associated protocol
            $review = \App\Models\Review::find($contentId);
            if ($review && $review->protocol_id) {
                $actionUrl = "/protocols/{$review->protocol_id}";
            }
        }
        
        return $recipient->createNotification(
            'vote',
            "New {$voteAction}",
            "{$voteEmoji} {$voter->name} {$voteAction} your {$contentType}: \"{$contentTitle}\"",
            [
                'voter_id' => $voter->id,
                'voter_name' => $voter->name,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'content_title' => $contentTitle,
                'vote_type' => $voteType,
                'vote_action' => $voteAction,
            ],
            $actionUrl,
            $voteIcon,
            $voter->id
        );
    }

    /**
     * Create a review notification.
     */
    public function createReviewNotification(User $recipient, User $reviewer, string $protocolTitle, int $protocolId): Notification
    {
        return $recipient->createNotification(
            'review',
            'New Review',
            "{$reviewer->name} reviewed your protocol: \"{$protocolTitle}\"",
            [
                'reviewer_id' => $reviewer->id,
                'reviewer_name' => $reviewer->name,
                'protocol_id' => $protocolId,
                'protocol_title' => $protocolTitle,
            ],
            "/protocols/{$protocolId}",
            'review-icon',
            $reviewer->id
        );
    }

    /**
     * Create a chat message notification.
     */
    public function createChatNotification(User $recipient, User $sender, string $chatRoomName, int $chatRoomId): Notification
    {
        // Check if a similar notification already exists in the last 5 minutes to prevent duplicates where it is still unread
        $existingNotification = Notification::where('user_id', $recipient->id)
            ->where('type', 'chat')
            ->where('sender_id', $sender->id)
            ->where('data->chat_room_id', $chatRoomId)
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->whereNull('read_at')
            ->first();

        // if type is group chat, message is "{name} sent a message in \"{chatRoomName}\""
        // if type is private chat, message is "{name} sent you a message"
        $message = $chatRoomName !== '' ? "{$sender->name} sent a message in \"{$chatRoomName}\"" : "{$sender->name} sent you a message";

        if ($existingNotification) {
            // Update the notification to refresh the timestamp and trigger broadcast
            $existingNotification->touch(); // This updates the updated_at timestamp

            // Load the sender relationship before broadcasting
            $existingNotification->load('sender');

            // manually broadcast the notification event since touch doesn't trigger it
            broadcast(new \App\Events\NotificationSent($existingNotification, $existingNotification->user));
            
            return $existingNotification;
        }

     
        
        return $recipient->createNotification(
            'chat',
            'New Message',
            $message,
            [
                'sender_id' => $sender->id,
                'sender_name' => $sender->name,
                'chat_room_id' => $chatRoomId,
                'chat_room_name' => $chatRoomName,
            ],
            "/chats",
            'chat-icon',
            $sender->id
        );
    }

    /**
     * Create a system notification.
     */
    public function createSystemNotification(User $recipient, string $title, string $message, ?array $data = null, ?string $actionUrl = null): Notification
    {
        return $recipient->createNotification(
            'system',
            $title,
            $message,
            $data,
            $actionUrl,
            'system-icon'
        );
    }
}
