ALTER TABLE /*$wgDBprefix*/mw_permissions
  ADD COLUMN perm_addgroupstoself JSON NOT NULL AFTER perm_removegroups,
  ADD COLUMN perm_removegroupsfromself JSON NOT NULL AFTER perm_addgroupstoself;
