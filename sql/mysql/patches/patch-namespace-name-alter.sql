ALTER TABLE /*$wgDBprefix*/mw_namespaces
	MODIFY COLUMN ns_namespace_name VARCHAR(128) NOT NULL;
