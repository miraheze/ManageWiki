<?php

namespace Miraheze\ManageWiki\FormFields;

use MediaWiki\HTMLForm\Field\HTMLSelectField;
use MediaWiki\HTMLForm\HTMLFormField;
use MediaWiki\Xml\XmlSelect;
use OOUI\DropdownInputWidget;
use OOUI\Element;

/**
 * A typed-preserving select field that avoids coercing option values to strings.
 */
class HTMLTypedSelectField extends HTMLSelectField {

	/**
	 * Override validation to check raw option values strictly.
	 */
	public function validate( $value, $alldata ) {
		$p = parent::validate( $value, $alldata );
		if ( $p !== true ) {
			return $p;
		}

		$validOptions = array_values( HTMLFormField::flattenOptions( $this->getOptions() ) );
		if ( in_array( $value, $validOptions, true ) ) {
			return true;
		}

		return $this->msg( 'htmlform-select-badoption' );
	}

	/**
	 * Helper: get the string key that matches the given typed value
	 */
	private function getOptionKeyForValue( $value ): mixed {
		foreach ( $this->getOptions() as $label => $optionValue ) {
			if ( $optionValue === $value ) {
				return $optionValue;
			}
		}
		// fallback for non-matching values
		return $value;
	}

	/**
	 * Override to preserve type-safe value handling in HTML.
	 */
	public function getInputHTML( $value ) {
		$select = new XmlSelect( $this->mName, $this->mID, $this->getOptionKeyForValue( $value ) );

		if ( !empty( $this->mParams['disabled'] ) ) {
			$select->setAttribute( 'disabled', 'disabled' );
		}

		foreach ( $this->getAttributes( [ 'tabindex', 'size' ] ) as $name => $val ) {
			$select->setAttribute( $name, $val );
		}

		if ( $this->mClass !== '' ) {
			$select->setAttribute( 'class', $this->mClass );
		}

		$select->addOptions( $this->getOptions() );

		return $select->getHTML();
	}

	/**
	 * Override to preserve typed value selection in OOUI widget.
	 */
	public function getInputOOUI( $value ) {
		$attribs = Element::configFromHtmlAttributes(
			$this->getAttributes( [ 'tabindex' ] )
		);

		if ( $this->mClass !== '' ) {
			$attribs['classes'] = [ $this->mClass ];
		}

		return new DropdownInputWidget( [
			'name' => $this->mName,
			'id' => $this->mID,
			'options' => $this->getOptionsOOUI(),
			'value' => $this->getOptionKeyForValue( $value ),
			'disabled' => !empty( $this->mParams['disabled'] ),
		] + $attribs );
	}

	/**
	 * Codex fallback rendering with type-safe value handling.
	 */
	public function getInputCodex( $value, $hasErrors ) {
		$select = new XmlSelect( $this->mName, $this->mID, $this->getOptionKeyForValue( $value ) );

		if ( !empty( $this->mParams['disabled'] ) ) {
			$select->setAttribute( 'disabled', 'disabled' );
		}

		foreach ( $this->getAttributes( [ 'tabindex', 'size' ] ) as $name => $val ) {
			$select->setAttribute( $name, $val );
		}

		$class = 'cdx-select' . ( $this->mClass !== '' ? ' ' . $this->mClass : '' );
		$select->setAttribute( 'class', $class );

		$select->addOptions( $this->getOptions() );

		return $select->getHTML();
	}
}
