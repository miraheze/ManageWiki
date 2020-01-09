-- Convert namespaces and permission table to use indexes for the db column
ALTER TABLE /*$wgDBprefix*/mw_namespaces
  ADD INDEX ns_dbname (ns_dbname);

ALTER TABLE /*$wgDBprefix*/mw_permissions
  ADD INDEX perm_dbname (perm_dbname);
