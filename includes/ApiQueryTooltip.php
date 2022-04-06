<?php

/**
 * This is a simple top-level API module that provides some shortcut API queries that allow certain backend calls to
 * be performed by TippingOver in a single request, rather than the two that would be needed in some cases by using
 * the standard available queries.
 *
 * @author Eyes <eyes@aeongarden.com>
 * @copyright Copyright � 2015 Eyes
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class APIQueryTooltip extends APIBase {
  /**
   * Holds the parser options
   * @var ParserOptions
   */
  private $mParserOptions = null;

  /**
   * Holds the TippingOver configuration.
   * @var TippingOverConfiguration
   */
  private $mConf = null;

  /**
   * Initializes a ParserOptions instance.
   */
  private function initializeParserOptions() {
    if ( $this->mParserOptions === null ) {
      $this->mParserOptions = ParserOptions::newCanonical( $this->getContext() );
      $this->mParserOptions->setWrapOutputClass( null );
    }
  }
  
  /**
   * Gets the options requested in the options param into an array form, or errors out the request if the options
   * are invalid.
   * @return Array An array with the available option keywords as keys and true or false as values.
   */
  private function getOptions(): array {
    $optionKeywords = [ 'follow', 'exists', 'title', 'image', 'cat', 'text' ];
    if ( isset( $this->params['options'] ) ) {
	  $optionValues = array_intersect(
		explode(
		  substr( $this->params['options'], 0, 1 ) === "\x1f" ? "\x1f" : "|",
		  substr( $this->params['options'], 0, 1 ) === "\x1f"
		    ? substr( $this->params['options'], 1 )
			: $this->params['options'],
		  50
		),
		$optionKeywords
	  );
    }

	$options = [];
	foreach ( $optionKeywords as $keyword ) {
		$options[ $keyword ] = in_array( $keyword, $optionValues ?? [] );
	}

	if ( $options['cat'] && !isset( $this->params['target'] ) ) {
      $this->dieWithError( 'Category filtering cannot be done without the target parameter.', 'no_target_for_cat_filter' );
    }

    return $options;
  }
  
  /**
   * This function gets the tooltip title in string form. If it's available directly from the tooltip param supplied
   * in the request, it returns that. Otherwise, it will use MediaWiki:To-tooltip-page-title to try to transform
   * the title supplied in the target parameter, but only if the options specified actually require knowing the
   * tooltip page title; otherwise, it skips that step and returns null. It also returns null if the target title
   * cannot be resolved.
   * @param $options Array of options, indexed by name and true for requested options.
   * @return string The tooltip page title in string form, or null if not needed or on errors.
   */
  private function getTooltipTitleText( $options ) {
    if ( isset( $this->params['tooltip'] ) ) {
      return $this->params['tooltip'];
    }
    if ( $options['exists'] || $options['image'] || $options['text'] || $options['title'] ) {
      if ( isset( $this->params['target'] ) ) {
        $directTargetTitle = $targetTitle = Title::newFromText( $this->params['target'] );
        if ( $options['follow'] ) {
          $targetTitle = WikiTooltipsCore::followRedirect( $targetTitle );
        }
        if ( $targetTitle !== null ) {
          // For #ask and #show in SMW, the parse can't come through the message cache, so we do this reroute.
          // See https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/1181
          $tooltipTitleCode = wfMessage( 'to-tooltip-page-name' )->inContentLanguage()
                                                                 ->params( $targetTitle->getPrefixedText(),
                                                                           $targetTitle->getFragment(),
                                                                           $directTargetTitle->getPrefixedText(),
                                                                           $directTargetTitle->getFragment()
                                                                         )
                                                                 ->plain();
          $tooltipTitleText = $this->parse( $tooltipTitleCode, Title::newFromText( 'MediaWiki:To-tooltip-page-name' ) );
          return WikiTooltipsCore::stripOuterTags( $tooltipTitleText );
        } else {
          return null;
        }
      } else {
        return null;
      }
    }
  }
  
  /**
   * Runs the parser to get the fully parsed content for a tooltip to return.
   * @param Title $tooltipTitle The title of the tooltip to get content from.
   * @param Title $targetTitle The title of the page that owns this tooltip.
   * @return string The tooltip content parsed to HTML.
   */
  private function parseTooltip( $tooltipTitle, $targetTitle ) {
    return $this->parse( WikiTooltipsCore::getTooltipWikiText( $tooltipTitle ), $targetTitle );
  }
  
  /**
   * Parses the supplied wikitext.
   * @global Parser $wgParser The MediaWiki parser.
   * @param string $wikitext The text to parse.
   * @param Title $title The title to supply for the parser.
   * @return string The wikitext parsed to HTML.
   */
  private function parse( $wikitext, $title ) {
    global $wgParser;
    
    $output = $wgParser->parse( $wikitext, $title, $this->mParserOptions );
    return $output->getText();
  }
  
  /**
   * Processes the API requests and adds the appropriate results.
   * @param Array $options The array of options from the getOptions function.
   */
  private function addResults( $options ) {
    $result = $this->getResult();
    
    $targetTitle = Title::newFromText( $this->params['target'] );
    if ( $options['cat'] ) {
      if ( $this->mConf->lateCategoryFiltering() ) {
        if ( $targetTitle !== null ) {
          $category = WikiTooltipsCore::getFilterCategoryTitle( $this->mConf )->getText();
          $finder = new TippingOverCategoryFinder;
          $finder->seed( Array( $targetTitle->getArticleID() ), Array( $category ) );
          if ( count( $finder->run() ) === 1 ) {
            $result->addValue( null, 
                               'passesCategoryFilter', 
                               ($this->mConf->enablingCategory() !== null) ? 'true' : 'false'
                             );
          } else {
            $result->addValue( null, 
                               'passesCategoryFilter', 
                               ($this->mConf->enablingCategory() === null) ? 'true' : 'false'
                             );
          }
        } else {
          $result->addValue( null, 'passesCategoryFilter', 'false' );
        }
      } else {
        $result->addValue( null, 'passesCategoryFilter', 'true' );
      }
    }
    
    $tooltipTitleText = $this->getTooltipTitleText( $options );
    if ( $tooltipTitleText !== null && trim( $tooltipTitleText ) !== '' ) {
      $tooltipTitle = Title::newFromText( $tooltipTitleText );
      if ( $tooltipTitle !== null ) {
        if ( $options['title'] ) {
          $result->addValue( null, 'tooltipTitle', $tooltipTitle->getPrefixedText() );
        }
        if ( $options['image'] ) {
          $result->addValue( null, 'isImage', ( $tooltipTitle->getNamespace() === NS_FILE ) ? 'true' : 'false');
        }
        if ( !$options['exists'] || $tooltipTitle->exists() ) {
          if ( $options['exists'] ) {
            $result->addValue( null, 'exists', 'true' );
          }
          if ( $options['text'] ) {
            if ( $targetTitle !== null ) {
              $result->addValue( 'text', '*', $this->parseTooltip( $tooltipTitle, $targetTitle ) );
            } else {
              $result->addValue( 'text', '*', $this->parseTooltip( $tooltipTitle, $tooltipTitle ) );
            }
          }
        } else if ( $options['exists'] ) {
          $result->addValue( null, 'exists', 'false' );
        }
      }
    } else if ( $tooltipTitleText !== null ) {
      $result->addValue( null, 'tooltipTitle', '' );
    }
  }
  
  /**
   * Executes the API request for given tooltip content.
   */
  public function execute( ) {
    $this->mConf = new TippingOverConfiguration();
    $this->params = $this->extractRequestParams();
    $this->requireAtLeastOneParameter( $this->params, 'target', 'tooltip' );
    
    $this->initializeParserOptions();
    WikiTooltipsCore::flagTooltipAttachmentUnsafe();
    
    $this->addResults( $this->getOptions() );
    
    $this->getMain()->setCacheMaxAge( 300 );
    $this->getMain()->setCacheMode( 'public' );
  }
  
  /**
   * Returns the names and metadata of the allowed parameters.
   * @return Array Returns the names and metadata of the allowed parameters.
   */
  public function getAllowedParams( ) {
    return Array( 'target' => Array( ApiBase::PARAM_TYPE => 'string' ),
                  'tooltip' => Array( ApiBase::PARAM_TYPE => 'string' ),
                  'options' => Array( ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ),
                );
  }
  
  /**
   * Returns a version string. Not consistent with other API modules since I'm not yet using SVN.
   * @return string A version string.
   */
  public function getVersion( ) {
    return __CLASS__ . ': TippingOver 0.6.8';
  }
}
