<?php

namespace Miraheze\ManageWiki\FormFields;

use HTMLAutoCompleteSelectField;
use HTMLTextField;

class HTMLAutoCompleteSelectFieldWithOOUI extends HTMLAutoCompleteSelectField {
	public function getInputOOUI( $value ) {
		return HTMLTextField::getInputOOUI( $value );
	}
}
