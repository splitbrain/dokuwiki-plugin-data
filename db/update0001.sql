CREATE TABLE data (eid INTEGER PRIMARY KEY, pid INTEGER, key, value);
CREATE INDEX idx_key ON data(key);
CREATE TABLE pages (pid INTEGER PRIMARY KEY, page, title);
CREATE UNIQUE INDEX idx_page ON pages(page);

