CREATE TABLE /*_*/mw_settings (
  s_dbname VARCHAR(64) NOT NULL PRIMARY KEY,
  s_settings LONGTEXT NULL,
  s_extensions LONGTEXT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/s_dbname ON /*_*/mw_settings (s_dbname);
