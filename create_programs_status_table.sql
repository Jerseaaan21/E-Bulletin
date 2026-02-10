-- Create programs_status table for storing department programs and accreditation status
-- Run this in phpMyAdmin or MySQL to create the table

CREATE TABLE IF NOT EXISTS `programs_status` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `dept_id` INT(11) NOT NULL,
  `program_name` VARCHAR(255) NOT NULL,
  `program_code` VARCHAR(50) NOT NULL,
  `accreditation_level` VARCHAR(50) DEFAULT NULL,
  `accreditation_date` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dept_id` (`dept_id`),
  CONSTRAINT `programs_status_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Fields:
-- id: Primary key
-- dept_id: Foreign key linking to departments table
-- program_name: Full name of the program (e.g., "Bachelor of Science in Information Technology")
-- program_code: Short code for the program (e.g., "BSIT")
-- accreditation_level: Level of accreditation (e.g., "Level I", "Level II", "Level III", "Level IV") - Optional
-- accreditation_date: Date when accreditation was granted - Optional

-- Note: Accreditation fields are nullable and not required yet
