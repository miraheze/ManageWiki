INSERT INTO mw_namespaces (ns_dbname, ns_namespace_id, ns_namespace_name, ns_searchable, ns_subpages, ns_content, ns_content_model, ns_protection, ns_aliases, ns_core, ns_additional)
VALUES 
('default', 0, '<Main>', 1, 1, 1, 'wikitext', '', '[]', 0, '[]'),
('default', 1, 'Talk', 0, 1, 0, 'wikitext', '', '[]', 0, '[]'),
('default', 2, 'User', 0, 1, 0, 'wikitext', '', '[]', 0, '[]'),
('default', 3, 'User_talk', 0, 1, 0, 'wikitext', '', '[]', 1, '[]'),
('default', 4, 'Project', 1, 1, 0, 'wikitext', '', '[]', 0, '[]'),
('default', 5, 'Project_talk', 0, 1, 0, 'wikitext', '', '[]', 1, '[]'),
('default', 6, 'File', 0, 0, 0, 'wikitext', '', '["Image"]', 1, '[]'),
('default', 7, 'File_talk', 0, 1, 0, 'wikitext', '', '["Image_talk"]', 1, '[]'),
('default', 8, 'MediaWiki', 0, 1, 0, 'wikitext', 'editinterface', '[]', 1, '[]'),
('default', 9, 'MediaWiki_talk', 0, 1, 0, 'wikitext', '', '[]', 1, '[]'),
('default', 10, 'Template', 0, 1, 0, 'wikitext', '', '[]', 1, '[]'),
('default', 11, 'Template_talk', 0, 1, 0, 'wikitext', '', '[]', 1, '[]'),
('default', 12, 'Help', 0, 1, 0, 'wikitext', '', '[]', 0, '[]'),
('default', 13, 'Help_talk', 0, 1, 0, 'wikitext', '', '[]', 1, '[]'),
('default', 14, 'Category', 0, 0, 0, 'wikitext', '', '[]', 1, '[]'),
('default', 15, 'Category_talk', 0, 1, 0, 'wikitext', '', '[]', 1, '[]');
