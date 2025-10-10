# Real-Time Online Status Guide

This guide explains how to use the real-time online/offline status indicators in the discussion platform API.

## Overview

The system provides real-time online status tracking for users with the following features:

-   **Automatic Activity Tracking**: Users are automatically marked as online when they make API requests
-   **Real-Time Broadcasting**: Status changes are broadcast to relevant chat rooms and users
-   **Multiple Status Types**: online, offline, away, busy
-   **Last Seen Tracking**: Track when users were last active
-   **Caching**: Efficient caching for performance

## Database Schema

The following fields have been added to the `users` table:

-   `last_seen_at`: Timestamp of last activity
-   `is_online`: Boolean indicating if user is currently online
-   `status`: String status (online, offline, away, busy)

## API Endpoints

### Set User Status

```http
POST /api/users/status
Authorization: Bearer {token}
Content-Type: application/json

{
    "status": "online"  // online, offline, away, busy
}
```

### Get Current User Status

```http
GET /api/users/status
Authorization: Bearer {token}
```

### Check Specific User Status

```http
GET /api/users/{userId}/status
Authorization: Bearer {token}
```

### Get All Online Users

```http
GET /api/users/online
Authorization: Bearer {token}
```

### Get Online Users for Chat Room

```http
GET /api/chat-rooms/{chatRoomId}/users/online
Authorization: Bearer {token}
```

## Real-Time Events

### UserStatusChanged Event

When a user's status changes, a `UserStatusChanged` event is broadcast with the following data:

```json
{
    "user": {
        "id": 123,
        "name": "John Doe"
    },
    "status": "online",
    "is_online": true,
    "last_seen_at": "2025-10-10T13:54:54.000000Z",
    "timestamp": "2025-10-10T13:54:54.000000Z"
}
```

### Broadcasting Channels

-   **Chat Room Channels**: `chat-room.{chatRoomId}` - Broadcasts to all members of a specific chat room
-   **User Status Channel**: `user-status` - General channel for user status updates

## WebSocket Integration

### Listening for Status Changes

```javascript
// Listen for user status changes in a chat room
Echo.private(`chat-room.${chatRoomId}`).listen("UserStatusChanged", (e) => {
    console.log("User status changed:", e);
    updateUserStatusIndicator(e.user.id, e.status, e.is_online);
});

// Listen for general user status changes
Echo.private("user-status").listen("UserStatusChanged", (e) => {
    console.log("User status changed:", e);
    updateUserStatusIndicator(e.user.id, e.status, e.is_online);
});
```

### Updating UI

```javascript
function updateUserStatusIndicator(userId, status, isOnline) {
    const userElement = document.querySelector(`[data-user-id="${userId}"]`);
    const statusIndicator = userElement.querySelector(".status-indicator");

    statusIndicator.className = `status-indicator ${status}`;
    statusIndicator.textContent = isOnline ? "Online" : "Offline";

    if (status === "away") {
        statusIndicator.textContent = "Away";
    } else if (status === "busy") {
        statusIndicator.textContent = "Busy";
    }
}
```

## Automatic Activity Tracking

The `TrackUserActivity` middleware automatically:

1. Updates `last_seen_at` timestamp for authenticated users
2. Marks users as online when they make requests
3. Throttles updates to once per minute to prevent excessive database writes
4. Uses caching to improve performance

## Status Types

-   **online**: User is actively using the platform
-   **offline**: User is not currently active
-   **away**: User is online but not actively engaged
-   **busy**: User is online but busy/do not disturb

## Cleanup Command

A cleanup command is available to mark users as offline if they haven't been active:

```bash
php artisan users:cleanup-offline
```

This command should be run periodically (e.g., every 15 minutes) to clean up users who haven't been seen in a while.

## Integration with Chat Events

The following chat events now include online status information:

-   **MessageSent**: Includes sender's online status
-   **TypingIndicator**: Includes user's online status
-   **ChatRoomUpdated**: Can include member status information

## Performance Considerations

-   **Caching**: User status is cached for 60 seconds to reduce database queries
-   **Throttling**: Activity updates are throttled to once per minute per user
-   **Indexing**: Database indexes on `last_seen_at`, `is_online`, and `status` fields
-   **Cleanup**: Regular cleanup of inactive users prevents database bloat

## Frontend Implementation Example

```javascript
class UserStatusManager {
    constructor() {
        this.onlineUsers = new Map();
        this.setupWebSocketListeners();
    }

    setupWebSocketListeners() {
        // Listen for status changes
        Echo.private("user-status").listen("UserStatusChanged", (e) => {
            this.updateUserStatus(e.user.id, e);
        });
    }

    updateUserStatus(userId, statusData) {
        this.onlineUsers.set(userId, {
            status: statusData.status,
            isOnline: statusData.is_online,
            lastSeen: statusData.last_seen_at,
        });

        this.updateUI(userId, statusData);
    }

    updateUI(userId, statusData) {
        const elements = document.querySelectorAll(
            `[data-user-id="${userId}"]`
        );
        elements.forEach((element) => {
            const indicator = element.querySelector(".status-indicator");
            if (indicator) {
                indicator.className = `status-indicator ${statusData.status}`;
                indicator.textContent = this.getStatusText(statusData.status);
            }
        });
    }

    getStatusText(status) {
        const statusTexts = {
            online: "Online",
            offline: "Offline",
            away: "Away",
            busy: "Busy",
        };
        return statusTexts[status] || "Unknown";
    }

    // Check if user is online
    isUserOnline(userId) {
        const user = this.onlineUsers.get(userId);
        return user && user.isOnline;
    }
}

// Initialize
const statusManager = new UserStatusManager();
```

## CSS Styling Example

```css
.status-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-left: 8px;
}

.status-indicator.online {
    background-color: #10b981; /* Green */
}

.status-indicator.offline {
    background-color: #6b7280; /* Gray */
}

.status-indicator.away {
    background-color: #f59e0b; /* Yellow */
}

.status-indicator.busy {
    background-color: #ef4444; /* Red */
}
```

This implementation provides a complete real-time online status system that integrates seamlessly with your existing chat functionality.
