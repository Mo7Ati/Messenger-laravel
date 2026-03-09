# Frontend: Inbox channel for real-time messages

The backend now uses a **single per-user private channel** for all real-time messages. Implement the following so the frontend receives every new message in real time, no matter which chat is open.

## Backend behavior (for context)

- **Channel:** Private channel per user: `messenger.user.{userId}` (subscribe as `Echo.private('messenger.user.' + userId)`).
- **Event:** `MessageCreated` is broadcast to **each participant’s** user channel when a message is sent in any conversation they belong to.
- **Sender exclusion:** The backend uses `toOthers()`, so the sender does not receive the event on their channel. The frontend must still add the sent message to the UI optimistically when the user sends it.
- **Payload:** Each event includes:
  - `message` – full message object (e.g. `id`, `body`, `created_at`, `is_mine`, `chat_id`, `user` with `id` and `name`, etc.).
  - `conversation_id` – ID of the conversation the message belongs to.

## What you need to implement

1. **Single subscription when the user is logged in**
   - Subscribe **once** to the current user’s private channel:  
     `Echo.private('messenger.user.' + currentUserId)`  
   - Do **not** subscribe per conversation. Do **not** use the old conversation channel (e.g. `messenger.{conversationId}`).
   - Keep this subscription active for the whole session (e.g. after login / when the messenger view is mounted).

2. **Listen for `MessageCreated`**
   - On `MessageCreated`, you receive `payload` with `message` and `conversation_id`.
   - Use `conversation_id` (or `message.chat_id`) to decide where the message belongs.

3. **Handle the event**
   - **If the message is for the conversation currently open:**  
     Append the received message to the current chat’s message list (so the user sees it in real time in the active chat).
   - **If the message is for another conversation:**  
     Update the conversation list (e.g. move that conversation to the top, update last message, set or increment unread), and optionally show a notification (toast / sound) like “New message from {message.user.name}” or “New message in [conversation]”.

4. **Sending messages**
   - When the user sends a message, add it to the UI immediately (optimistic update). The sender will **not** receive a `MessageCreated` event for their own message.
   - Keep sending the `X-Socket-ID` header (value: `Echo.socketId()`) with the request that creates the message (e.g. `POST /api/messages`) so the backend can exclude the sender’s socket and avoid duplicates.

5. **Cleanup**
   - When the user logs out or leaves the messenger, leave the private channel (e.g. `Echo.leave('messenger.user.' + userId)` or equivalent so the subscription is cleared).

## Summary

- **Subscribe:** One private channel per user: `Echo.private('messenger.user.' + currentUserId)`.
- **Listen:** Event name `MessageCreated`, payload: `{ message, conversation_id }`.
- **Routing:** Use `conversation_id` to update either the open chat or the conversation list + notifications.
- **Send:** Optimistic update on send; include `X-Socket-ID` on the send-message API request.
