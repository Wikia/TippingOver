<?php

/**
 * This class determines TippingOver's configuration from the appropriate $wgto global variables set up in extension
 * registration and possibly modified in LocalSettings.php.
 *
 * @author Eyes <eyes@aeongarden.com>
 * @copyright Copyright � 2015 Eyes
 * @license GPL-2.0-or-later
 */

class TippingOverConfiguration {
  // Constants for legacy settings
	const DISABLE = 0; // Configuration option for disabling certain features
	const PREQUERY = 1; // Configuration option for category filter processing
	const RUN_EARLY = 2; // Configuration option for processing certain features during page loads.
	const RUN_LATE = 3; // Configuration option for processing certain features on link mouseover events.
	const DISABLING = false; // Configuration option for making the category filter disable tooltips on links.
	const ENABLING = true;  // Configuration option for making the category filter enable tooltipls on links.

  // Current settings
	private $mEnabled = true;
	private $mEnableInNamespaces;
	private $mPreprocessCategoryFilter;
	private $mEnableOnImageLinks;
	private $mNamespacesWithTooltips;
	private $mEarlyTargetRedirectFollow;
	private $mEarlyCategoryFiltering;
	private $mEnablingCategory;
	private $mDisablingCategory;
	private $mEarlyPageTitleParse;
	private $mAssumeNonemptyPageTitle;
	private $mEarlyExistsCheck;
	private $mLoadingTooltip;
	private $mLateTargetRedirectFollow;
	private $mLateCategoryFiltering;
	private $mLatePageTitleParse;
	private $mLateExistsCheck;
	private $mAllowTwoRequestProcess;
	private $mMissingPageTooltip;
	private $mEmptyPageNameTooltip;

	/**
	 * Is TippiingOver enabled?
	 * @return bool true for enabled, false for disabled
	 */
	public function enabled() {
		return $this->mEnabled;
	}

	/**
	 * An array of namespaces with a bool indicating if tooltips should be seen on pages in that namespace.
	 * @return array Namespace index keys and bool values indicating if tooltips are enabled in the namespace.
	 */
	public function enableInNamespaces() {
		return $this->mEnableInNamespaces;
	}

	/**
	 * Indicates if tooltips should be shown on pages in the indicated namespace.
	 * @param int $index Index of a wiki namespace.
	 * @return bool true if tooltips should be shown on pages in the namespace, false otherwise.
	 */
	public function enableInNamespace( $index ): bool {
		return ( array_key_exists( $index, $this->mEnableInNamespaces ) &&
			 $this->mEnableInNamespaces[$index]
		   );
	}

	/**
	 * Should TippingOver build a lookup index for early category filtering?
	 * @return bool true for building a lookup index, false for using a DB lookup for each early filter check.
	 */
	public function preprocessCategoryFilter() {
		return $this->mPreprocessCategoryFilter;
	}

	/**
	 * Should TippingOver attach any tooltips to image links?
	 * @return bool true to allow tooltip attachment to image links, false otherwise
	 */
	public function enableOnImageLinks() {
		return $this->mEnableOnImageLinks;
	}

	/**
	 * An array of namespaces with a bool indicating if links with targets in given namespaces should get tooltips.
	 * @return array Namespace index keys and bool values indicating if links into the namespaces get tooltips.
	 */
	public function namespacesWithTooltips() {
		return $this->mNamespacesWithTooltips;
	}

	/**
	 * Indicates if tooltips should be attached on links targeting pages in the specified namespace.
	 * @param int $index Index of a wiki namespace.
	 * @return bool true if tooltips should be shown on links targeting pages in the specified namespace.
	 */
	public function namespaceWithTooltips( $index ): bool {
		return ( array_key_exists( $index, $this->mNamespacesWithTooltips ) &&
			 $this->mNamespacesWithTooltips[$index]
		   );
	}

	/**
	 * Should TippingOver follow target page redirects during early checks?
	 * @return bool true to follow redirects, false to either wait or not do it at all
	 */
	public function earlyTargetRedirectFollow() {
		return $this->mEarlyTargetRedirectFollow;
	}

	/**
	 * Should TippingOver perform category filtering during early checks?
	 * @return bool true to filter early, false to either wait or not do it at all
	 */
	public function earlyCategoryFiltering() {
		return $this->mEarlyCategoryFiltering;
	}

	/**
	 * The category that leaves tooltip attachment enabled if a target page is within when category filtering is enabled
	 * @return string The enabling category page title as a string
	 */
	public function enablingCategory() {
		return $this->mEnablingCategory;
	}

	/**
	 * The category that disables tooltip attachment if a target page is within when category filtering is enabled
	 * @return string The disabling category page title as a string
	 */
	public function disablingCategory() {
		return $this->mDisablingCategory;
	}

	/**
	 * Should TippingOver perform the page title parse during the early phase?
	 * @return bool true to parse early, false to wait for the late phase
	 */
	public function earlyPageTitleParse() {
		return $this->mEarlyPageTitleParse;
	}

	/**
	 * Should TippingOver assume page title parsing won't return an empty value?
	 * @return bool True to assume an empty page title won't be return, false to assume it might
	 */
	public function assumeNonemptyPageTitle() {
		return $this->mAssumeNonemptyPageTitle;
	}

	/**
	 * Should TippingOver check if the tooltip page exists during the early phase?
	 * @return bool true to make the check early, false to wait or skip it entirely
	 */
	public function earlyExistsCheck() {
		return $this->mEarlyExistsCheck;
	}

	/**
	 * Should TippingOver allow the two request process if needed to support loading tooltip, or disable that tooltip.
	 * @return bool true to allow the two request process, false to disable the loading tooltip if needed to prevent it
	 */
	public function allowTwoRequestProcess() {
		return $this->mAllowTwoRequestProcess;
	}

	/**
	 * The page name for the tooltip that should display as a link's attached tooltip is loading.
	 * @return string The page name for the loading tooltip as a string.
	 */
	public function loadingTooltip() {
		return $this->mLoadingTooltip;
	}

	/**
	 * Should TippingOver follow target page redirects during the late phase if no follow or parse was done early?
	 * @return bool true to follow redirects late absent early follows and parses, false to never do it late
	 */
	public function lateTargetRedirectFollow() {
		return $this->mLateTargetRedirectFollow;
	}

	/**
	 * Should TippingOver perform category filtering during late phase if not done early?
	 * @return bool true to filter late if not yet done, false to never do it late
	 */
	public function lateCategoryFiltering() {
		return $this->mLateCategoryFiltering;
	}

	/**
	 * Should TippingOver perform the page title parse during the late phase if not done early?
	 * @return bool true to parse late if not yet done, false to never do it late
	 */
	public function latePageTitleParse() {
		return $this->mLatePageTitleParse;
	}

	/**
	 * Should TippingOver check if the tooltip page exists during the late phase if not done early?
	 * @return bool true to make the check late if not yet done, false to never do it late
	 */
	public function lateExistsCheck() {
		return $this->mLateExistsCheck;
	}

	/**
	 * The page name for the tooltip that should display for a link when the tooltip page doesn't exist.
	 * @return string The page name for the missing page tooltip as a string.
	 */
	public function missingPageTooltip() {
		return $this->mMissingPageTooltip;
	}

	/**
	 * The page name for the tooltip that should display for a link when the page title parse returns a empty value.
	 * @return string The page name for the empty page name tooltip as a string.
	 */
	public function emptyPageNameTooltip() {
		return $this->mEmptyPageNameTooltip;
	}

	/**
	 * Generates a TippingOverConfiguration from the global TippingOver settings.
	 * @global Array $wgtoEnableInNamespaces What namespaces where pages get tooltips on their links.
	 * @global bool $wgtoPreprocessCategoryFilter Whether to build a lookup table for early category filtering
	 * @global bool $wgtoEnableOnImageLinks Whether to attach tooltips to image links.
	 * @global Array $wgtoNamespacesWithTooltips What target page namespaces enable tooltips on their links.
	 * @global bool $wgtoEarlyTargetRedirectFollow Follow target pages that are redirects early?
	 * @global bool $wgtoEarlyCategoryFiltering Check category filter early to enable or disable tooltips on links?
	 * @global string $wgtoEnablingCategory The category to use for an enabling category filter.
	 * @global string $wgtoDisablingCategory The category to use for a disabling category filter.
	 * @global bool $wgtoEarlyPageTitleParse Do the title parse to get the tooltip page title early?
	 * @global bool $wgtoAssumeNonemptyPageTitle Assume the title parse won't return empty values?
	 * @global bool $wgtoEarlyExistsCheck Check if the tooltip page actually exists during the early phase?
	 * @global string $wgtoLoadingTooltip The name of the page containing the loading tooltip.
	 * @global bool $wgtoLateTargetRedirectFollow Follow target pages that are redirects late?
	 * @global bool $wgtoLateCategoryFiltering Check category filter late to enable or disable tooltips on links?
	 * @global bool $wgtoLatePageTitleParse Do the title parse to get the tooltip page title late?
	 * @global bool $wgtoLateExistsCheck Check if the tooltip page actually exists during the late phase?
	 * @global bool $wgtoAllowTwoRequestProcess Permit two requests to support loading tooltips in some configurations?
	 * @global string $wgtoMissingPageTooltip The name of the page containing the missing page tooltip.
	 * @global string $wgtoEmptyPageNameTooltip The name of the page containing the empty page name tooltip.
	 */
	public function __construct() {
		global $wgtoEnableInNamespaces,
		   $wgtoPreprocessCategoryFilter,
		   $wgtoEnableOnImageLinks,
		   $wgtoNamespacesWithTooltips,
		   $wgtoEarlyTargetRedirectFollow,
		   $wgtoEarlyCategoryFiltering,
		   $wgtoEnablingCategory,
		   $wgtoDisablingCategory,
		   $wgtoEarlyPageTitleParse,
		   $wgtoAllowTwoRequestProcess,
		   $wgtoAssumeNonemptyPageTitle,
		   $wgtoEarlyExistsCheck,
		   $wgtoLoadingTooltip,
		   $wgtoLateTargetRedirectFollow,
		   $wgtoLateCategoryFiltering,
		   $wgtoLatePageTitleParse,
		   $wgtoLateExistsCheck,
		   $wgtoMissingPageTooltip,
		   $wgtoEmptyPageNameTooltip;

		$this->mEnableInNamespaces = $wgtoEnableInNamespaces;
		$this->mPreprocessCategoryFilter = $wgtoPreprocessCategoryFilter;
		$this->mEnableOnImageLinks = $wgtoEnableOnImageLinks;
		$this->mNamespacesWithTooltips = $wgtoNamespacesWithTooltips;
		$this->mEarlyTargetRedirectFollow = $wgtoEarlyTargetRedirectFollow;
		$this->mEarlyCategoryFiltering = $wgtoEarlyCategoryFiltering;
		$this->mEnablingCategory = $wgtoEnablingCategory;
		$this->mDisablingCategory = $wgtoDisablingCategory;
		$this->mEarlyPageTitleParse = $wgtoEarlyPageTitleParse;
		$this->mAssumeNonemptyPageTitle = $wgtoAssumeNonemptyPageTitle;
		$this->mEarlyExistsCheck = $wgtoEarlyExistsCheck;
		$this->mLoadingTooltip = $wgtoLoadingTooltip;
		$this->mLateTargetRedirectFollow = $wgtoLateTargetRedirectFollow;
		$this->mLateCategoryFiltering = $wgtoLateCategoryFiltering;
		$this->mLatePageTitleParse = $wgtoLatePageTitleParse;
		$this->mLateExistsCheck = $wgtoLateExistsCheck;
		$this->mAllowTwoRequestProcess = $wgtoAllowTwoRequestProcess;
		$this->mMissingPageTooltip = $wgtoMissingPageTooltip;
		$this->mEmptyPageNameTooltip = $wgtoEmptyPageNameTooltip;

		$this->update();
		$this->validate();
	}

	/**
	 * Under extension registration, it's no longer possible to define the legacy configuration constants in
	 * LocalSettings.php, at least not without requiring adding a special step, so the setting values will likely come in
	 * as strings rather than the expected integer values. This converts those strings.
	 * @param mixed &$setting The setting to be normalized.
	 */
	private function normalize( &$setting ): void {
		if ( is_string( $setting ) ) {
			switch ( $setting ) {
				case "TO_DISABLE":
					  $setting = self::DISABLE;
					break;
				case "TO_PREQUERY":
					$setting = self::PREQUERY;
					break;
				case "TO_RUN_EARLY":
					$setting = self::RUN_EARLY;
					break;
				case "TO_RUN_LATE":
					$setting = self::RUN_LATE;
					break;
				case "TO_DISABLING":
					$setting = self::DISABLING;
					break;
				case "TO_ENABLING":
					$setting = self::ENABLING;
					break;
			}
		}
	}

	/**
	 * This checks for depreciated TippingOver configuration variables. If they are set, the relevant updated settings
	 * are adjusted to their equivalents. Notices are issued when this occurs.
	 * @global int $wgtoFollowTargetRedirects self::DISABLE, self::RUN_EARLY, or self::RUN_LATE
	 * @global int $wgtoCategoryFiltering self::DISABLE, self::PREQUERY, self::RUN_EARLY, or self::RUN_LATE
	 * @global int $wgtoCategoryFilterMode self::ENABLING or self::DISABLING
	 * @global int $wgtoPageTitleParse self::RUN_EARLY or self::RUN_LATE
	 * @global int $wgtoExistsCheck self::DISABLE, self::RUN_EARLY, or self::RUN_LATE
	 */
	private function update(): void {
		global $wgtoFollowTargetRedirects,
		   $wgtoCategoryFiltering,
		   $wgtoCategoryFilterMode,
		   $wgtoPageTitleParse,
		   $wgtoExistsCheck;

		$this->normalize( $wgtoFollowTargetRedirects );
		$this->normalize( $wgtoCategoryFiltering );
		$this->normalize( $wgtoCategoryFilterMode );
		$this->normalize( $wgtoPageTitleParse );
		$this->normalize( $wgtoExistsCheck );

		if ( is_int( $wgtoFollowTargetRedirects ) ) {
			$this->mEarlyTargetRedirectFollow = ( $wgtoFollowTargetRedirects === self::RUN_EARLY );
			$this->mLateTargetRedirectFollow = ( $wgtoFollowTargetRedirects !== self::DISABLE );

			$msg = sprintf( '(TippingOver) $wgtoFollowTargetRedorects is deprecated. Equivalent updated setting applied: ' .
					  '$wgtoEarlyTargetRedirectFollow = %s; $wgtoLateTargetRedirectFollow = %s;',
					  $this->mEarlyTargetRedirectFollow ? "true" : "false",
					  $this->mLateTargetRedirectFollow ? "true" : "false"
					);
			wfLogWarning( $msg, 1, E_USER_DEPRECATED );
		}

		if ( isset( $wgtoCategoryFiltering ) ) {
			$this->mEarlyCategoryFiltering = ( $wgtoCategoryFiltering === self::PREQUERY ||
										 $wgtoCategoryFiltering === self::RUN_EARLY );
			$this->mPreprocessCategoryFilter = ( $wgtoCategoryFiltering === self::PREQUERY );
			$this->mLateCategoryFiltering = ( $wgtoCategoryFiltering === self::RUN_LATE );
			$msg = sprintf( '(TippingOver) $wgtoCategoryFiltering is deprecated. Equivalent updated settings applied: ' .
					  '$wgtoEarlyCategoryFiltering = %s; $wgtoPreprocessCategoryFilter = %s;' .
					  '$wgtoLateCategoryFiltering = %s;',
					  $this->mEarlyCategoryFiltering ? "true" : "false",
					  $this->mPreprocessCategoryFilter ? "true" : "false",
					  $this->mLateCategoryFiltering ? "true" : "false"
					);
			wfLogWarning( $msg, 1, E_USER_DEPRECATED );
		}

		if ( isset( $wgtoCategoryFilterMode ) ) {
			switch ( $wgtoCategoryFilterMode ) {
				case self::ENABLING:
					$this->mDisablingCategory = null;
					break;
				case self::DISABLING:
					$this->mEnablingCategory = null;
					break;
			}
			$msg = sprintf( '(TippingOver) $wgtoCategoryFilterMode is deprecated. Equivalent updated settings applied: ' .
					  '$wgtoEnablingCategory = %s; $wgtoDisablingCategory = %s;',
					  $this->mEnablingCategory === null ? "null" : '"' . $this->mEnablingCategory . '"',
					  $this->mDisablingCategory === null ? "null" : '"' . $this->mDisablingCategory . '"'
					);
			wfLogWarning( $msg, 1, E_USER_DEPRECATED );
		}

		if ( isset( $wgtoPageTitleParse ) ) {
			$this->mEarlyPageTitleParse = ( $wgtoPageTitleParse === self::RUN_EARLY );
			$this->mLatePageTitleParse = !( $this->mEarlyPageTitleParse );
			$msg = sprintf( '(TippingOver) $wgtoPageTitleParse is deprecated. Equivalent updated settings applied: ' .
					  '$wgtoEarlyPageTitleParse = %s; $wgtoLatePageTitleParse = %s;',
					  $this->mEarlyPageTitleParse ? "true" : "false",
					  $this->mLatePageTitleParse ? "true" : "false"
					);
			wfLogWarning( $msg, 1, E_USER_DEPRECATED );
		}

		if ( isset( $wgtoExistsCheck ) ) {
			$this->mEarlyExistsCheck = ( $wgtoExistsCheck === self::RUN_EARLY );
			$this->mLateExistsCheck = ( $wgtoExistsCheck === self::RUN_LATE );
			$msg = sprintf( '(TippingOver) $wgtoExistsCheck is deprecated. Equivalent updated settings applied: ' .
					  '$wgtoEarlyExistsCheck = %s; $wgtoLateExistsCheck = %s;',
					  $this->mEarlyExistsCheck ? "true" : "false",
					  $this->mLateExistsCheck ? "true" : "false"
					);
			wfLogWarning( $msg, 1, E_USER_DEPRECATED );
		}
	}

	/**
	 * Performs validation of the given configuration, overriding settings as necessary to make the configuration coherent
	 * or disabling TippingOver entirely in some cases. May output warnings in certain cases.
	 */
	private function validate(): void {
		if ( $this->mEnablingCategory !== null && !( is_string( $this->mEnablingCategory ) ) ) {
			$this->mEnablingCategory = null;
			$msg = '(TippingOver) Cannot interpret non-string value as a category page name in $wgEnablingCategory.' .
			 '$wgtoEnablingCategory has been forced to null';
			wfLogWarning( $msg, 1, E_USER_NOTICE );
		} elseif ( $this->mEnablingCategory === "" ) {
			$this->mEnablingCategory = null; // accept empty string as valid, but normalize it to null
		}

		if ( $this->mDisablingCategory !== null && !( is_string( $this->mDisablingCategory ) ) ) {
			$msg = '(TippingOver) Cannot interpret non-string value as a category page name in $wgDisablingCategory.' .
			 '$wgtoDisablingCategory has been forced to null';
			wfLogWarning( $msg, 1, E_USER_NOTICE );
			$this->mDisablingCategory = null;
		} elseif ( $this->mDisablingCategory === "" ) {
			$this->mDisablingCategory = null; // accpet empty string as valid, but normalize it to null
		}

		if ( $this->mEarlyCategoryFiltering || $this->mLateCategoryFiltering ) {
			if ( $this->enablingCategory() === null && $this->disablingCategory() === null ) {
				$this->mEarlyCategoryFiltering = false;
				$this->mLateCategoryFiltering = false;
				$msg = '(TippingOver) Category filtering requires a category in either $wgtoEnablingCategory or ' .
				'$wgtoDisablingCategory. $wgtoEarlyCategoryFIltering and $wgtoLateCategoryFiltering have been forced ' .
				'to false';
				wfLogWarning( $msg, 1, E_USER_NOTICE );
			}
		}

		if ( !( $this->mEarlyPageTitleParse || $this->mLatePageTitleParse ) ) {
			$this->mEnabled = false;
			$msg = '(TippingOver) $wgtoEarlyPageTitleParse and $wgtoLatePageTitleParse are both false. ' .
			 'At least one must be true to use the current version of TippingOver. TippingOver is disabled';
			wfLogWarning( $msg, 1, E_USER_NOTICE );
		}

		if ( !( $this->mEarlyPageTitleParse ) && $this->mEarlyExistsCheck ) {
			$this->mEarlyExistsCheck = false;
			$msg = '(TippingOver) Early exists checks are unavailable when $wgtoEarlyPageTitleParse is false.' .
			 '$wgtoEarlyExistsCheck has been forced to false';
			wfLogWarning( $msg, 1, E_USER_NOTICE );
		}

		if ( $this->mLoadingTooltip !== null && !( is_string( $this->mLoadingTooltip ) ) ) {
			$this->mLoadingTooltip = null;
			$msg = '(TippingOver) Cannot interpret non-string value as a page name in $wgLoadingTooltip.' .
			 '$wgtoLoadingTooltip has been forced to null';
			wfLogWarning( $msg, 1, E_USER_NOTICE );
		} elseif ( $this->mLoadingTooltip === "" ) {
			$this->mLoadingTooltip = null; // accept empty string as valid, but normalize it to null
		}

	/* Quietly shut off lazy checks when the equivalent early checks are enabled. In this version, it's not useful
	 * for both to be enabled, but that's likely to change in future versions, so don't want to discourage users
	 * from having them "enabled" in the configuration even if it's currently overridden.
	 */
		$this->mLateTargetRedirectFollow = !( $this->mEarlyTargetRedirectFollow ) && $this->mLateTargetRedirectFollow;
		$this->mLateCategoryFiltering = !( $this->mEarlyCategoryFiltering ) && $this->mLateCategoryFiltering;
		$this->mLatePageTitleParse = !( $this->mEarlyPageTitleParse ) && $this->mLatePageTitleParse;
		$this->mLateExistsCheck = !( $this->mEarlyExistsCheck ) && $this->mLateExistsCheck;

		if ( $this->mMissingPageTooltip !== null && !( is_string( $this->mMissingPageTooltip ) ) ) {
			$this->mMissingPageTooltip = null;
			$msg = '(TippingOver) Cannot interpret non-string value as a page name in $wgMissingPageTooltip.' .
			 '$wgtoMissingPageTooltip has been forced to null';
			wfLogWarning( $msg, 1, E_USER_NOTICE );
		} elseif ( $this->mMissingPageTooltip === "" ) {
			$this->mMissingPageTooltip = null; // accept empty string as valid, but normalize it to null
		}

		if ( $this->mEmptyPageNameTooltip !== null && !( is_string( $this->mEmptyPageNameTooltip ) ) ) {
			$this->mEmptyPageNameTooltip = null;
			$msg = '(TippingOver) Cannot interpret non-string value as a page name in $wgEmptyPageNameTooltip.' .
			 '$wgtoEmptyPageNameTooltip has been forced to null';
			wfLogWarning( $msg, 1, E_USER_NOTICE );
		} elseif ( $this->mEmptyPageNameTooltip === "" ) {
			$this->mEmptyPageNameTooltip = null; // accept empty string as valid, but normalize it to null
		}
	}
}
