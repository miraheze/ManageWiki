<?php

namespace Miraheze\ManageWiki\FormFields;

use MediaWiki\HTMLForm\Field\HTMLSelectField;
use MediaWiki\Xml\XmlSelect;
use OOUI\DropdownInputWidget;
use OOUI\Element;

/**
 * Select field that preserves original value types.
 */
class HTMLTypedSelectField extends HTMLSelectField {

	/** @var array<string, mixed> Map from encoded value to real typed value */
	private array $typedMap = [];

	public function __construct( $params ) {
		parent::__construct( $params );
		$this->buildTypedMap();
	}

	private function buildTypedMap(): void {
		$this->typedMap = [];

		foreach ( $this->getOptions() as $label => $realValue ) {
			$this->typedMap[$this->encodeValue( $realValue )] = $realValue;
		}
	}

	private function encodeValue( mixed $val ): string {
		// Simple, unique string representation for HTML keys
		return sha1( serialize( $val ) );
	}

	private function decodeValue( string $encoded ): mixed {
		return $this->typedMap[$encoded] ?? null;
	}

	public function loadDataFromRequest( $request ) {
		$encoded = parent::loadDataFromRequest( $request );
		return $this->decodeValue( $encoded );
	}

	public function validate( $value, $alldata ) {
		// validate() receives decoded values already
		foreach ( $this->typedMap as $encoded => $typed ) {
			if ( $typed === $value || (string)$typed === (string)$value ) {
				return true;
			}
		}
		return $this->msg( 'htmlform-select-badoption' );
	}

	public function getInputHTML( $value ) {
		$select = new XmlSelect( $this->mName, $this->mID, $this->encodeValue( $value ) );

		if ( !empty( $this->mParams['disabled'] ) ) {
			$select->setAttribute( 'disabled', 'disabled' );
		}

		foreach ( $this->getAttributes( [ 'tabindex', 'size' ] ) as $name => $val ) {
			$select->setAttribute( $name, $val );
		}

		if ( $this->mClass !== '' ) {
			$select->setAttribute( 'class', $this->mClass );
		}

		$options = [];
		foreach ( $this->getOptions() as $label => $realValue ) {
			$options[$label] = $this->encodeValue( $realValue );
		}
		$select->addOptions( $options );

		return $select->getHTML();
	}

	public function getInputOOUI( $value ) {
		$attribs = Element::configFromHtmlAttributes( $this->getAttributes( [ 'tabindex' ] ) );

		if ( $this->mClass !== '' ) {
			$attribs['classes'] = [ $this->mClass ];
		}

		$options = [];
		foreach ( $this->getOptions() as $label => $realValue ) {
			$options[] = [
				'label' => $label,
				'data' => $this->encodeValue( $realValue )
			];
		}

		return new DropdownInputWidget( [
			'name' => $this->mName,
			'id' => $this->mID,
			'options' => $options,
			'value' => $this->encodeValue( $value ),
			'disabled' => !empty( $this->mParams['disabled'] ),
		] + $attribs );
	}
}
