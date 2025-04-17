<?php

namespace Miraheze\ManageWiki;

use MediaWiki\HTMLForm\OOUIHTMLForm;
use MediaWiki\Xml\Xml;
use OOUI\ButtonInputWidget;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\IndexLayout;
use OOUI\PanelLayout;
use OOUI\TabPanelLayout;
use OOUI\Widget;

class ManageWikiOOUIForm extends OOUIHTMLForm {

	/** @var bool Override default value from HTMLForm */
	protected $mSubSectionBeforeFields = false;

	/**
	 * @param string $html
	 * @return string
	 */
	public function wrapForm( $html ) {
		$html = Xml::tags( 'div', [ 'id' => 'managewiki' ], $html );
		return parent::wrapForm( $html );
	}

	/**
	 * @param string $legend
	 * @param string $section
	 * @param array $attributes
	 * @param bool $isRoot
	 * @return PanelLayout
	 */
	protected function wrapFieldSetSection( $legend, $section, $attributes, $isRoot ) {
		$layout = parent::wrapFieldSetSection( $legend, $section, $attributes, $isRoot );

		$layout->addClasses( [ 'managewiki-fieldset-wrapper' ] );
		$layout->removeClasses( [ 'oo-ui-panelLayout-framed' ] );

		return $layout;
	}

	/**
	 * @return string
	 */
	public function getBody() {
		$tabPanels = [];
		foreach ( $this->mFieldTree as $key => $val ) {
			if ( !is_array( $val ) ) {
				wfDebug( __METHOD__ . " encountered a field not attached to a section: '$key'" );
				continue;
			}

			$label = $this->getLegend( $key );

			$content =
				$this->getHeaderHtml( $key ) .
				$this->displaySection(
					$val,
					'',
					"mw-section-$key-"
				) .
				$this->getFooterHtml( $key );

			$tabPanels[] = new TabPanelLayout( "mw-section-$key", [
				'classes' => [ 'mw-htmlform-autoinfuse-lazy' ],
				'label' => $label,
				'content' => new FieldsetLayout( [
					'classes' => [ 'managewiki-section-fieldset' ],
					'id' => "mw-section-$key",
					'label' => $label,
					'items' => [
						new Widget( [
							'content' => new HtmlSnippet( $content ),
						] ),
					],
				] ),
				'expanded' => false,
				'framed' => true,
			] );
		}

		$indexLayout = new IndexLayout( [
			'infusable' => true,
			'expanded' => false,
			'autoFocus' => false,
			'classes' => [ 'managewiki-tabs' ],
		] );

		$indexLayout->addTabPanels( $tabPanels );

		$header = $this->formatFormHeader();

		$form = new PanelLayout( [
			'framed' => true,
			'expanded' => false,
			'classes' => [ 'managewiki-tabs-wrapper' ],
			'content' => $indexLayout,
		] );

		return $header . $form;
	}

	/**
	 * @return string
	 */
	public function getButtons() {
		if ( !$this->mShowSubmit ) {
			return '';
		}

		$descriptor = [];
		$descriptor['reason'] = [
			'type' => 'text',
			'placeholder-message' => 'managewiki-placeholder-reason',
			'id' => 'managewiki-submit-reason',
			'required' => true,
		];

		$field = $this->hasField( 'reason' ) ?
			$this->getField( 'reason' ) :
			$this->addFields( $descriptor )->getField( 'reason' );

		$html = $field->getInputOOUI( '' );

		$html .= parent::getButtons();

		$html .= new ButtonInputWidget( [
			'label' => $this->msg( 'managewiki-review' )->text(),
			'id' => 'managewiki-review',
		] );

		$html = Xml::tags( 'div', [ 'class' => 'managewiki-submit-formfields' ], $html );

		return $html;
	}
}
