-- Add order_position column to department_post table
ALTER TABLE department_post ADD COLUMN IF NOT EXISTS order_position INT DEFAULT 0 AFTER status;

-- Update existing records to have sequential order positions
SET @row_number = 0;
UPDATE department_post 
SET order_position = (@row_number:=@row_number + 1)
ORDER BY id ASC;
