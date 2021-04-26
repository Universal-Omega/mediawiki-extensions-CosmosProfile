<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Skin\Cosmos\CosmosSocialProfile;

/**
 * User profile Wiki Page
 *
 * @file
 * @ingroup Extensions
 * @author David Pean <david.pean@gmail.com>
 * @copyright Copyright © 2007, Wikia Inc.
 * @license GPL-2.0-or-later
 */

class CosmosProfileHeader extends Article {

	/**
	 * @var Title
	 */
	public $title = null;

	/**
	 * @var User User object for the person whose profile is being viewed
	 */
	public $profileOwner;

	/**
	 * @var User User who is viewing someone's profile
	 */
	public $viewingUser;

	/**
	 * @var string user name of the user whose profile we're viewing
	 * @deprecated Prefer using getName() on $this->profileOwner or $this->viewingUser as appropriate
	 */
	public $user_name;

	/**
	 * @var int user ID of the user whose profile we're viewing
	 * @deprecated Prefer using getId() or better yet, getActorId(), on $this->profileOwner or $this->viewingUser as appropriate
	 */
	public $user_id;

	/**
	 * @var User User object representing the user whose profile we're viewing
	 * @deprecated Confusing name; prefer using $this->profileOwner or $this->viewingUser as appropriate
	 */
	public $user;

	/**
	 * @var bool is the current user the owner of the profile page?
	 */
	public $is_owner;

	function __construct( $title ) {
		$context = $this->getContext();
		// This is the user *who is viewing* the page
		$user = $this->viewingUser = $context->getUser();

		parent::__construct( $title );
		// These vars represent info about the user *whose page is being viewed*
		$this->profileOwner = User::newFromName( $title->getText() );

		$this->user_name = $this->profileOwner->getName();
		$this->user_id = $this->profileOwner->getId();

		$this->user = $this->profileOwner;
		$this->user->load();

		$this->is_owner = ( $this->profileOwner->getName() == $user->getName() );
	}

	/**
	 * Is the current user the owner of the profile page?
	 * In other words, is the current user's username the same as that of the
	 * profile's owner's?
	 *
	 * @return bool
	 */
	function isOwner() {
		return $this->is_owner;
	}

	function view() {
		$context = $this->getContext();
		$out = $context->getOutput();

		$out->setPageTitle( $this->getTitle()->getPrefixedText() );

		// No need to display noarticletext, we use our own message
		// @todo FIXME: this was basically "!$this->profileOwner" prior to actor.
		// Now we need to explicitly check for this b/c if we don't and we're viewing
		// the User: page of a nonexistent user as an anon, that profile page will
		// display as User:<your IP address> and $this->profileOwner will have been
		// set to a User object representing that anonymous user (IP address).
		if ( $this->profileOwner->isAnon() ) {
			parent::view();
			return '';
		}

		$out->addHTML( '<div id="profile-top">' );
		$out->addHTML( $this->getProfileHeader() );
		$out->addHTML( '<div class="visualClear"></div></div>' );

		// Add JS -- needed by UserBoard stuff but also by the "change profile type" button
		// If this were loaded in getUserBoard() as it originally was, then the JS that deals
		// with the "change profile type" button would *not* work when the user is using a
		// regular wikitext user page despite that the social profile header would still be
		// displayed.
		// @see T202272, T242689
		$out->addModules( 'ext.cosmosprofile.userprofile.js' );

		// User does not want social profile for User:user_name, so we just
		// show header + page content
		if ( $this->getTitle()->inNamespaces( NS_USER ) ) {
			parent::view();
			return '';
		}
	}

	/**
	 * Get the header for the social profile page, which includes the user's
	 * points and user level (if enabled in the site configuration) and lots
	 * more.
	 *
	 * @return string HTML suitable for output
	 */
	function getProfileHeader() {
		$context = $this->getContext();
		$language = $context->getLanguage();

		// Safe URLs
		$watchlist = SpecialPage::getTitleFor( 'Watchlist' );
		$contributions = SpecialPage::getTitleFor( 'Contributions', $this->profileOwner->getName() );
		$upload_avatar = SpecialPage::getTitleFor( 'UploadAvatar' );

		$avatar = new ProfileAvatar( $this->profileOwner->getId(), 'l' );

		$output = '<div id="profile-image">' . $avatar->getAvatarURL();
		// Expose the link to the avatar removal page in the UI when the user has
		// uploaded a custom avatar
		$canRemoveOthersAvatars = $this->viewingUser->isAllowed( 'avatarremove' );
		if ( !$avatar->isDefault() && ( $canRemoveOthersAvatars || $this->isOwner() ) ) {
			// Different URLs for privileged and regular users
			// Need to specify the user for people who are able to remove anyone's avatar
			// via the special page; for regular users, it doesn't matter because they
			// can't remove anyone else's but their own avatar via RemoveAvatar
			if ( $canRemoveOthersAvatars ) {
				$removeAvatarURL = SpecialPage::getTitleFor( 'RemoveAvatar', $this->profileOwner->getName() )->getFullURL();
			} else {
				$removeAvatarURL = SpecialPage::getTitleFor( 'RemoveAvatar' )->getFullURL();
			}
			$output .= '<p><a href="' . htmlspecialchars( $removeAvatarURL ) . '" rel="nofollow">' .
					wfMessage( 'user-profile-remove-avatar' )->text() . '</a>
			</p>';
		}
		global $wgCosmosSocialProfileShowGroupTags, $wgCosmosSocialProfileShowEditCount, $wgCosmosSocialProfileFollowBioRedirects, $wgCosmosSocialProfileAllowBio;
		$groupTags = $wgCosmosSocialProfileShowGroupTags
				? CosmosSocialProfile::getUserGroups( $this->profileOwner->getName() )
				: null;

			if ( $wgCosmosSocialProfileShowEditCount ) {
				$contribsURL = $contributions->getFullURL();

				$editCount = '<br/> <div class="contributions-details tally"><a href="' .
					htmlspecialchars( $contribsURL ) . '"><em>' . CosmosSocialProfile::getUserEdits( $this->profileOwner->getName() ) .
					'</em><span>' . $context->msg( 'cosmos-editcount-label' )->escaped() . '<br>' .
					CosmosSocialProfile::getUserRegistration( $this->profileOwner->getName() ) . '</span></a></div>';
			} else {
				$editCount = null;
			}

			// experimental
			$followBioRedirects = $wgCosmosSocialProfileFollowBioRedirects;

			$bio = $wgCosmosSocialProfileAllowBio
				? CosmosSocialProfile::getUserBio( $this->profileOwner->getName(), $followBioRedirects )
				: null;


		$output .= '</div>';

		$output .= '<div id="profile-right">';

		$output .= '<div class="hgroup">
				<h1 itemprop="name">' .
					htmlspecialchars( $this->profileOwner->getName() ) .
				'</h1>' . $groupTags . $editCount . $bio;
		$output .= '<div class="visualClear"></div>
			</div>
			<div class="profile-actions">';

		$profileLinks = [];
		if ( $this->isOwner() ) {
			$profileLinks['user-upload-avatar'] =
				'<a href="' . htmlspecialchars( $upload_avatar->getFullURL() ) . '">' . wfMessage( 'user-upload-avatar' )->escaped() . '</a>';
			$profileLinks['user-watchlist'] =
				'<a href="' . htmlspecialchars( $watchlist->getFullURL() ) . '">' . wfMessage( 'user-watchlist' )->escaped() . '</a>';
		}

		$profileLinks['user-contributions'] =
			'<a href="' . htmlspecialchars( $contributions->getFullURL() ) . '" rel="nofollow">' .
				wfMessage( 'user-contributions' )->escaped() . '</a>';

		$output .= $language->pipeList( $profileLinks );
		$output .= '</div>

		</div>';

		return $output;
	}
}