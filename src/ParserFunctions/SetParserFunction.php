<?php

namespace SMW\ParserFunctions;

use Parser;
use SMW\DataValueFactory;
use SMW\MediaWiki\Renderer\WikitextTemplateRenderer;
use SMW\MediaWiki\StripMarkerDecoder;
use SMW\MessageFormatter;
use SMW\ParserData;
use SMW\ParserParameterProcessor;

/**
 * Class that provides the {{#set}} parser function
 *
 * @see http://semantic-mediawiki.org/wiki/Help:Properties_and_types#Silent_annotations_using_.23set
 * @see http://www.semantic-mediawiki.org/wiki/Help:Setting_values
 *
 * @license GNU GPL v2+
 * @since   1.9
 *
 * @author Markus Krötzsch
 * @author Jeroen De Dauw
 * @author mwjames
 */
class SetParserFunction {

	/**
	 * @var ParserData
	 */
	private $parserData;

	/**
	 * @var MessageFormatter
	 */
	private $messageFormatter;

	/**
	 * @var WikitextTemplateRenderer
	 */
	private $templateRenderer;

	/**
	 * @var StripMarkerDecoder
	 */
	private $stripMarkerDecoder;

	/**
	 * @since 1.9
	 *
	 * @param ParserData $parserData
	 * @param MessageFormatter $messageFormatter
	 * @param WikitextTemplateRenderer $templateRenderer
	 */
	public function __construct( ParserData $parserData, MessageFormatter $messageFormatter, WikitextTemplateRenderer $templateRenderer ) {
		$this->parserData = $parserData;
		$this->messageFormatter = $messageFormatter;
		$this->templateRenderer = $templateRenderer;
	}

	/**
	 * @since 3.0
	 *
	 * @param StripMarkerDecoder $stripMarkerDecoder
	 */
	public function setStripMarkerDecoder( StripMarkerDecoder $stripMarkerDecoder ) {
		$this->stripMarkerDecoder = $stripMarkerDecoder;
	}

	/**
	 * @since 3.1
	 *
	 * @return SemanticData
	 */
	public function getSemanticData() {
		return $this->parserData->getSemanticData();
	}

	/**
	 * @since  1.9
	 *
	 * @param ParserParameterProcessor $parameters
	 *
	 * @return string|null
	 */
	public function parse( ParserParameterProcessor $parameters ) {

		$count = 0;
		$template = '';
		$subject = $this->parserData->getSemanticData()->getSubject();

		$parametersToArray = $parameters->toArray();

		if ( isset( $parametersToArray['template'] ) ) {
			$template = $parametersToArray['template'][0];
			unset( $parametersToArray['template'] );
		}

		$dataValueFactory = DataValueFactory::getInstance();

		// Set context
		$dataValueFactory->addCallable( 'semantic.data', [ $this, 'getSemanticData' ] );

		foreach ( $parametersToArray as $property => $values ) {

			$last = count( $values ) - 1; // -1 because the key starts with 0

			foreach ( $values as $key => $value ) {

				if ( $this->stripMarkerDecoder !== null ) {
					$value = $this->stripMarkerDecoder->decode( $value );
				}

				$dataValue = $dataValueFactory->newDataValueByText(
						$property,
						$value,
						false,
						$subject
					);

				if ( $this->parserData->canUse() ) {
					$this->parserData->addDataValue( $dataValue );
				}

				$this->messageFormatter->addFromArray( $dataValue->getErrors() );

				$this->addFieldsToTemplate(
					$template,
					$dataValue,
					$property,
					$value,
					$last == $key,
					$count
				);
			}
		}

		$this->parserData->copyToParserOutput();

		// Remove context
		$dataValueFactory->clearCallable( 'semantic.data' );

		$html = $this->templateRenderer->render() . $this->messageFormatter
			->addFromArray( $parameters->getErrors() )
			->getHtml();

		return [ $html, 'noparse' => $template === '', 'isHTML' => false ];
	}

	private function addFieldsToTemplate( $template, $dataValue, $property, $value, $isLastElement, &$count ) {

		if ( $template === '' || !$dataValue->isValid() ) {
			return '';
		}

		$this->templateRenderer->addField( 'property', $property );
		$this->templateRenderer->addField( 'value', $value );
		$this->templateRenderer->addField( 'last-element', $isLastElement );
		$this->templateRenderer->addField( '#', $count++ );
		$this->templateRenderer->packFieldsForTemplate( $template );
	}

}
