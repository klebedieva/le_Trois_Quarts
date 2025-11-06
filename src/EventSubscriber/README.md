# Event Subscribers - Beginner's Guide

## What are Event Subscribers?

Event Subscribers are classes that automatically run code when certain events happen in your application. They work "behind the scenes" - you don't need to call them manually.

Think of them like automatic assistants that do things for you:
- When a user logs in → automatically log the event
- When a response is sent → automatically add security headers
- When an API request comes in → automatically check rate limits

## How They Work

1. **Symfony automatically discovers them** - If a class implements `EventSubscriberInterface`, Symfony finds it automatically
2. **They register for events** - The `getSubscribedEvents()` method tells Symfony which events to listen to
3. **They run automatically** - When the event happens, Symfony calls the subscriber's method

## Event Subscribers in This Project

### 1. SecurityHeadersSubscriber
**What it does:** Adds security headers to all HTTP responses

**When it runs:** After controller returns response, before sending to client

**Why it's useful:** Protects against XSS, clickjacking, and other attacks

**Example:** Every response gets `X-Frame-Options: DENY` header automatically

---

### 2. JsonFieldWhitelistSubscriber
**What it does:** Validates and filters JSON request data before it reaches controllers

**When it runs:** Before controller is called, only for API requests with JSON

**Why it's useful:** Prevents mass assignment attacks and DoS attacks

**Example:** If someone sends `{"email": "user@example.com", "isAdmin": true}`, the `isAdmin` field is automatically removed if it's not allowed

---

### 3. ApiRateLimitSubscriber
**What it does:** Limits how many requests a client can make to the API

**When it runs:** Before controller is called, only for API requests

**Why it's useful:** Prevents API abuse and DoS attacks

**Example:** If someone makes 100 requests per second, they get a 429 error after the limit

---

### 4. LoginAuditSubscriber
**What it does:** Logs all login attempts (successful and failed)

**When it runs:** When login succeeds or fails

**Why it's useful:** Helps track suspicious login attempts for security

**Example:** Every login attempt is logged with IP address, user email, and timestamp

---

### 5. InactivityLogoutSubscriber
**What it does:** Automatically logs out users who have been inactive for too long

**When it runs:** Before controller is called, only for `/admin/*` pages

**Why it's useful:** Prevents unauthorized access if someone leaves their computer unlocked

**Example:** If you're logged into admin panel and don't do anything for 30 minutes, you're automatically logged out

---

### 6. LastLoginSubscriber
**What it does:** Updates the user's last login timestamp when they log in

**When it runs:** When login succeeds

**Why it's useful:** Tracks when users last logged in (useful for analytics)

**Example:** After successful login, `User.lastLoginAt` is automatically updated to current time

## How to Debug Event Subscribers

### Check if a subscriber is running:
1. Add a `dump()` or `error_log()` statement in the subscriber method
2. Make a request that should trigger it
3. Check the output/logs

### Check response headers:
- Open browser DevTools → Network tab
- Look at response headers
- You should see headers added by `SecurityHeadersSubscriber`

### Check logs:
- Look in `var/log/` directory
- `LoginAuditSubscriber` logs to security audit log channel
- Check `config/packages/monolog.yaml` for log file locations

## Common Questions

### Q: Do I need to call these subscribers manually?
**A:** No! They run automatically. Just make sure the class implements `EventSubscriberInterface` and has `getSubscribedEvents()` method.

### Q: Can I disable a subscriber?
**A:** Yes, you can:
1. Remove it from `src/EventSubscriber/` directory, or
2. Comment out the class, or
3. Remove it from autowiring in `config/services.yaml`

### Q: How do I know which subscriber runs first?
**A:** Check the priority in `getSubscribedEvents()`:
- Lower priority number = runs earlier
- Example: Priority 8 runs before Priority 9

### Q: Can I create my own subscriber?
**A:** Yes! Just:
1. Create a class that implements `EventSubscriberInterface`
2. Add `getSubscribedEvents()` method
3. Add methods to handle the events
4. Symfony will automatically discover and register it

## Example: Creating Your Own Subscriber

```php
<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class MyCustomSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Your code here
        // This runs automatically on every request
    }
}
```

## Summary

Event Subscribers are powerful but "hidden" - they run automatically without you calling them. They're essential for:
- Security (headers, rate limiting, field validation)
- Logging (audit trails)
- User experience (auto-logout, last login tracking)

Don't worry if you don't see them in your controllers - they're working behind the scenes to keep your application secure and functional!

