# Safety Analysis: Update User Emails Command

## What the command does

The `app:update-user-emails` command **ONLY** modifies the `email` field in the `users` table.

## What is NOT affected

### Database Structure
- ✅ **User ID** (primary key) - Never changes
- ✅ **Password hashes** - Remain unchanged
- ✅ **User roles** - Remain unchanged  
- ✅ **Other user fields** - name, isActive, createdAt, lastLoginAt remain unchanged
- ✅ **Related entities** - No cascade updates

### Related Entities Analysis

1. **ContactMessage.repliedBy**
   - Uses `ManyToOne` relationship with `User` entity
   - Relationship is based on **User ID**, not email
   - Field is nullable (`nullable: true`)
   - **No cascade operations** defined
   - ✅ **Safe**: Changing email does not affect this relationship

2. **No other entities reference User.email**
   - All relationships use User ID (primary key)
   - Email is only used for authentication/login

### Database Constraints
- Email has `UNIQUE` constraint - command checks for duplicates before updating
- Email validation is performed before update
- Foreign key relationships use User ID, not email

## Code Analysis

### Command Implementation
```php
// Line 104: Only modifies email field
$user->setEmail($newEmail);

// Line 105: Persists only the User entity
$this->entityManager->persist($user);

// Line 109: Flush - only email changes are written to database
$this->entityManager->flush();
```

### Safety Features
1. ✅ **Dry-run mode** - Preview changes without applying
2. ✅ **Email validation** - Checks format before update
3. ✅ **Duplicate check** - Prevents overwriting existing emails
4. ✅ **Confirmation prompt** - Requires user confirmation (unless --force)
5. ✅ **Selective update** - Only updates users with matching domain

## Potential Side Effects

### Authentication
- Users will need to use new email to log in
- Old email will no longer work for authentication
- ⚠️ **Action required**: Users must be notified of email change

### Email Uniqueness
- Command checks for duplicate emails before updating
- If duplicate found, that user is skipped (not updated)

## Recommendations

1. **Always use --dry-run first**:
   ```bash
   php bin/console app:update-user-emails --old-domain=letroisquarts.com --new-domain=letroisquarts.online --dry-run
   ```

2. **Backup database** before running (standard practice)

3. **Notify users** about email change after update

4. **Test login** with new email after update

## Conclusion

✅ **The command is safe** - it only modifies email addresses in the users table and does not affect any other data in the database.

