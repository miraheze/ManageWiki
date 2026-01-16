CREATE TABLE mw_namespaces (
	ns_dbname VARCHAR(64) NOT NULL,
	ns_namespace_id BIGINT NOT NULL,
	ns_namespace_name VARCHAR(128) NOT NULL,
	ns_searchable SMALLINT NOT NULL,
	ns_subpages SMALLINT NOT NULL,
	ns_content SMALLINT NOT NULL,
	ns_content_model VARCHAR(32) NOT NULL,
	ns_protection VARCHAR(32) NOT NULL,
	ns_aliases JSONB NOT NULL,
	ns_core INT DEFAULT 0 NOT NULL,
	ns_additional JSONB NOT NULL
);

CREATE UNIQUE INDEX uniquens ON mw_namespaces (ns_dbname, ns_namespace_id);

CREATE INDEX ns_dbname ON mw_namespaces (ns_dbname);
