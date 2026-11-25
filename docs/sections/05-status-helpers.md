## Status Helpers & Error Codes

Each constructor sets the status, and the error helpers also set a message and a code. The default messages are translated in the application locale; a message you pass is sent as it is. Codes never change with the locale.

### Success Helpers

| Constructor | Status | Body |
| --- | --- | --- |
| `Response::success($data = null)` | 200 | the envelope |
| `Response::created($data = null)` | 201 | the envelope |
| `Response::accepted($data = null)` | 202 | the envelope |
| `Response::noContent()` | 204 | none |

Called without an argument, `success()`, `created()` and `accepted()` send no `data` field.

### Error Helpers

| Constructor | Status | Default message | Code |
| --- | --- | --- | --- |
| `Response::error($message, $status = 400)` | 400 or the given status | your message | none |
| `Response::unauthorized($message = null)` | 401 | Unauthenticated. | `UNAUTHENTICATED` |
| `Response::forbidden($message = null)` | 403 | This action is unauthorized. | `FORBIDDEN` |
| `Response::notFound($message = null)` | 404 | Not Found. | `NOT_FOUND` |
| `Response::validationError($errors, $message = null)` | 422 | The given data was invalid. | `VALIDATION_ERROR` |

```php title="app/Http/Controllers/RegisterController.php"
return Response::validationError(['email' => ['The email field is required.']]);
```

```json title="Response" no-copy
{
  "success": false,
  "message": "The given data was invalid.",
  "code": "VALIDATION_ERROR",
  "errors": {"email": ["The email field is required."]}
}
```

### What Each Response Carries

A success carries `data`; an error carries `errors`. Calling `data()` on an error or `errors()` on a success throws an `InvalidArgumentException`, so `success` never contradicts the body. Put the details of an error in `errors()` or `meta()`. `message`, `code`, `meta`, notifications, request IDs and SEO tags work on both.

```php
Response::error('Payment failed.', 402)->meta('retry_after', 30);   // fine
Response::error('Payment failed.', 402)->data($order);              // InvalidArgumentException
Response::success($order)->errors(['card' => ['Declined.']]);       // InvalidArgumentException
```

### Translated Messages

The default messages come from `api-response::messages` (`unauthenticated`, `forbidden`, `not_found`, `validation`), so with the locale set to `ar` `Response::notFound()` sends `"message": "غير موجود."`. A locale without the line falls back to English. See [Exception Rendering](#exceptions) for the full list of keys and how to publish them.

### Changing the Status

`status()` sets any status from 200 to 599, but it keeps the response honest: a success keeps a status below 400 and an error one of 400 or more. Anything else throws an `InvalidArgumentException`.

```php
Response::error('Conflict.')->status(409);     // 409, "success": false
Response::success($post)->status(203);         // 203, "success": true
Response::success()->status(422);              // InvalidArgumentException: use Response::error()
```

A redirect status (3xx) counts as a success. Set the `Location` header yourself with `header()`.

### Error Codes

An error code is a stable identifier your clients can switch on. It is not the HTTP status and not the message you translate. `code()` accepts letters, digits, `_`, `.`, `:` and `-`, and `code(null)` removes it:

```php
Response::error(__('payments.balance'), 422)->code('INSUFFICIENT_BALANCE');
```

### Responses Without a Body

`204` and `304` responses send only their headers: no envelope, notifications or metadata. A `HEAD` request gets the headers of the response, `Content-Type` included, with an empty body.

```php
return Response::noContent()->header('X-Deleted', '1');
```
