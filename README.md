# Messenger

A real-time messaging API built with **Laravel 12**. Provides a complete backend for chat applications with peer-to-peer and group conversations, contact management, file attachments, read receipts, and instant WebSocket-based message delivery.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Authentication](#authentication)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Real-Time Broadcasting](#real-time-broadcasting)
- [File Storage](#file-storage)
- [API Response Format](#api-response-format)

---

## Overview

Messenger is a RESTful API that handles everything needed for a real-time messaging platform: user authentication, contact relationships, one-on-one and group messaging, file attachments, read receipts, and live WebSocket delivery.

Authentication is handled through **Laravel Sanctum in SPA mode** -- session-based with cookie authentication, not API tokens. This makes it ideal for single-page application frontends that run on a stateful domain.

Real-time features are powered by **Laravel Reverb** (self-hosted WebSocket server) or **Pusher** as an alternative, with events broadcast over private per-user channels.

---

## Features

### Messaging
- **Peer-to-peer conversations** -- direct messages between two users, with automatic peer chat creation on first message
- **Group chats** -- multi-participant conversations with a label and optional avatar
- **File attachments** -- images, documents, audio, and video sent alongside or instead of text (up to 5 MB each)
- **Read receipts** -- per-recipient tracking of who has read each message and when
- **Mark as read** -- mark all unread messages in a conversation as read at once
- **Message archiving** -- soft deletes preserve message history without permanent removal
- **Last message preview** -- each chat tracks its most recent message for conversation lists

### Contact Management
- **Contact request lifecycle** -- send, accept, reject, and remove contacts
- **Status tracking** -- contacts move through statuses: pending, accepted, cancelled, blocked, removed
- **Search contacts** -- find accepted contacts by name, email, or phone
- **Unread counts** -- pending contact request count included in the contacts list response

### Group Management
- **Create groups** -- set a label and optional avatar, add initial participants
- **Admin role** -- the group creator is assigned the admin role
- **Add/remove members** -- admin-only operations to manage group participants
- **Admin transfer** -- when an admin leaves, the role transfers to the next member automatically
- **Auto-cleanup** -- if only one participant remains after someone leaves, the group is deleted

### Authentication & Users
- **Session-based SPA auth** -- Laravel Sanctum cookie authentication with CSRF protection
- **Social OAuth login** -- Google and GitHub via Laravel Socialite, with automatic account linking for existing emails
- **Email verification** -- required verification flow with resend support (rate-limited to 6 per minute)
- **Password reset** -- full forgot/reset password flow via email
- **Login throttling** -- 5 attempts per email/IP combination, then 60-second lockout
- **User profiles** -- name, email, bio, phone number, and avatar
- **Discoverability** -- boolean flag controlling whether a user appears in search results
- **Last active tracking** -- `last_active_at` updated automatically via middleware on every authenticated request
- **User search** -- find discoverable users by name, email, phone, or bio (minimum 2 characters, limited to 20 results)

### Real-Time
- **WebSocket broadcasting** via Laravel Reverb or Pusher
- **Instant message delivery** -- `MessageCreated` broadcasts immediately (ShouldBroadcastNow) to all chat participants
- **Contact request alerts** -- receiver is notified in real-time when a request is sent, sender is notified when it's accepted or rejected
- **Group notifications** -- users are notified when added to a group chat

---

## Tech Stack

### Backend
| Technology | Version | Purpose |
|---|---|---|
| PHP | 8.2+ | Runtime |
| Laravel | 12.0 | Framework |
| Laravel Sanctum | 4.0 | SPA cookie authentication |
| Laravel Reverb | 1.0 | Self-hosted WebSocket server |
| Laravel Socialite | 5.25 | OAuth (Google, GitHub) |
| Laravel Telescope | 5.18 | Debugging & request monitoring |
| MySQL | 8.0+ | Database |

### Frontend Assets
| Technology | Version | Purpose |
|---|---|---|
| Vite | 7.0 | Build tool |
| Tailwind CSS | 4.0 | CSS framework |
| Laravel Echo | 2.3 | WebSocket client |
| Pusher.js | 8.4 | WebSocket transport |
| Axios | 1.11 | HTTP client |

### Development & Testing
| Tool | Purpose |
|---|---|
| Pest | Testing framework (PHPUnit-based) |
| Laravel Pint | Code style & linting |
| Laravel Pail | Real-time log viewer |
| Laravel Sail | Docker development environment |

---

## Architecture

```
app/
├── Enums/                  # ChatTypeEnum (peer/group), ContactStatusEnum
├── Events/                 # MessageCreated, ContactRequestSent, ContactRequestUpdated, AddedToGroup
├── Helpers/                # successResponse(), errorResponse(), locale helpers
├── Http/
│   ├── Controllers/
│   │   ├── Auth/           # Login, Register, SocialAuth, Password, Email verification
│   │   ├── ChatController
│   │   ├── MessagesController
│   │   ├── ContactController
│   │   ├── ProfileController
│   │   └── UserController
│   ├── Middleware/          # updateUserLastActive
│   ├── Requests/           # LoginRequest, SendContactRequestRequest, UpdateProfileRequest, UpdatePasswordRequest
│   └── Resources/          # UserResource, ChatResource, MessageResource, MessageAttachmentResource
├── Models/                 # User, Chat, Message, Contact, Attachment, Participant, Recipient, SocialAccount
└── Providers/              # AppServiceProvider, BroadcastServiceProvider

routes/
├── api.php                 # Protected API routes (auth:sanctum)
├── auth.php                # Authentication routes (login, register, OAuth, password reset, email verify)
├── channels.php            # Broadcast channel authorization
└── web.php                 # Web routes
```

### Design Patterns
- **RESTful API** with consistent JSON response structure (`success`, `message`, `data`)
- **API Resources** -- all responses pass through JsonResource transformers (UserResource, ChatResource, MessageResource)
- **Form Request validation** -- dedicated request classes encapsulate validation rules and authorization
- **Event-driven broadcasting** -- real-time updates decoupled from controllers via Laravel Events
- **Database transactions** -- atomic operations for message creation (with recipients and attachments) and group management
- **Eager loading** -- `with()` used throughout to prevent N+1 query problems
- **Pivot models** -- Participant and Recipient are custom pivot models with additional logic (auto-setting `joined_at`, soft deletes)
- **Enums** -- PHP backed enums for chat types and contact statuses with label helpers

---

## Authentication

Messenger uses **Laravel Sanctum in SPA (stateful) mode** -- authentication is session-based with HTTP-only cookies, not bearer tokens. The frontend must:

1. Be served from a stateful domain configured in Sanctum
2. Include credentials with every request (`withCredentials: true`)
3. Obtain a CSRF cookie from `/sanctum/csrf-cookie` before login

### Auth Flow

| Step | Request | Description |
|---|---|---|
| 1 | `GET /sanctum/csrf-cookie` | Obtain CSRF token cookie |
| 2 | `POST /api/login` | Authenticate with email/password, establishes session |
| 3 | Subsequent requests | Session cookie sent automatically, `auth:sanctum` middleware validates |

### Social OAuth Flow

| Step | Request | Description |
|---|---|---|
| 1 | `GET /api/auth/{provider}/redirect` | Redirects to Google or GitHub |
| 2 | Provider redirects back | `GET /api/auth/{provider}/callback` |
| 3 | Server creates/links user | If email exists, links social account; otherwise creates new user |

### Rate Limiting

- **Login**: 5 attempts per email/IP, then 60-second lockout
- **Email verification**: 6 requests per minute

---

## API Endpoints

All routes under `auth:sanctum` require an active session (cookie-based).

### Authentication Routes

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/register` | Guest | Register (name, email, password, optional avatar) |
| `POST` | `/api/login` | Guest | Login with email/password |
| `POST` | `/api/logout` | Auth | Destroy session |
| `GET` | `/api/auth/{provider}/redirect` | -- | Redirect to OAuth provider |
| `GET` | `/api/auth/{provider}/callback` | -- | Handle OAuth callback |
| `POST` | `/api/forgot-password` | Guest | Send password reset link |
| `POST` | `/api/reset-password` | Guest | Reset password with token |
| `GET` | `/api/verify-email/{id}/{hash}` | Auth | Verify email (signed URL) |
| `POST` | `/api/email/verification-notification` | Auth | Resend verification email |
| `PUT` | `/api/password` | Auth | Update password |

### Chat Routes

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/chats` | List user's chats with participants and last message. Filter by `?type=peer` or `?type=group` |
| `GET` | `/api/chats/{chat}` | Get chat with all messages, attachments, and participants |
| `POST` | `/api/chats` | Create group chat (`label`, `participants_ids[]`, optional `avatar`) |
| `POST` | `/api/chats/{chat}/mark-as-read` | Mark all unread messages in chat as read |
| `POST` | `/api/chats/{chat}/participants` | Add participant to group (admin only) |
| `DELETE` | `/api/chats/{chat}/participants/{userId}` | Remove participant from group (admin only) |
| `POST` | `/api/chats/{chat}/avatar` | Update group avatar (admin only) |
| `POST` | `/api/chats/{chat}/leave` | Leave group chat |

### Message Routes

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/messages` | Send a message. Provide `chat_id` or `user_id` (auto-creates peer chat). Accepts `message` (text) and/or `attachments[]` (files) |
| `GET` | `/api/messages/attachments/{attachment}` | Get temporary download URL (expires in 30 minutes) |

### Contact Routes

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/contacts/search` | Search accepted contacts by name, email, or phone |
| `GET` | `/api/contacts` | List accepted contacts (includes pending request count) |
| `GET` | `/api/contacts/requests` | List pending received contact requests |
| `GET` | `/api/contacts/sent` | List pending sent contact requests |
| `GET` | `/api/contacts/{contact}` | Get contact details with their peer chat |
| `POST` | `/api/contacts/request` | Send contact request (`receiver_id`) |
| `POST` | `/api/contacts/accept/{user}` | Accept a pending contact request |
| `POST` | `/api/contacts/reject/{user}` | Reject a pending contact request |
| `DELETE` | `/api/contacts/{user}` | Remove an accepted contact |

### Profile & User Routes

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/user` | Get authenticated user info |
| `POST` | `/api/user/profile` | Update profile (name, email, bio, phone, avatar) |
| `DELETE` | `/api/user/avatar` | Delete profile avatar |
| `PUT` | `/api/user/password` | Update password (current_password + password + confirmation) |
| `GET` | `/api/users/search` | Search discoverable users (min 2 chars, max 20 results) |

---

## Database Schema

### Tables & Columns

**users**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string | unique |
| email_verified_at | timestamp | nullable |
| password | string | hashed |
| phone | string | unique, nullable |
| is_discoverable | boolean | default: true |
| bio | text | nullable |
| avatar | string | nullable |
| last_active_at | timestamp | nullable, auto-updated by middleware |
| remember_token | string | |
| created_at / updated_at | timestamps | |

**chats**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK -> users | nullable, creator |
| last_message_id | FK -> messages | nullable |
| label | string | nullable, used for group names |
| avatar | string | nullable, group avatar |
| type | enum | `peer` or `group`, default: `peer` |
| created_at / updated_at | timestamps | |

**participants** (pivot)
| Column | Type | Notes |
|---|---|---|
| user_id | FK -> users | PK (composite) |
| chat_id | FK -> chats | PK (composite) |
| role | enum | `admin` or `member`, default: `member` |
| joined_at | timestamp | auto-set on creation |

**messages**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| chat_id | FK -> chats | cascade delete |
| user_id | FK -> users | nullable (null on user delete) |
| body | text | message content |
| type | enum | `text` or `attachment`, default: `text` |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft deletes |

**recipients** (pivot)
| Column | Type | Notes |
|---|---|---|
| user_id | FK -> users | PK (composite) |
| message_id | FK -> messages | PK (composite) |
| read_at | timestamp | nullable, set when user reads message |
| deleted_at | timestamp | soft deletes |

**contacts**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| sender_id | FK -> users | cascade delete |
| receiver_id | FK -> users | cascade delete |
| status | enum | `pending`, `accepted`, `blocked`, `cancelled`, `removed` |
| accepted_at | timestamp | nullable |
| created_at / updated_at | timestamps | |
| unique | (sender_id, receiver_id) | |

**message_attachments**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| message_id | FK -> messages | cascade delete |
| path | string | storage path |
| original_name | string | original filename |
| mime_type | string(100) | nullable |
| size | unsigned bigint | nullable, in bytes |
| created_at / updated_at | timestamps | |

**social_accounts**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK -> users | cascade delete |
| provider | string | `google` or `github` |
| provider_id | string | |
| provider_token | text | nullable |
| provider_refresh_token | text | nullable |
| created_at / updated_at | timestamps | |
| unique | (provider, provider_id) | |

### Entity Relationships

```
User ─────┬──── sentMessages ──────────── Message
          │                                  │
          ├──── chats (many-to-many) ─── Chat ──── lastMessage
          │       via Participant             │
          │                                  ├──── messages (has many)
          ├──── contactsSent ─────────── Contact
          ├──── contactsReceived             │
          │                                  └──── sender / receiver (Users)
          ├──── receivedMessages
          │       via Recipient ─────── read_at tracking
          │
          └──── socialAccounts ──── SocialAccount

Message ──┬──── attachments ──── Attachment
          └──── recipients ───── Recipient (read_at, soft deletes)
```

---

## Real-Time Broadcasting

Events are broadcast over private per-user WebSocket channels. Each user subscribes to `messenger.user.{userId}`.

### Broadcast Events

| Event | Type | Channel | Payload |
|---|---|---|---|
| `MessageCreated` | ShouldBroadcastNow | `messenger.user.{userId}` for each participant | Message model (serialized) |
| `ContactRequestSent` | ShouldBroadcast | `messenger.user.{receiverId}` | Contact request with sender info |
| `ContactRequestUpdated` | ShouldBroadcast | `messenger.user.{senderId}` | Updated contact with receiver info |
| `AddedToGroup` | ShouldBroadcast | `messenger.user.{newUserId}` or all participants | Group data (ChatResource) |

- `MessageCreated` uses **ShouldBroadcastNow** (dispatched immediately, not queued)
- Other events use **ShouldBroadcast** (dispatched through the queue)
- `MessageCreated` uses `toOthers()` so the sender does not receive their own message

### Channel Authorization

```php
// Only the authenticated user can listen to their own channel
Broadcast::channel('messenger.user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

// General messenger channel - any authenticated user
Broadcast::channel('messenger', fn ($user) => $user);
```

---

## File Storage

### Supported Uploads

| Category | Allowed Formats | Max Size |
|---|---|---|
| User avatars | jpeg, jpg, png, gif, webp | 2 MB |
| Group avatars | jpeg, jpg, png, gif, svg | 2 MB |
| Message attachments | jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, zip, mp3, mp4, wav | 5 MB |

### Storage Locations

| Content | Disk | Path |
|---|---|---|
| User avatars | public | `avatars/` |
| Group avatars | public | `group-avatars/` |
| Message attachments | local | `attachments/` |

- Attachment downloads are served via **temporary URLs** that expire after 30 minutes
- Old avatars are automatically deleted when a new one is uploaded
- AWS S3 can be configured as an alternative storage driver

---

## API Response Format

All endpoints return a consistent JSON structure:

**Success:**
```json
{
  "success": true,
  "message": "Success",
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error description"
}
```

### Resource Transformations

**UserResource** returns: `id`, `name`, `email`, `avatar`, `bio`, `phone`, and conditionally `contact_status` (contacts/request_sent/request_received/none) and `role` (in group context).

**ChatResource** returns: `id`, `type`, `label` (resolved -- group label or other user's name for peer chats), `avatar` (resolved -- group avatar or other user's avatar for peer chats), `participants` (filtered -- all for groups, only the other user for peer chats), `messages`, `last_message`, `new_messages` (unread count), `created_at`.

**MessageResource** returns: `id`, `chat_id`, `body`, `type`, `is_mine`, `user_id`, `user`, `attachments`, `created_at`, and for the sender's own messages: `status` with `is_read_by_all` and `readers` list.

---

## License

This project is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).
