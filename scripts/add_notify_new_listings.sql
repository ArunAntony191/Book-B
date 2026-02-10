-- Add notification preference for new book listings
-- This allows users to opt-in to receive notifications when new books are listed

ALTER TABLE users 
ADD COLUMN notify_new_listings BOOLEAN DEFAULT 0 
COMMENT 'User preference to receive notifications when new books are listed';

-- Update existing users to have the column (default is 0/OFF)
UPDATE users SET notify_new_listings = 0 WHERE notify_new_listings IS NULL;
