INSERT INTO mw_permissions (perm_dbname, perm_group, perm_permissions, perm_addgroups, perm_removegroups, perm_addgroupstoself, perm_removegroupsfromself, perm_autopromote)
VALUES
('default', '*', '["autocreateaccount","createaccount","edit","createpage","createtalk","viewmywatchlist","editmywatchlist","viewmyprivateinfo","editmyprivateinfo","editmyoptions","autocreateaccount"]', '[]', '[]', '[]', '[]', NULL),
('default', 'autoconfirmed', '["autoconfirmed","editsemiprotected","createaccount"]', '[]', '[]', '[]', '[]', '["&",[1,10],[2,345600]]'),
('default', 'autopatrolled', '["autopatrol","patrol","patrolmarks"]', '[]', '[]', '[]', '[]', NULL),
('default', 'bot', '["bot","autoconfirmed","editsemiprotected","nominornewtalk","autopatrol","suppressredirect","apihighlimits"]', '[]', '[]', '[]', '[]', NULL),
('default', 'bureaucrat', '["noratelimit","managewiki-core","managewiki-settings","managewiki-permissions","managewiki-namespaces"]', '["bot","bureaucrat","sysop","interface-admin"]', '["bot","sysop","interface-admin"]', '[]', '[]', NULL),
('default', 'confirmed', '["editsemiprotected","autoconfirmed"]', '[]', '[]', '[]', '[]', NULL),
('default', 'interface-admin', '["editsitecss","editsitejson","editsitejs","editinterface","editusercss","edituserjson","edituserjs"]', '[]', '[]', '[]', '[]', NULL),
('default', 'member', '["read"]', '[]', '[]', '[]', '[]', NULL),
('default', 'rollbacker', '["rollback"]', '[]', '[]', '[]', '[]', NULL),
('default', 'sysop', '["editsitecss","upload_by_url","editsitejson","editsitejs","block","createaccount","delete","deletedhistory","deletedtext","undelete","editinterface","editusercss","edituserjson","edituserjs","import","importupload","move","move-subpages","move-rootuserpages","move-categorypages","patrol","autopatrol","protect","editprotected","rollback","upload","reupload","reupload-shared","unwatchedpages","autoconfirmed","editsemiprotected","ipblock-exempt","blockemail","markbotedits","apihighlimits","browsearchive","noratelimit","movefile","unblockself","suppressredirect","mergehistory","managechangetags","deletechangetags","deletelogentry","deleterevision","patrolmarks"]', '["autopatrolled","confirmed","rollbacker"]', '["autopatrolled","confirmed","rollbacker"]', '[]', '[]', NULL),
('default', 'user', '["move","move-subpages","move-rootuserpages","move-categorypages","movefile","edit","createpage","createtalk","upload","reupload","reupload-shared","minoredit","editmyusercss","editmyuserjson","editmyuserjs","sendemail","applychangetags","changetags","editcontentmodel"]', '[]', '[]', '[]', '[]', NULL);
