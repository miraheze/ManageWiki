CREATE TABLE mw_permissions (
	perm_dbname VARCHAR(64) NOT NULL,
	perm_group VARCHAR(64) NOT NULL,
	perm_permissions JSONB NOT NULL,
	perm_addgroups JSONB NOT NULL,
	perm_removegroups JSONB NOT NULL,
	perm_addgroupstoself JSONB NOT NULL,
	perm_removegroupsfromself JSONB NOT NULL,
	perm_autopromote JSONB DEFAULT NULL
);

CREATE UNIQUE INDEX uniqueperm ON mw_permissions (perm_dbname, perm_group);

CREATE INDEX perm_dbname ON mw_permissions (perm_dbname);

CREATE INDEX perm_group ON mw_permissions (perm_group);
