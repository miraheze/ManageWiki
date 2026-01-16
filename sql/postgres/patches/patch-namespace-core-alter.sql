ALTER TABLE /*$wgDBprefix*/mw_namespaces
  MODIFY COLUMN ns_core INT NOT NULL DEFAULT 0;
