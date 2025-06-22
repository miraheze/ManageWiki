ALTER TABLE /*$wgDBprefix*/mw_namespaces
  ADD COLUMN ns_additional JSON NOT NULL AFTER ns_core;
