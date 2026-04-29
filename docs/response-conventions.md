# sApi Response Conventions

This document defines the default response contract for `sApi`.

## Goals

- stay close to common REST API practice
- be easy to describe in OpenAPI 3.1
- feel familiar to Laravel developers
- avoid custom envelope noise

## Success Responses

Successful responses use a top-level `data` member.

### Resource collection

```json
{
  "data": [
    {
      "id": 1,
      "title": "News",
      "alias": "news"
    }
  ]
}
```

### Named collection

```json
{
  "data": {
    "categories": [
      {
        "id": 1,
        "title": "News",
        "alias": "news"
      }
    ]
  }
}
```

### Single resource

```json
{
  "data": {
    "id": 1,
    "title": "News",
    "alias": "news"
  }
}
```

### Optional message

Add `message` only when it carries useful human-readable context.

```json
{
  "data": {
    "id": 1
  },
  "message": "Category created."
}
```

## Error Responses

Error responses use `application/problem+json` and follow RFC 9457.

```json
{
  "type": "about:blank",
  "title": "Unauthorized",
  "status": 401,
  "detail": "Invalid credentials."
}
```

Additional extension members are allowed when needed.

```json
{
  "type": "about:blank",
  "title": "Unprocessable Entity",
  "status": 422,
  "detail": "Validation failed.",
  "errors": {
    "username": [
      "The username field is required."
    ]
  }
}
```

## Token Responses

Token endpoints should use familiar OAuth-style field names.

```json
{
  "data": {
    "access_token": "....",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

## Naming Rules

- use `snake_case` for JSON keys
- use plural names for collections
- prefer stable machine-readable identifiers like `id`, `alias`, `slug`
- do not use `object` as a generic payload key
- do not duplicate the HTTP status code in the response body unless the endpoint has a strong business reason

## OpenAPI Guidance

- describe success payloads under `data`
- describe errors with `application/problem+json`
- reuse shared schemas for repeated envelopes
- keep transport metadata in HTTP status and headers, not in custom body wrappers
