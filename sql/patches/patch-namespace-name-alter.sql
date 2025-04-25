ALTER TABLE /*$wgDBprefix*/mw_namespaces
	MODIFY COLUMN ns_namespace_name VARCHAR(256) NOT NULL;
