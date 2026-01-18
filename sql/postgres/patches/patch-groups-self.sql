ALTER TABLE /*$wgDBprefix*/mw_permissions
  ADD COLUMN perm_addgroupstoself JSONB NOT NULL AFTER perm_removegroups,
  ADD COLUMN perm_removegroupsfromself JSONB NOT NULL AFTER perm_addgroupstoself;
