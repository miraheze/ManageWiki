CREATE TABLE /*_*/mw_permissions (
  `perm_dbname` VARCHAR(64) NOT NULL,
  `perm_group` VARCHAR(64) NOT NULL,
  `perm_permissions` JSON NOT NULL,
  `perm_addgroups` JSON NOT NULL,
  `perm_removegroups` JSON NOT NULL,
  `perm_addgroupstoself` JSON NOT NULL,
  `perm_removegroupsfromself` JSON NOT NULL,
  `perm_autopromote` JSON,
  UNIQUE KEY `uniqueperm`(perm_dbname,perm_group)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/perm_dbname ON /*_*/mw_permissions (perm_dbname);
CREATE INDEX /*i*/perm_group ON /*_*/mw_permissions (perm_group);
