-- The waitlist now serves the reviews platform's two audiences. 'panelist' is
-- retained only so rows captured before the pivot still validate.

ALTER TABLE waitlist
    MODIFY COLUMN role ENUM('business','agency','panelist') NOT NULL;
