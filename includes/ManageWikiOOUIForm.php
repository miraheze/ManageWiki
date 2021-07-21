<?php

class ManageWikiOOUIForm extends OOUIHTMLForm {
	protected $mSubSectionBeforeFields = false;

	public function wrapForm( $html ) {
		$html = Xml::tags( 'div', [ 'id' => 'baseform' ], $html );

		return parent::wrapForm( $html );
	}

	protected function wrapFieldSetSection( $legend, $section, $attributes, $isRoot ) {
		if ( $isRoot ) {
			$wrapper = new OOUI\PanelLayout( [
				'expanded' => false,
				'scrollable' => true,
				'framed' => true,
				'infusable' => false,
				'classes' => [ 'oo-ui-stackLayout oo-ui-indexLayout-stackLayout' ]
			] );

			$layout = new OOUI\PanelLayout( [
				'expanded' => false,
				'scrollable' => true,
				'framed' => true,
				'infusable' => false,
				'classes' => [ 'oo-ui-tabPanelLayout' ]
			] );

			$wrapper->appendContent( $layout );
		} else {
			$wrapper = $layout = new OOUI\PanelLayout( [
				'expanded' => false,
				'padded' => true,
				'framed' => true,
				'infusable' => false,
			] );
		}

		$layout->appendContent(
			new OOUI\FieldsetLayout( [
				'label' => $legend,
				'infusable' => false,
				'items' => [
					new OOUI\Widget( [
						'content' => new OOUI\HtmlSnippet( $section )
					] ),
				],
			] + $attributes )
		);

		return $wrapper;
	}

	public function getBody() {
		$fakeTabs = [];

		foreach( $this->getFormSections() as $i => $key ) {
			$fakeTabs[] = Html::rawElement(
				'div',
				[
					'class' =>
						'oo-ui-widget oo-ui-widget-enabled oo-ui-optionWidget oo-ui-tabOptionsWidget oo-ui-labelElement' .
						( $i === 0 ? 'oo-ui-optionWidget-selected' : '' )
				],
				Html::element(
					'a',
					[
						'class' => 'oo-ui-labelElement-label',
						'href' => '#mw-section-' . $key
					],
					$this->getLegend( $key )
				)
			);
		}

		$fakeTabsHtml = Html::rawElement(
			'div',
			[
				'class' => 'oo-ui-layout oo-ui-panelLayout oo-ui-indexLayout-tabPanel'
			],
			Html::rawElement(
				'div',
				[
					'class' => 'oo-ui-widget oo-ui-widget-enabled ooui-selectWidget oo-ui-selectWidget-depressed oo-ui-tabSelectWidget'
				],
				implode( $fakeTabs )
			)
		);

		return Html::rawElement(
			'div',
			[
				'class' => 'oo-ui-layout oo-ui-panelLayout oo-ui-paenlLayout-framed mw-baseform-faketabs'
			],
			Html::rawElement(
				'div',
				[
					'class' => 'oo-ui-layout oo-ui-menuLayout oo-ui-menuLayout-static oo-ui-menuLayout-top oo-ui-menuLayout-showMenu oo-ui-indexLayout'
				],
				Html::rawElement(
					'div',
					[
						'class' => 'oo-ui-menuLayout-menu'
					],
					$fakeTabsHtml
				) .
				Html::rawElement(
					'div',
					[
						'class' => 'oo-ui-menuLayout-content mw-htmlform-autoinfuse-lazy'
					],
					$this->displaySection( $this->mFieldTree, '', 'mw-section-' )
				)
			)
		);
	}

	public function getButtons( $display = true ) {
		if ( !$this->mShowSubmit ) {
			return;
		}

		$descriptor = [];

		$descriptor['reason'] = [
			'type' => 'text',
			'placeholder-message' => 'managewiki-label-reason',
			'required' => true
		];

		$field = $this->addFields( $descriptor )->getField( 'reason' );

		if ( !$display ) {
			return;
		}

		$html = $field->getInputOOUI( '' );

		$html .= parent::getButtons();

		$html = Xml::tags( 'div', [ 'class' => 'managewiki-submit-formfields' ], $html );

		return $html;
	}

	public function getFormSections() {
		return array_keys( array_filter( $this->mFieldTree, 'is_array' ) );
	}
}
