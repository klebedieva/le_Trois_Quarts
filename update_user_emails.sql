-- SQL script to update user emails from @letroisquarts.com to @letroisquarts.online
-- Run this script on your hosting database

-- Update admin email
UPDATE users 
SET email = 'admin@letroisquarts.online' 
WHERE email = 'admin@letroisquarts.com';

-- Update moderator email
UPDATE users 
SET email = 'moderator@letroisquarts.online' 
WHERE email = 'moderator@letroisquarts.com';

-- Verify the changes (optional - check before running UPDATE if needed)
-- SELECT id, email, name, role FROM users WHERE email LIKE '%@letroisquarts.%';

