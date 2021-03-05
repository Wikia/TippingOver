<?php

/**
 * This static class handles most basic tooltip functions that occur during a page load through index.php.
 *
 * @author Eyes <eyes@aeongarden.com>
 * @copyright Copyright � 2015 Eyes
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class WikiTooltips {
  /**
   * The element class ID prefix for a tooltip div.
   */
  const TOOLTIP_CLASS_ID = 'to-tooltip-';

  /**
   * The element class ID prefix for a preloading div.
   */
  const PRELOAD_CLASS_ID = 'to-preload-';

  /**
   * Holds the TippingOver configuration.
   * @var TippingOverConfiguration
   */
  private static $mConf = null;

  /**
   * Holds a parser instance
   * @var Parser
   */
  private static $mParser = null;

  /**
   * Holds the parser options
   * @var ParserOptions
   */
  private static $mParserOptions = null;

  /**
   * A sorted array of category members, used for the TO_PREQUERY category filter mode.
   * @var Array
   */
  private static $mCategoryFilterLookup = Array();

  /**
   * An array keyed by ids of pages previously checked against the category filter, containing true for those that
   * passed and false for those that didn't. Used for the TO_DO_EARLY category filter mode.
   * @var Array
   */
  private static $mCategoryFilterCache = Array();

  /**
   * The next index to use for image link tooltip attachment.
   * @var int
   */
  private static $mImageLinkNextIndex = 0;

  /**
   * An array containing the attributes to add to image links that should have tooltips.
   * @var Array
   */
  private static $mImageLinkTargets = Array();

  /**
   * Indicates if we've initialized all variables needed to process links. This is a kludge as some of these need
   * access to the parser. Eventually, a better hook with parser access should be identified to avoid all the
   * redundant checks of this state, but due to time issues, this is the solution for now.
   * @var bool
   */
  private static $mIsFullyInitialized = false;

  /**
   * Indicates if we're in the parser because we're parsing the content of a tooltip. There is no value in adding
   * tooltips to content inside a tooltip, and it can even lead to potential fatal errors by calling the parser
   * recursively, so when this is true, attaching tooltips to content is disabled.
   * @var bool
   */
  private static $mIsParsingTooltipContent = false;

  /**
   * The parsed HTML for the loading tooltip, or null if this is disabled somehow.
   * @var String
   */
  private static $mLoadingTooltipHtml;

  /**
   * True if tooltips are enabled in this namespace.
   * @var String
   */
  private static $mTooltipsEnabledHere = false;

  /**
   * Indicates if the loading tooltip should be preloaded.
   * @var bool
   */
  private static $mPreloadLoadingTooltip;

  /**
   * The parsed HTML for the missing page tooltip, or null if this is disabled somehow.
   * @var String
   */
  private static $mMissingPageTooltipHtml;

  /**
   * Indicates if the missing page tooltip should be preloaded.
   * @var bool
   */
  private static $mPreloadMissingPageTooltip;

  /**
   * The parsed HTML for the empty page name tooltip, or null if this is disabled somehow.
   * @var String
   */
  private static $mEmptyPageNameTooltipHtml;

  /**
   * Indicates if the empty page name tooltip should be preloaded.
   * @var bool
   */
  private static $mPreloadEmptyPageNameTooltip;

  /**
   * Indicates if a two-request process should be used instead of one. This happens in certain configurations to avoid
   * showing a loading tooltip when there is a strong possibility that there is no tooltip to show.
   * @var bool
   */
  private static $mUseTwoRequestProcess;

  /**
   * Run in the BeforeInitialize hook, this attachs functions to various hooks for tooltip processing and registers
   * the JavaScript module if the current page is in a namespace where tooltips are enabled.
   * @global Array $wgHooks The global where hooks are registered in MediaWiki.
   * @param Title $title The title being requested.
   * @param Article $article The article object. Ignored.
   * @param OutputPage $output The output page.
   * @param User $user The user object. Ignored.
   * @param WebRequest $request The request object. Ignored.
   * @param MediaWiki $mediawiki The MediaWiki object. Ignored.
   */
  static public function initializeHooksAndModule( &$title, &$article, &$output, &$user, $request, $mediawiki ) {
    global $wgHooks;

    self::$mConf = new TippingOverConfiguration();

    if ( self::$mConf->enabled() && self::$mConf->enableInNamespace( $title->getNamespace() ) ) {
      self::$mTooltipsEnabledHere = true;
      $wgHooks['MakeGlobalVariablesScript'][] = Array( 'WikiTooltips::registerParsedConfigVarsForScriptExport' );
      $wgHooks['LinkEnd'][] = Array( 'WikiTooltips::linkTooltipRender' );
      if ( self::$mConf->enableOnImageLinks() ) {
        $wgHooks['ImageBeforeProduceHTML'][] = Array( 'WikiTooltips::imageLinkTooltipStartRender' );
        $wgHooks['ThumbnailBeforeProduceHTML'][] = Array( 'WikiTooltips::imageLinkTooltipFinishRender' );
      }

      $output->addModules( 'ext.TippingOver.wikiTooltips' );
    }
  }

  /**
   * Calls all parser function registrations functions.
   * @param Parser $parser The parser object being initialized.
   * @return bool true to indicate no problems.
   */
  static public function initializeParserHooks( &$parser ) {
    $parser->setFunctionHook( 'tipfor', 'WikiTooltipsCore::tipforRender', SFH_OBJECT_ARGS );
    return true;
  }

  /**
   * Run in the MakeGlobalVariablesScript hook, this exports values needed by toWikiTooltips.js to perform the
   * client-side tooltip functions.
   * @param Array $vars The variables to export.
   * @param OutputPage $out An OutputPage instance. Not used.
   */
  public static function registerParsedConfigVarsForScriptExport( &$vars, $out ) {
    $vars['wgTippingOver']['doLateTargetRedirectFollow'] = self::$mConf->lateTargetRedirectFollow();
    $vars['wgTippingOver']['doLatePageTitleParse'] = self::$mConf->latePageTitleParse();
    $vars['wgTippingOver']['doLateExistsCheck'] = self::$mConf->lateExistsCheck();
    $vars['wgTippingOver']['doLateCategoryFiltering'] = self::$mConf->lateCategoryFiltering();
    $vars['wgTippingOver']['loadingTooltip'] = self::$mLoadingTooltipHtml;
    $vars['wgTippingOver']['missingPageTooltip'] = self::$mMissingPageTooltipHtml;
    $vars['wgTippingOver']['emptyPageNameTooltip'] = self::$mEmptyPageNameTooltipHtml;
    $vars['wgTippingOver']['preloadLoadingTooltip'] = self::$mPreloadLoadingTooltip;
    $vars['wgTippingOver']['preloadMissingPageTooltip'] = self::$mPreloadMissingPageTooltip;
    $vars['wgTippingOver']['preloadEmptyPageNameTooltip'] = self::$mPreloadEmptyPageNameTooltip;
    $vars['wgTippingOver']['useTwoRequestProcess'] = self::$mUseTwoRequestProcess;
  }

  /**
   * Recurvise function that assigns all page ids in the given category and its subcategories to the $mCategoryLookup
   * array, allowing for category filtering to be done without querying the database later.
   * @param string $title The appropriate value of page_name or cl_to for the category in the database.
   */
  private static function populateLookupFromCategory( $title ) {
    $dbr = wfGetDB( DB_SLAVE );

    $result = $dbr->select( Array( 'page', 'categorylinks' ),
                            Array( 'page_id', 'page_namespace', 'page_title' ),
                            Array( 'cl_to' => $title, 'cl_from = page_id' ),
                            __METHOD__,
                            Array( 'ORDER BY' => 'page_id' )
                          );

    foreach ( $result as $row ) {
      if ( $row->page_id !== null &&
           $row->page_id !== 0 &&
           $row->page_namespace !== null &&
           array_key_exists( intval( $row->page_namespace ), self::$mConf->namespacesWithTooltips() ) &&
           self::$mConf->namespacesWithTooltips()[intval( $row->page_namespace )]
         ) {
        self::$mCategoryFilterLookup[] = intval( $row->page_id );
      }
      if ( intval( $row->page_namespace ) === NS_CATEGORY ) {
        self::populateLookupFromCategory( $row->page_title );
      }
    }
  }

  /**
   * Initiates the prefetch of page ids for category filtering should the extension configuration have it properly
   * enabled in the correct mode.
   */
  private static function populateLookup() {
    $title = WikiTooltipsCore::getFilterCategoryTitle( self::$mConf );
    if ( $title !== null ) {
      self::populateLookupFromCategory( $title->getDBKey() );
      sort( self::$mCategoryFilterLookup, SORT_NUMERIC );
    }
  }

  /**
   * Performs a binary search of the category filter lookup array for the given item.
   * @param int $id The page id to search for.
   * @return bool True if the id was found, false if not.
   */
  private static function isInCategoryLookup( $id ) {
    $id = intval( $id );
    $min = 0;
    $max = count( self::$mCategoryFilterLookup ) - 1;

    while ( $min <= $max ) {
      $mid = intval( ( $min + $max ) / 2 );
      $midId = self::$mCategoryFilterLookup[$mid];

      if ( $midId === $id ) {
        return true;
      } else if ( $midId > $id ) {
        $max = $mid - 1;
      } else {
        $min = $mid + 1;
      }
    }

    return false;
  }

  /**
   * Returns true if the given namespace index and title pair passes the category filtering enabled by the current
   * configuration, or always passes true if no such filtering is enabled or if late checks are enabled.
   * @param Title $title The title to search for.
   * @return bool False if it fails the filter and should have its tooltip disabled, true to continue processing.
   */
  private static function passesCategoryFilter( $title ) {
    if ( self::$mConf->earlyCategoryFiltering() ) {
      if ( self::$mConf->preprocessCategoryFilter() ) {
        if ( self::isInCategoryLookup( $title->getArticleID() ) ) {
          return ( self::$mConf->enablingCategory() !== null );
        } else {
          return ( self::$mConf->enablingCategory() === null );
        }
      } else {
        $id = $title->getArticleID();
        if ( array_key_exists( $id, self::$mCategoryFilterCache ) ) {
          return self::$mCategoryFilterCache[$id];
        } else {
          $category = WikiTooltipsCore::getFilterCategoryTitle( self::$mConf )->getText();
          $finder = new CategoryFinder;
          $finder->seed( Array( $id ), Array( $category ) );
          if ( count( $finder->run() ) === 1 ) {
            return ( self::$mCategoryFilterCache[$id] = ( self::$mConf->enablingCategory() !== null ) );
          } else {
            return ( self::$mCategoryFilterCache[$id] = ( self::$mConf->enablingCategory() === null ) );
          }
        }
      }
    } else {
      return true;
    }
  }

  /**
   * Converts all nonnumeric, nonalphabetic character or any character (actually, byte) outside the ASCII set to a hex
   * representation beginning with an underscore and ending with a dash. Used primarily for generating unique element
   * ids from page titles. This must produce results consistent with toWikiTooltips.encodeAllSpecials in
   * toWikiTooltips.js.
   * @param string $unencoded The unencoded string.
   * @return string The encoded string.
   */
  private static function encodeAllSpecial( $unencoded ) {
    $encoded = "";
    $c = null;
    $safeChars = "/[0-9A-Za-z]/";
    for( $i = 0; $i < strlen( $unencoded ); $i++ ) {
      $c = $unencoded[$i];
      if ( preg_match( $safeChars, $c ) === 1 ) {
        $encoded = $encoded . $c;
      } else if ( $c === ' ' ) {
        $encoded = $encoded . '_';
      } else {
        $encoded = $encoded . '_' . dechex( ord($c) ) . '-';
      }
    }
    return $encoded;
  }

  /**
   * Gets a Parser and ParserOptions instance by cloning the main parser, using the same approach as MediaWiki's
   * internal messages API.
   * @global Parser $wgParser The main parser object.
   * @global Array $wgParserConf The main parser configuration.
   */
  private static function initializeParser() {
    global $wgParser, $wgParserConf;

    if ( self::$mParser === null && isset( $wgParser ) ) {
      $wgParser->firstCallInit();
      $class = $wgParserConf['class'];
      if ( $class == 'ParserDiffTest' ) {
        self::$mParser = new $class( $wgParserConf );
      } else {
        self::$mParser = clone $wgParser;
      }
    }

    if ( self::$mParserOptions === null ) {
      self::$mParserOptions = new ParserOptions;
      self::$mParserOptions->setEditSection( false );
    }
  }

  /**
   * Disables tooltip attachment until afterTooltipContentParse is called. Intended to provide a way to disable
   * tooltip attachment when parsing the actual content of a tooltip.
   */
  public static function beforeTooltipContentParse( ) {
    self::$mIsParsingTooltipContent = true;
  }

  /**
   * Reenables tooltip attachment after beforeTooltipContentParse is called.
   */
  public static function afterTooltipContentParse( ) {
    self::$mIsParsingTooltipContent = false;
  }

  /**
   * Parses the content of the given page name to HTML, or returns null if the given page name is null, does not exist,
   * is in some way invalid, or parses just to whitespace.
   * @param string $titleText The title in string form.
   * @param string $html Returns the page content parsed to HTML or null.
   * @param bool $doPreload Returns true if the tooltip show use preload logic or false otherwise.
   */
  private static function parseTooltip( $titleText, &$html, &$doPreload ) {
    if ( self::$mParser !== null && $titleText !== null && trim( $titleText ) !== '' ) {
      $title = Title::newFromText( $titleText );
      WikiTooltipsCore::flagTooltipAttachmentUnsafe(); // tooltip attaching risks fatal redundant parse here, so disable
      $out = self::$mParser->parse( WikiTooltipsCore::getTooltipWikiText( $title ),
                                    $title,
                                    self::$mParserOptions,
                                    true
                                  );
      WikiTooltipsCore::flagTooltipAttachmentSafe(); // safe again to attach tooltips
      $html = $out->getText();
      $doPreload = ( $title->getNamespace() === NS_FILE );
      if ( trim( $html ) === '' ) {
        $html = null;
        $doPreload = false;
      }
    } else {
      $html =  null;
      $doPreload = false;
    }
  }

  /**
   * Performs potentially expensive initialization tasks which require parser or database access.
   */
  private static function performDelayedInitialization() {
    self::initializeParser();
    self::parseTooltip( self::$mConf->loadingTooltip(),
                        self::$mLoadingTooltipHtml,
                        self::$mPreloadLoadingTooltip
                      );
    self::parseTooltip( self::$mConf->missingPageTooltip(),
                        self::$mMissingPageTooltipHtml,
                        self::$mPreloadMissingPageTooltip
                      );
    if ( !self::$mConf->assumeNonemptyPageTitle() ) {
      self::parseTooltip( self::$mConf->emptyPageNameTooltip(),
                          self::$mEmptyPageNameTooltipHtml,
                          self::$mPreloadEmptyPageNameTooltip
                        );
    } else {
      self::$mEmptyPageNameTooltipHtml = null;
      self::$mPreloadEmptyPageNameTooltip = false;
    }

    self::$mUseTwoRequestProcess = false;
    if ( self::$mLoadingTooltipHtml !== null ) {
      if ( self::$mConf->latePageTitleParse() &&
           !self::$mConf->lateExistsCheck() &&
           self::$mMissingPageTooltipHtml === null
         ) {
        self::$mUseTwoRequestProcess = true;
      } else if ( self::$mConf->latePageTitleParse() &&
                  !self::$mConf->assumeNonemptyPageTitle() &&
                  self::$mEmptyPageNameTooltipHtml === null
                ) {
        self::$mUseTwoRequestProcess = true;
      } else if ( self::$mConf->lateExistsCheck() && self::$mMissingPageTooltipHtml === null ) {
        self::$mUseTwoRequestProcess = true;
      } else if ( self::$mConf->lateCategoryFiltering() ) {
        self::$mUseTwoRequestProcess = true;
      }
    }

    if ( self::$mUseTwoRequestProcess && !self::$mConf->allowTwoRequestProcess() ) {
      self::$mUseTwoRequestProcess = false;
      self::$mLoadingTooltipHtml = null;
      self::$mPreloadLoadingTooltip = false;
    }

    if ( self::$mConf->earlyCategoryFiltering() && self::$mConf->preprocessCategoryFilter() ) {
      self::populateLookup();
    }

    self::$mIsFullyInitialized = true;
  }

  /**
   * This function performs the server-side checks enabled by the current configuration to determine if a given link
   * does not have a tooltip and returns null if not. Otherwise, it returns an array of information needed for both
   * displaying the tooltip client-side and running any further checks there to see if a tooltip is available for the
   * link.
   * @param Title $target The target page of the link or tooltip span.
   * @return Array null if the link should not have a tooltip, or an array of information if it might get one.
   */
  private static function runEarlyTooltipChecks( $target ) {
    if ( self::$mConf->namespaceWithTooltips( $target->getNamespace() ) ) {
      $setupInfo = Array( 'canLateFollow' => true );
      $setupInfo['directTargetTitle'] = $directTarget = $target;
      if ( self::$mConf->earlyTargetRedirectFollow() ) {
        $target = WikiTooltipsCore::followRedirect( $target );
        $setupInfo['canLateFollow'] = false;
      }
      $setupInfo['targetTitle'] = $target;

      if ( self::passesCategoryFilter( $target ) ) {
        $setupInfo['canLateFollow'] = $setupInfo['canLateFollow'] && !( self::$mConf->earlyCategoryFiltering() );
        $tooltipTitle = null;

        if ( self::$mConf->earlyPageTitleParse() ) {
          $setupInfo['canLateFollow'] = false;
          // For #ask and #show in SMW, the parse can't come through the message cache, so we do this reroute
          // See https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/1181
          $tooltipTitleWikitext = wfMessage( 'to-tooltip-page-name' )->inContentLanguage()
                                                                     ->params( $target->getPrefixedText(),
                                                                               $target->getFragment(),
                                                                               $directTarget->getPrefixedText(),
                                                                               $directTarget->getFragment()
                                                                             )
                                                                     ->plain();
          $messageTitle = Title::newFromText( 'MediaWiki:To-tooltip-page-name' );
          $tooltipTitleParse = self::$mParser->parse( $tooltipTitleWikitext,
                                                      $messageTitle,
                                                      self::$mParserOptions,
                                                      true
                                                    );
          $tooltipTitleText = WikiTooltipsCore::stripOuterTags( $tooltipTitleParse->getText() );
          if ( trim( $tooltipTitleText ) !== "" ) {
            $tooltipTitle = Title::newFromText( $tooltipTitleText );
            if ( $tooltipTitle !== null ) {
              if ( self::$mConf->earlyExistsCheck() ) {
                $setupInfo['canLateFollow'] = false;
                if ( $tooltipTitle->exists() ) {
                  $setupInfo['missingPage'] = false;
                  $setupInfo['isImage'] = ( $tooltipTitle->getNamespace() === NS_FILE );
                } else if ( self::$mMissingPageTooltipHtml !== null ) {
                  $setupInfo['missingPage'] = true;
                  $setupInfo['isImage'] = self::$mPreloadMissingPageTooltip;
                } else {
                  return null; // the tooltip page is missing, and there's no tooltip for this situation
                }
              } else {
                $setupInfo['isImage'] = ( $tooltipTitle->getNamespace() === NS_FILE );
              }
              $setupInfo['tooltipTitle'] = $tooltipTitle->getPrefixedText();
              $setupInfo['emptyPageName'] = false;
            } else {
              return null; // not an empty page name, but Title couldn't process it, so no tooltip.
            }
          } else if ( self::$mEmptyPageNameTooltipHtml !== null ) {
            $setupInfo['emptyPageName'] = true;
            $setupInfo['isImage'] = self::$mPreloadEmptyPageNameTooltip;
          } else {
            return null; // the tooltip title parsed to an empty value, and we have no tooltip for it
          }
        }

        return $setupInfo;
      } else {
        return null; // didn't pass category filter or namespace check, so no tooltip.
      }
    }
  }

  /**
   * Given an array of setup information from runEarlyTooltipChecks(), this adds appropriate HTML attibures as
   * name/value pairs to the second given array, which should either be an empty array or an array of existing HTML
   * attributes that should be added to the final tag.
   * @param Array $setupInfo Setup information from runEarlyTooltipChecks().
   * @param Array $attribs An array of HTML attributes with name/value pairs to add tooltip-related attributes to.
   */
  private static function setUpAttribs( $setupInfo, &$attribs ) {
    $tooltipId = self::encodeAllSpecial( $setupInfo['targetTitle']->getPrefixedText() );
    if ( array_key_exists( 'class', $attribs ) ) {
      $attribs['class'] .= ' to_hasTooltip';
    } else {
      $attribs['class'] = 'to_hasTooltip';
    }
    $titles = $setupInfo['targetTitle']->getFullText() . "|";
    $attribs['data-to-id'] = $tooltipId;
    if ( !$setupInfo['directTargetTitle']->equals( $setupInfo['targetTitle'] ) ) {
      $titles .= $setupInfo['directTargetTitle']->getFullText();
    }
    $titles .= "|";
    $flags = $setupInfo['canLateFollow'] ? 'F' : 'f';
    $tooltipTitle = isset( $setupInfo['tooltipTitle'] ) ? $setupInfo['tooltipTitle'] : null;
    if ( $tooltipTitle ) {
      $titles .= $setupInfo['tooltipTitle'];
    }
    if ( array_key_exists( 'isImage', $setupInfo ) ) {
      $flags .= $setupInfo['isImage'] ? 'I' : 'i';
    }
    if ( array_key_exists( 'emptyPageName', $setupInfo ) ) {
      $flags .= $setupInfo['emptyPageName'] ? 'E' : 'e';
    }
    if ( array_key_exists( 'missingPage', $setupInfo ) ) {
      $flags .= $setupInfo['missingPage'] ? 'M' : 'm';
    }
    if ( $titles !== "" ) {
      $attribs['data-to-titles'] = $titles;
    }
    if ( $flags !== "" ) {
      $attribs['data-to-flags'] = $flags;
    }
  }

  /**
   * This function performs the server-side checks enabled by the current configuration to determine if a given target
   * does not have a tooltip and returns false if not. Otherwise, this adds appropriate HTML attributes as name/value
   * pairs to the second given array, which should either be an empty array or an array of existing HTML attributes that
   * should be added to the final tag, and also returns true.
   * @param Title $target The target page of the link or tooltip span.
   * @param Array $attribs An array of HTML attributes with name/value pairs to add tooltip-related attributes to.
   * @return bool True if there is a tooltip and tooltip attribtues have been added.
   */
  private static function maybeAttachTooltip( $target, &$attribs ) {
    if ( $target !== null ) {
      $setupInfo = self::runEarlyTooltipChecks( $target );

      if ( $setupInfo !== null ) {
        self::setUpAttribs( $setupInfo, $attribs );
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  /**
   * Attached to the LinkEnd hook of the MediaWiki linker, this function will add appropriate data elements and other
   * attributes to any link that should have or might have a tooltip, preparing it for the client-side script to
   * finish the job.
   * @param mixed $dummy Placeholder for removed skin parameter. Not used.
   * @param Title $target The title of the target page.
   * @param Array $options An array of link options to get or set.
   * @param string $html The inner content of the <a> tag.
   * @param Array $attribs The attributes of the <a> tag and their values.
   * @param string $ret Alternate HTML to return rather than the <a> tag the linker would generate.
   */
  public static function linkTooltipRender( $dummy, $target, $options, &$html, &$attribs, &$ret ) {
    if ( WikiTooltipsCore::isTooltipAttachmentSafe() ) {
      if ( !self::$mIsFullyInitialized ) {
        self::performDelayedInitialization();
      }

      self::maybeAttachTooltip( $target, $attribs );

      return true;
    }
  }

  /**
   * Attached to ImageBeforeProduceHTML, this collects the target of an image link and uses an ugly hack to mark the
   * image link for processing in a later hook where it's possibly to attach attributes to the link.
   * @param Skin $skin The current skin. Ignored.
   * @param Title $title Title of the image.
   * @param File $file File of the image. Ignored.
   * @param Array $frameParams Various parameters for the image and link.
   * @param Array $handlerParams Various parameters for the image and link. Ignored.
   * @param String $time The timestamp of the image or false for the current image. Ignored.
   * @param String $res HTML override. Not used.
   * @return boolean false to use the HTML override. true returned instead to continue normal processing.
   */
  public static function imageLinkTooltipStartRender( &$skin,
                                                      &$title,
                                                      &$file,
                                                      &$frameParams,
                                                      &$handlerParams,
                                                      &$time,
                                                      &$res
                                                   ) {
    if ( WikiTooltipsCore::isTooltipAttachmentSafe() ) {
      if ( !self::$mIsFullyInitialized ) {
        self::performDelayedInitialization();
      }

      if ( isset( $frameParams['link-url'] ) && $frameParams['link-url'] !== '' ) {
        $target = null; // Target is external, so no tooltip to worry about.
      } elseif ( isset( $frameParams['link-title'] ) && $frameParams['link-title'] !== '' ) {
        $target = $frameParams['link-title'];
      } elseif ( !empty( $frameParams['no-link'] ) ) {
        $target = null; // This image won't be linked to anything, so no tooltip.
      } else {
        $target = $title; // Image should be linking to its own page.
      }

      if ( $target !== null ) {
        // This is a rather hideous hack to avoid having to scrape a link url to get the target title when it's
        // actually possible to attach attributes to the link. Instead, while the target title is available as an actual
        // Title, it is stored along with the results of the early checks by index. A numbered class is then added to
        // allow the link to be identified in another hook, allowing tooltip attachment to be finished up then.
        self::$mImageLinkTargets[self::$mImageLinkNextIndex] = $target;
        if ( array_key_exists( 'class', $frameParams ) ) {
          $frameParams['class'] .= ' ';
        } else {
          $frameParams['class'] = '';
        }
        $frameParams['class'] .= 'to_addTooltip_' . strval( self::$mImageLinkNextIndex );
        ++self::$mImageLinkNextIndex;
      }
    }

    return true;
  }

  /**
   * Attached to the ThumbnailBeforeProduceHTML hook, this function, with the aid of the hack in
   * imageLinkTooltipStartRender will add appropriate data elements and other attributes to any image link that should
   * have or might have a tooltip, preparing it for the client-side script to finish the job.
   * @param ThumbnailImage $thumbnail The ThumbnailImage object for the image. Ignored.
   * @param Array $attribs The attributes for the img tag.
   * @param Array $linkAttribs The attributes for the a tag.
   * @return boolean
   */
  public static function imageLinkTooltipFinishRender( $thumbnail, &$attribs, &$linkAttribs ) {
    if ( array_key_exists( 'class', $attribs ) ) {
      $outClasses = Array();
      $inClasses = explode( ' ', $attribs['class'] );
      $index = null;
      foreach ( $inClasses as $class ) {
        if ( strpos( $class, 'to_addTooltip_' ) === 0 ) {
          $index = substr( $class, 14 );
          if ( ctype_digit( $index ) ) {
            $index = intval( $index );
          } else {
            $index = null;
            $outClasses[] = $class;
          }
        } else {
          $outClasses[] = $class;
        }
      }
      if ( $index !== null && array_key_exists( $index, self::$mImageLinkTargets ) ) {
        $target = self::$mImageLinkTargets[$index];
        self::maybeAttachTooltip( $target, $linkAttribs );
      }
      if ( count( $outClasses ) > 0 ) {
        $attribs['class'] = implode( ' ', $outClasses );
      } else {
        unset( $attribs['class'] );
      }
    }

    return true;
  }

  /**
   * The function handles the #tipfor function when tooltip attachment is flagged as safe. If tooltips are enabled in
   * the current namespace this function will output text into a span and add appropriate data elements and other
   * attributes to that span if it should have or might have a tooltip, preparing it for the client-side script to
   * finish the job. If tooltips are not enabled in the current namespace, it will simply output the appropriate text.
   * @param Parser $parser The parser object. Ignored.
   * @param PPFrame $frame The parser frame object.
   * @param Array $params The parameters and values together, not yet expanded or trimmed.
   * @return Array The function output along with relevant parser options.
   */
  public static function tipforRender( $parser, $frame, $params ) {
    if ( !self::$mIsFullyInitialized ) {
      self::performDelayedInitialization();
    }

    $targetTitleText = "";
    $targetTitle = null;
    if ( isset( $params[0] ) ) {
      $targetTitleText = trim( $frame->expand( $params[0] ) );
      if ( self::$mTooltipsEnabledHere ) {
        $targetTitle = Title::newFromText( $targetTitleText );
      }
    }

    $displayText = "";
    if ( isset( $params[1] ) ) {
      $displayText = trim( $frame->expand( $params[1] ) );
    }

    if ( $displayText === "" ) {
      $displayText = $targetTitleText;
      if ( isset( $params[2] ) ) {
        $displayText .= trim( $frame->expand( $params[2] ) );
      }
    }

    $output = $displayText;
    if ( $targetTitle !== null ) {
      $attribs = Array();
      if ( self::maybeAttachTooltip( $targetTitle, $attribs ) ) {
        $output = Xml::element( 'span', $attribs, $displayText, false );
      }
    }

    return Array( $output, 'noparse' => false );
  }
}
