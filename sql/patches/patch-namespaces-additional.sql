ALTER TABLE /*$wgDBprefix*/mw_namespaces
  ADD COLUMN ns_additional JSON AFTER ns_core;
