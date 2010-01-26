CREATE TABLE aliases (name, type, prefix, postfix, comment);
CREATE UNIQUE INDEX idx_name ON aliases(name);
