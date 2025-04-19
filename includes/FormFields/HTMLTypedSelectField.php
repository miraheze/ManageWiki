<?php

namespace Miraheze\ManageWiki\FormFields;

use MediaWiki\HTMLForm\Field\HTMLSelectField;
use MediaWiki\Xml\XmlSelect;
use OOUI\DropdownInputWidget;
use OOUI\Element;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\ConfigNames;

/**
 * Select field that preserves original value types.
 */
class HTMLTypedSelectField extends HTMLSelectField {
	/**
	 * Basically don't do any validation. If it's a number that's fine. Also,
	 * add it to the list if it's not there already
	 *
	 * @param string $value
	 * @param array $alldata
	 * @return bool
	 */
	public function validate( $value, $alldata ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$settings = $config->get( ConfigNames::Settings );
		
		//if ( $value === '' ) {
		//	return true;
		//}
		foreach ( $this->mParams['options'] as $label => $val ) {
			$this->mParams['options'] = $settings["set-{$this->getName()}"]['options'];
		}
		foreach ( $this->mParams['options'] as $label => $val ) {
			var_dump( "$label: " . gettype( $val ) );
		}

		return true;
	}
}
