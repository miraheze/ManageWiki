ALTER TABLE /*$wgDBprefix*/mw_permissions
  ADD COLUMN perm_revoke LONGTEXT AFTER perm_autopromote;
