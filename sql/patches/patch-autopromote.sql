ALTER TABLE /*$wgDBprefix*/mw_permissions
  ADD COLUMN perm_autopromote JSON AFTER perm_removegroupsfromself;
