ALTER TABLE aliases ADD COLUMN enum text;
UPDATE aliases SET enum = comment;
ALTER TABLE aliases DROP COLUMN comment;
