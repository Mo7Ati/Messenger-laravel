# React frontend: exclude sender from broadcast (toOthers)

The Laravel API uses `broadcast()->toOthers()` so the user who sends a message does not receive their own message over the channel. For that to work, **the React app must send the current Echo socket ID** with the request that creates the message.

## What to do in the React project

When calling the API to send a message (e.g. `POST /api/messages`), add the header:

- **Header name:** `X-Socket-ID`
- **Header value:** current Echo socket ID (e.g. `Echo.socketId()`)

Laravel reads this header and tells the broadcaster to exclude that socket, so the sender won’t get the `MessageCreated` event.

## Option 1: Add header to the send-message request

Where you send the message (axios/fetch), add the header only for that request:

```js
// If you use axios and have Echo in scope
const sendMessage = async (conversationId, body) => {
  const { data } = await axios.post('/api/messages', 
    { conversation_id: conversationId, message: body },
    {
      headers: {
        'X-Socket-ID': Echo.socketId() ?? '',
      },
    }
  );
  return data;
};
```

```js
// If you use fetch
const sendMessage = async (conversationId, body) => {
  const res = await fetch('/api/messages', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Socket-ID': Echo.socketId() ?? '',
      // ... auth (e.g. Authorization: `Bearer ${token}`)
    },
    body: JSON.stringify({ conversation_id: conversationId, message: body }),
  });
  return res.json();
};
```

Use your real API base URL and auth if needed.

## Option 2: Axios interceptor (all API requests)

If you use one axios instance for the Laravel API, you can attach the socket ID to every request:

```js
import axios from 'axios';
import Echo from './echo'; // or wherever your Echo instance lives

axios.interceptors.request.use((config) => {
  if (Echo.socketId()) {
    config.headers['X-Socket-ID'] = Echo.socketId();
  }
  return config;
});
```

Then your existing `axios.post('/api/messages', ...)` will automatically include `X-Socket-ID`.

## Notes

- `Echo.socketId()` can be `null` before the WebSocket is connected; only set the header when it’s truthy (or use `?? ''` as in the examples).
- The backend does not need any change; it already uses `toOthers()` and reads `X-Socket-ID` from the request.
