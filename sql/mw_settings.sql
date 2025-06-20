CREATE TABLE /*_*/mw_settings (
  s_dbname VARCHAR(64) NOT NULL PRIMARY KEY,
  s_settings JSON NULL,
  s_extensions JSON NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/s_dbname ON /*_*/mw_settings (s_dbname);
