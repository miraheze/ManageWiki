<?php

namespace Miraheze\ManageWiki;

enum Module: string {
	case CORE = 'core';
	case EXTENSIONS = 'extensions';
	case NAMESPACES = 'namespaces';
	case PERMISSIONS = 'permissions';
	case SETTINGS = 'settings';
}
