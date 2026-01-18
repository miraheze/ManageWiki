ALTER TABLE /*$wgDBprefix*/mw_namespaces
  ADD COLUMN ns_additional JSONB NOT NULL AFTER ns_core;
