-- Add is_read column to notifications table
ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER message;

-- Add index for faster queries
ALTER TABLE notifications ADD INDEX idx_is_read (is_read);
