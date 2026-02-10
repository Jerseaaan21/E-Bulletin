-- Add Accreditation_Status module to the modules table (for regular departments)
INSERT INTO modules (name, description, icon) 
VALUES ('Accreditation_Status', 'Manage program accreditation status', 'fas fa-award');

-- Add Accreditation_Status module to the ceit_modules table (for CEIT/college level)
INSERT INTO ceit_modules (name, description, icon) 
VALUES ('Accreditation_Status', 'Manage program accreditation status', 'fas fa-award');

-- Verify the insertions
SELECT * FROM modules WHERE name = 'Accreditation_Status';
SELECT * FROM ceit_modules WHERE name = 'Accreditation_Status';
