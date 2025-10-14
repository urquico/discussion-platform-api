<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string|null $remember_token
 * @property-read array $activity_stats
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Protocol[] $protocols
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Thread[] $threads
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Comment[] $comments
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Review[] $reviews
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Vote[] $votes
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_admin',
        'last_seen_at',
        'is_online',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'last_seen_at' => 'datetime',
        'is_online' => 'boolean',
    ];


    /**
     * Get the votes by the user.
     *
     * @return HasMany
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }


    /**
     * Get the protocols created by the user.
     *
     * @return HasMany
     */
    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class, 'author', 'name');
    }


    /**
     * Get the threads created by the user.
     *
     * @return HasMany
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class, 'author', 'name');
    }

    /**
     * Get the comments created by the user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'author', 'name');
    }

    /**
     * Get the reviews created by the user.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'author', 'name');
    }

    /**
     * Get the chat rooms the user belongs to.
     */
    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_users')
                    ->withPivot('role', 'is_active', 'joined_at')
                    ->withTimestamps();
    }

    /**
     * Get the user's activity statistics.
     */
    public function getActivityStatsAttribute(): array
    {
        return [
            'total_protocols' => $this->protocols()->count(),
            'total_threads' => $this->threads()->count(),
            'total_comments' => $this->comments()->count(),
            'total_reviews' => $this->reviews()->count(),
            'total_votes_received' => $this->getTotalVotesReceived(),
        ];
    }

    /**
     * Get total votes received by the user across all their content.
     */
    public function getTotalVotesReceived(): int
    {
        $protocolVotes = $this->protocols()
            ->withCount(['reviews'])
            ->get()
            ->sum('reviews_count');

        $threadVotes = $this->threads()
            ->withCount(['votes'])
            ->get()
            ->sum('votes_count');

        $commentVotes = $this->comments()
            ->withCount(['votes'])
            ->get()
            ->sum('votes_count');

        return $protocolVotes + $threadVotes + $commentVotes;
    }

    /**
     * Check if the user has verified their email.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark the user's email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Mark the user as online.
     */
    public function markAsOnline(): bool
    {
        return $this->update([
            'is_online' => true,
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Mark the user as offline.
     */
    public function markAsOffline(): bool
    {
        return $this->update([
            'is_online' => false,
            'status' => 'offline',
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Update the user's last seen timestamp.
     */
    public function updateLastSeen(): bool
    {
        return $this->update([
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Set the user's status (online, offline, away, busy).
     */
    public function setStatus(string $status): bool
    {
        $validStatuses = ['online', 'offline', 'away', 'busy'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $updateData = ['status' => $status];
        
        if ($status === 'online') {
            $updateData['is_online'] = true;
            $updateData['last_seen_at'] = now();
        } elseif ($status === 'offline') {
            $updateData['is_online'] = false;
            $updateData['last_seen_at'] = now();
        }

        return $this->update($updateData);
    }

    /**
     * Check if the user is currently online.
     */
    public function isOnline(): bool
    {
        return $this->is_online && $this->status === 'online';
    }

    /**
     * Check if the user was recently active (within last 5 minutes).
     */
    public function isRecentlyActive(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Get the user's online status for display.
     */
    public function getOnlineStatusAttribute(): string
    {
        if ($this->isOnline()) {
            return 'online';
        }

        if ($this->isRecentlyActive()) {
            return 'recently_active';
        }

        return 'offline';
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadNotificationsCount(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Get recent notifications (last 10).
     */
    public function getRecentNotifications(int $limit = 10)
    {
        return $this->notifications()
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllNotificationsAsRead(): int
    {
        return Notification::markAllAsReadForUser($this->id);
    }

    /**
     * Create a notification for this user.
     */
    public function createNotification(
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        ?string $actionUrl = null,
        ?string $icon = null,
        ?int $senderId = null
    ): Notification {
        return Notification::createForUser(
            $this->id,
            $type,
            $title,
            $message,
            $data,
            $actionUrl,
            $icon,
            $senderId
        );
    }
}
