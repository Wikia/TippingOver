<?php

/**
 * This static class contains tooltip-related functions needed both by index.php and api.php calls.
 *
 * @author Eyes <eyes@aeongarden.com>
 * @copyright Copyright � 2015 Eyes
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class WikiTooltipsCore {
  /**
   * Indicates if we're in the parser because we're parsing the content of a tooltip. There is no value in adding
   * tooltips to content inside a tooltip, and it can even lead to potential fatal errors by calling the parser
   * recursively, so when this is false, attaching tooltips to content is disabled.
   * @var bool
   */
  private static $mIsTooltipAttachmentSafe = true;

  /**
   * Indicates if we're in the parser because we're parsing the content of a tooltip. There is no value in adding
   * tooltips to content inside a tooltip, and it can even lead to potential fatal errors by calling the parser
   * recursively, so when this is true, attaching tooltips to content is disabled.
   * @return bool
   */
  public static function isTooltipAttachmentSafe() {
    return self::$mIsTooltipAttachmentSafe;
  }

  /**
   * Gets a Title object for the appropriate filter category, or returns null if there is an error getting it. Note
   * this function does not check to see if category filtering is disabled.
   * @return Title The Title object of the appropriate root category or null if there is an error generating it..
   */
  public static function getFilterCategoryTitle( $conf ) {
    if ( $conf->enablingCategory() !== null ) {
      return Title::newFromText( $conf->enablingCategory(), NS_CATEGORY );
    } else {
      return Title::newFromText( $conf->disablingCategory(), NS_CATEGORY );
    }
  }

  /**
   * This function will check if the given page title references a redirect and returns the redirect target title if
   * it does; otherwise, it returns the title given.
   * @param Title $title The title to follow any redirect on.
   * @return Title The original title if it isn't a redirect or the title of the redirect target if it is.
   */
  public static function followRedirect( $title ) {
    if ( $title !== null && $title->getNamespace() !== NS_MEDIA && $title->getNamespace() > -1 ) {
      $page = WikiPage::factory( $title );
      $target = $page->getRedirectTarget();
      if ( $target !== null ) {
        return $target;
      }
    }

    return $title;
  }

  /**
   * Indicate tooltip attachment is unsafe in the current state. (Usually means we're parsing the content of a tooltip,
   * so the attachment process is likely to call the parser redundantly and cause a fatal error.)
   */
  public static function flagTooltipAttachmentUnsafe( ) {
    self::$mIsTooltipAttachmentSafe = false;
  }

  /**
   * Indicate tooltip attachment is safe in the current state.
   */
  public static function flagTooltipAttachmentSafe( ) {
    self::$mIsTooltipAttachmentSafe = true;
  }

  /**
   * Parser output is wrapped within at least a p tag, and in more recent MediaWiki versions, an outer div. These are
   * undesirable when trying to parse down to just a page title, so this function is here to remove them.
   * @param string $out The parser output to strip outer tags from.
   * @return string The output sans outer tags
   */
  public static function stripOuterTags( $out ) {
    $matches = [];
    $old = null;
    $new = $out;
    while ( $old !== $new && preg_match( '/^<.*?>(.+)\n?<\/.*?>\n?$/sU', $new, $matches ) ) {
      $old = $new;
      if ( count( $matches ) > 0 && $matches[1] !== "" ) {
        $new = $matches[1];
      }
    }
    return trim( $new );
  }

  /**
   * Returns the wikitext used to retrieve the appropriate content for a given tooltip page.
   * @param Title $title A title object for the given tooltip page.
   * @return string The wikitext to parse to get the appropriate content.
   */
  public static function getTooltipWikiText( $title ) {
    if ( $title !== null ) {
      if ( $title->getNamespace() === NS_FILE ) {
        return '[[' . $title->getPrefixedText() . '|link=]]';
      } else {
        return '{{:' . $title->getPrefixedText() . '}}';
      }
    } else {
      return null;
    }
  }

  /**
   * The function handles the #tipfor parser function. If tooltip attachment is unsafe, the function is handled here.
   * Otherwise, the job is passed to WikiTooltips since we should only be safe to attach tooltips outside of the API
   * module.
   * @param Parser $parser The parser object. Ignored.
   * @param PPFrame $frame The parser frame object.
   * @param Array $params The parameters and values together, not yet expanded or trimmed.
   * @return Array The function output along with relevant parser options.
   */
  public static function tipforRender( $parser, $frame, $params ) {
    if ( self::$mIsTooltipAttachmentSafe ) {
      // Tooltip attachment is flagged as safe, so we shouldn't be in the API module, kick it to WikiTooltips.
      return WikiTooltips::tipforRender( $parser, $frame, $params );
    } else {
      // Tooltip attachment is flagged as unsafe, so let's not do it. Just return the appropraite content unattached.
      $displayText = "";
      if ( isset( $params[1] ) ) {
        $displayText = trim( $frame->expand( $params[1] ) );
      } else if ( isset( $params[0] ) ) {
        $displayText = trim( $frame->expand( $params[0] ) );
      }
      return Array( $displayText, 'noparse' => false );
    }
  }
}
