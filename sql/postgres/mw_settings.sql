CREATE TABLE mw_settings (
	s_dbname VARCHAR(64) NOT NULL,
	s_settings JSONB DEFAULT NULL,
	s_extensions JSONB DEFAULT NULL,
	PRIMARY KEY(s_dbname)
);

CREATE INDEX s_dbname ON mw_settings (s_dbname);
