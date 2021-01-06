<?php
class HTMLAutoCompleteSelectFieldWithOOUI extends HTMLAutoCompleteSelectField {
	public function getInputOOUI( $value ) {
		return HTMLTextField::getInputOOUI( $value );
     }
}
