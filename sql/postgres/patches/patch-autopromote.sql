ALTER TABLE /*$wgDBprefix*/mw_permissions
  ADD COLUMN perm_autopromote JSONB AFTER perm_removegroupsfromself;
