<?php

class CosmosProfileHooks {
	/**
	 * Mark social user pages as known so they appear in blue, unless the user
	 * is explicitly using a wiki user page, which may or may not exist.
	 *
	 * The assumption here is that when we have a Title pointing to a non-subpage
	 * page in the user NS (i.e. a user profile page), we _probably_ want to treat
	 * it as a blue link unless we have a good reason not to.
	 *
	 * Pages like Special:TopUsers etc. which use LinkRenderer would be slightly
	 * confusing if they'd show a mixture of red and blue links when in fact,
	 * regardless of the URL params, with SocialProfile installed they behave the
	 * same.
	 *
	 * @param Title $title title to check
	 * @param bool &$isKnown Whether the page should be considered known
	 */
	public static function onTitleIsAlwaysKnown( $title, &$isKnown ) {
		if ( $title->inNamespace( NS_USER ) && !$title->isSubpage() ) {
			$isKnown = true;
		}
	}

	/**
	 * Called by ArticleFromTitle hook
	 * Calls UserProfilePage instead of standard article on registered users'
	 * User: or User_profile: pages which are not subpages
	 *
	 * @param Title $title
	 * @param Article|null &$article
	 * @param IContextSource $context
	 */
	public static function onArticleFromTitle( Title $title, &$article, $context ) {
		global $wgHooks;

		$out = $context->getOutput();
		$request = $context->getRequest();
		$pageTitle = $title->getText();

		if (
			!$title->isSubpage() &&
			 $title->inNamespace( NS_USER ) &&
			!User::isIP( $pageTitle )
		) {
			$out->enableClientCache( false );
			$wgHooks['ParserLimitReportPrepare'][] = 'CosmosProfileHooks::onParserLimitReportPrepare';

			$out->addModuleStyles( [
				'ext.cosmosprofile.clearfix',
				'ext.cosmosprofile.userprofile.css'
			] );

			$article = new CosmosProfileHeader( $title );
		}
	}

	/**
	 * Mark page as uncacheable
	 *
	 * @param Parser $parser
	 * @param ParserOutput $output
	 */
	public static function onParserLimitReportPrepare( $parser, $output ) {
		$parser->getOutput()->updateCacheExpiry( 0 );
	}

	/**
	 * Load the necessary CSS for avatars in diffs if that feature is enabled.
	 *
	 * @param DifferenceEngine $differenceEngine
	 */
	public static function onDifferenceEngineShowDiff( $differenceEngine ) {
		global $wgCosmosProfileAvatarsInDiffs;
		if ( $wgCosmosProfileAvatarsInDiffs ) {
			$differenceEngine->getOutput()->addModuleStyles( 'ext.cosmosprofile.userprofile.diff' );
		}
	}

	/**
	 * Displays user avatars in diffs.
	 *
	 * This is largely based on wikiHow's /extensions/wikihow/hooks/DiffHooks.php
	 * (as of 2016-07-08) with some tweaks for SocialProfile.
	 *
	 * @author Scott Cushman@wikiHow -- original code
	 * @author Jack Phoenix, Samantha Nguyen -- modifications
	 *
	 * @param DifferenceEngine $differenceEngine
	 * @param string &$oldHeader
	 * @param string $prevLink
	 * @param string $oldMinor
	 * @param bool $diffOnly
	 * @param string $ldel
	 * @param bool $unhide
	 */
	public static function onDifferenceEngineOldHeader( $differenceEngine, &$oldHeader, $prevLink, $oldMinor, $diffOnly, $ldel, $unhide ) {
		global $wgCosmosProfileAvatarsInDiffs;

		if ( !$wgCosmosProfileAvatarsInDiffs ) {
			return;
		}

		$oldRevision = $differenceEngine->getOldRevision();

		$oldRevisionHeader = $differenceEngine->getRevisionHeader( $oldRevision, 'complete', 'old' );

		$oldRevUser = $oldRevision->getUser();
		if ( $oldRevUser instanceof User ) {
			$username = $oldRevUser->getName();
			$uid = $oldRevUser->getId();
		} else {
			$username = $oldRevision->getUserText();
			$uid = $oldRevUser; // sic!
		}
		$avatar = new ProfileAvatar( $uid, 'l' );
		$avatarElement = $avatar->getAvatarURL( [
			'alt' => $username,
			'title' => $username,
			'class' => 'diff-avatar'
		] );

		$oldHeader = '<div id="mw-diff-otitle1"><h4>' . $oldRevisionHeader . '</h4></div>' .
			'<div id="mw-diff-otitle2">' . $avatarElement . '<div id="mw-diff-oinfo">' .
			Linker::revUserTools( $oldRevision, !$unhide ) .
			// '<br /><div id="mw-diff-odaysago">' . $differenceEngine->mOldRev->getTimestamp() . '</div>' .
			Linker::revComment( $oldRevision, !$diffOnly, !$unhide ) .
			'</div></div>' .
			'<div id="mw-diff-otitle3" class="rccomment">' . $oldMinor . $ldel . '</div>' .
			'<div id="mw-diff-otitle4">' . $prevLink . '</div>';
	}

	/**
	 * Displays user avatars in diffs.
	 *
	 * This is largely based on wikiHow's /extensions/wikihow/hooks/DiffHooks.php
	 * (as of 2016-07-08) with some tweaks for SocialProfile.
	 *
	 * @author Scott Cushman@wikiHow -- original code
	 * @author Jack Phoenix, Samantha Nguyen -- modifications
	 *
	 * @param DifferenceEngine $differenceEngine
	 * @param string &$newHeader
	 * @param string[] $formattedRevisionTools
	 * @param string $nextLink
	 * @param string $rollback
	 * @param string $newMinor
	 * @param bool $diffOnly
	 * @param string $rdel
	 * @param bool $unhide
	 */
	public static function onDifferenceEngineNewHeader( $differenceEngine, &$newHeader, $formattedRevisionTools, $nextLink, $rollback, $newMinor, $diffOnly, $rdel, $unhide ) {
		global $wgCosmosProfileAvatarsInDiffs;

		if ( !$wgCosmosProfileAvatarsInDiffs ) {
			return;
		}

		$newRevision = $differenceEngine->getNewRevision();

		$newRevisionHeader =
			$differenceEngine->getRevisionHeader( $newRevision, 'complete', 'new' ) .
			' ' . implode( ' ', $formattedRevisionTools );

		$newRevUser = $newRevision->getUser();
		if ( $newRevUser instanceof User ) {
			$username = $newRevUser->getName();
			$uid = $newRevUser->getId();
		} else {
			$username = $newRevision->getUserText();
			$uid = $newRevUser; // sic!
		}
		$avatar = new ProfileAvatar( $uid, 'l' );
		$avatarElement = $avatar->getAvatarURL( [
			'alt' => $username,
			'title' => $username,
			'class' => 'diff-avatar'
		] );

		$newHeader = '<div id="mw-diff-ntitle1"><h4>' . $newRevisionHeader . '</h4></div>' .
			'<div id="mw-diff-ntitle2">' . $avatarElement . '<div id="mw-diff-oinfo">'
			. Linker::revUserTools( $newRevision, !$unhide ) .
			" $rollback " .
			// '<br /><div id="mw-diff-ndaysago">' . $differenceEngine->mNewRev->getTimestamp() . '</div>' .
			Linker::revComment( $newRevision, !$diffOnly, !$unhide ) .
			'</div></div>' .
			'<div id="mw-diff-ntitle3" class="rccomment">' . $newMinor . $rdel . '</div>' .
			'<div id="mw-diff-ntitle4">' . $nextLink . $differenceEngine->markPatrolledLink() . '</div>';
	}

}