<?php

/**
 * Real-time Chat Integration Example
 * 
 * This file demonstrates how to use the Laravel Reverb real-time chat functionality
 * that has been integrated into your discussion platform API.
 */

// Example JavaScript code for the frontend to connect to Reverb and handle real-time messages

/*
// 1. Install Laravel Echo and Pusher JS (if not already installed)
// npm install --save laravel-echo pusher-js

// 2. Configure Echo in your frontend application
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.MIX_REVERB_APP_KEY,
    wsHost: process.env.MIX_REVERB_HOST,
    wsPort: process.env.MIX_REVERB_PORT ?? 80,
    wssPort: process.env.MIX_REVERB_PORT ?? 443,
    forceTLS: (process.env.MIX_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// 3. Listen for messages in a specific chat room
const chatRoomId = 1; // Replace with actual chat room ID

window.Echo.private(`chat-room.${chatRoomId}`)
    .listen('.message.sent', (e) => {
        console.log('New message received:', e.message);
        // Add the message to your chat UI
        addMessageToChat(e.message);
    })
    .listen('.message.edited', (e) => {
        console.log('Message edited:', e.message);
        // Update the message in your chat UI
        updateMessageInChat(e.message);
    })
    .listen('.message.deleted', (e) => {
        console.log('Message deleted:', e.message);
        // Remove or mark message as deleted in your chat UI
        removeMessageFromChat(e.message.id);
    });

// 4. Send a message via API
async function sendMessage(chatRoomId, message, messageType = 'text', replyToMessageId = null) {
    try {
        const response = await fetch(`/api/chat-rooms/${chatRoomId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getAuthToken()}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                message: message,
                message_type: messageType,
                reply_to_message_id: replyToMessageId
            })
        });

        if (response.ok) {
            const data = await response.json();
            console.log('Message sent successfully:', data.data);
            // The real-time event will be broadcast automatically
        } else {
            console.error('Failed to send message:', await response.text());
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
}

// 5. Get chat room messages
async function getChatRoomMessages(chatRoomId, page = 1, perPage = 50) {
    try {
        const response = await fetch(`/api/chat-rooms/${chatRoomId}/messages?page=${page}&per_page=${perPage}`, {
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
            }
        });

        if (response.ok) {
            const data = await response.json();
            console.log('Messages retrieved:', data.data);
            return data;
        } else {
            console.error('Failed to get messages:', await response.text());
        }
    } catch (error) {
        console.error('Error getting messages:', error);
    }
}

// 6. Edit a message
async function editMessage(messageId, newMessage) {
    try {
        const response = await fetch(`/api/messages/${messageId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getAuthToken()}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                message: newMessage
            })
        });

        if (response.ok) {
            const data = await response.json();
            console.log('Message edited successfully:', data.data);
            // The real-time event will be broadcast automatically
        } else {
            console.error('Failed to edit message:', await response.text());
        }
    } catch (error) {
        console.error('Error editing message:', error);
    }
}

// 7. Delete a message
async function deleteMessage(messageId) {
    try {
        const response = await fetch(`/api/messages/${messageId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (response.ok) {
            const data = await response.json();
            console.log('Message deleted successfully:', data.data);
            // The real-time event will be broadcast automatically
        } else {
            console.error('Failed to delete message:', await response.text());
        }
    } catch (error) {
        console.error('Error deleting message:', error);
    }
}

// Helper functions for UI updates
function addMessageToChat(message) {
    // Implement your UI logic here
    const chatContainer = document.getElementById('chat-messages');
    const messageElement = createMessageElement(message);
    chatContainer.appendChild(messageElement);
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function updateMessageInChat(message) {
    // Implement your UI logic here
    const messageElement = document.getElementById(`message-${message.id}`);
    if (messageElement) {
        messageElement.querySelector('.message-content').textContent = message.message;
        messageElement.classList.add('edited');
    }
}

function removeMessageFromChat(messageId) {
    // Implement your UI logic here
    const messageElement = document.getElementById(`message-${messageId}`);
    if (messageElement) {
        messageElement.querySelector('.message-content').textContent = '[Message deleted]';
        messageElement.classList.add('deleted');
    }
}

function createMessageElement(message) {
    const div = document.createElement('div');
    div.id = `message-${message.id}`;
    div.className = 'message';
    div.innerHTML = `
        <div class="message-header">
            <strong>${message.sender_name}</strong>
            <span class="timestamp">${new Date(message.created_at).toLocaleTimeString()}</span>
        </div>
        <div class="message-content">${message.message}</div>
        ${message.is_edited ? '<div class="edited-indicator">(edited)</div>' : ''}
    `;
    return div;
}

function getAuthToken() {
    // Implement your token retrieval logic here
    return localStorage.getItem('auth_token');
}
*/

/**
 * Environment Configuration Required
 * 
 * Add these variables to your .env file:
 * 
 * BROADCAST_CONNECTION=reverb
 * REVERB_APP_ID=your-app-id
 * REVERB_APP_KEY=your-app-key
 * REVERB_APP_SECRET=your-app-secret
 * REVERB_HOST=localhost
 * REVERB_PORT=8080
 * REVERB_SCHEME=http
 * 
 * For production, update REVERB_HOST to your domain and REVERB_SCHEME to https
 */

/**
 * Starting the Reverb Server
 * 
 * To start the Laravel Reverb server, run:
 * php artisan reverb:start
 * 
 * Or for production with specific configuration:
 * php artisan reverb:start --host=0.0.0.0 --port=8080
 */

/**
 * API Endpoints Summary
 * 
 * POST /api/chat-rooms/{chatRoomId}/messages
 * - Send a message to a chat room
 * - Body: { message: string, message_type?: string, reply_to_message_id?: int }
 * 
 * GET /api/chat-rooms/{chatRoomId}/messages
 * - Get messages for a chat room
 * - Query params: ?page=1&per_page=50
 * 
 * PUT /api/messages/{messageId}
 * - Edit a message
 * - Body: { message: string }
 * 
 * DELETE /api/messages/{messageId}
 * - Delete a message
 * 
 * All endpoints require authentication via Bearer token
 */

/**
 * Real-time Events
 * 
 * The following events are broadcast to the chat-room.{chatRoomId} channel:
 * 
 * - message.sent: When a new message is sent
 * - message.edited: When a message is edited
 * - message.deleted: When a message is deleted
 * 
 * Each event includes the message data and action type.
 */
