CREATE TABLE rpmdb (eid INTEGER PRIMARY KEY, pid INTEGER, value);
INSERT INTO rpmdb (eid,pid,value) SELECT eid,pid,value FROM data WHERE key='rpm';
DELETE FROM data WHERE key='rpm';
