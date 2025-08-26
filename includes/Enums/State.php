<?php

namespace Miraheze\ManageWiki\Enums;

enum State: string {
	case Closed = 'closed';
	case Deleted = 'deleted';
	case Experimental = 'experimental';
	case Inactive = 'inactive';
	case Locked = 'locked';
	case Private = 'private';
}
