ALTER TABLE /*$wgDBprefix*/mw_permissions
  ADD COLUMN perm_autopromotion LONGTEXT AFTER perm_removegroupsfromself;
