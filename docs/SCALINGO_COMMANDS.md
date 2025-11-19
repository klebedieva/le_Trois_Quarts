# Running Symfony Commands on Scalingo

This guide explains how to execute Symfony console commands on your Scalingo hosting.

## Prerequisites

1. **Install Scalingo CLI** (if not already installed):
   ```bash
   # macOS
   brew tap scalingo/scalingo
   brew install scalingo
   
   # Linux
   curl -O https://cli.scalingo.com
   chmod +x scalingo
   sudo mv scalingo /usr/local/bin/
   
   # Windows (via Chocolatey)
   choco install scalingo
   ```

2. **Login to Scalingo**:
   ```bash
   scalingo login
   ```

## Running Commands

### Method 1: Using `scalingo run` (Recommended)

Execute a one-off command in your application container:

```bash
# General syntax
scalingo --app <your-app-name> run php bin/console <command>

# Example: Update user emails
scalingo --app le-trois-quarts run php bin/console app:update-user-emails --old-domain=letroisquarts.com --new-domain=letroisquarts.online --dry-run

# Example: Update user emails (with confirmation)
scalingo --app le-trois-quarts run php bin/console app:update-user-emails --old-domain=letroisquarts.com --new-domain=letroisquarts.online

# Example: Update user emails (force, no confirmation)
scalingo --app le-trois-quarts run php bin/console app:update-user-emails --old-domain=letroisquarts.com --new-domain=letroisquarts.online --force
```

### Method 2: Using SSH (Alternative)

If you need an interactive session:

```bash
# Open SSH session
scalingo --app <your-app-name> run bash

# Then run commands normally
php bin/console app:update-user-emails --old-domain=letroisquarts.com --new-domain=letroisquarts.online
```

## Finding Your App Name

If you don't know your app name:

```bash
# List all your apps
scalingo apps

# Or check in Scalingo dashboard: https://dashboard.scalingo.com
```

## Complete Example: Updating User Emails

```bash
# Step 1: Check what will be changed (dry-run)
scalingo --app le-trois-quarts run php bin/console app:update-user-emails \
  --old-domain=letroisquarts.com \
  --new-domain=letroisquarts.online \
  --dry-run

# Step 2: If everything looks good, run the actual update
scalingo --app le-trois-quarts run php bin/console app:update-user-emails \
  --old-domain=letroisquarts.com \
  --new-domain=letroisquarts.online \
  --force
```

## Other Useful Commands

```bash
# Clear cache
scalingo --app le-trois-quarts run php bin/console cache:clear

# List all available commands
scalingo --app le-trois-quarts run php bin/console list

# Show help for a specific command
scalingo --app le-trois-quarts run php bin/console app:update-user-emails --help

# Create a new admin
scalingo --app le-trois-quarts run php bin/console app:create-admin

# Update passwords
scalingo --app le-trois-quarts run php bin/console app:update-passwords --email=admin@letroisquarts.online
```

## Environment Variables

Scalingo automatically provides environment variables to your commands. Make sure your `.env` or environment variables in Scalingo dashboard are properly configured:

- `DATABASE_URL` - Database connection string
- `APP_ENV` - Environment (prod, dev, etc.)
- Other Symfony environment variables

## Troubleshooting

### Command not found
If you get "command not found", make sure you're using the full path:
```bash
scalingo --app <app-name> run php bin/console <command>
```

### Database connection issues
Check your `DATABASE_URL` environment variable in Scalingo dashboard:
```bash
scalingo --app <app-name> env | grep DATABASE_URL
```

### Permission errors
Some commands might need specific permissions. Check Scalingo logs:
```bash
scalingo --app <app-name> logs --lines 100
```

## Notes

- Commands run in a temporary container, so file system changes won't persist
- Database changes will persist (they're stored in your database)
- Use `--dry-run` flag when available to preview changes before applying them
- Always backup your database before running destructive commands

