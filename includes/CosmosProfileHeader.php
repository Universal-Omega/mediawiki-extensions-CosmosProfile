<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

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
		global $wgCosmosProfileShowGroupTags, $wgCosmosProfileShowEditCount, $wgCosmosProfileFollowBioRedirects, $wgCosmosProfileAllowBio;
		$groupTags = $wgCosmosProfileShowGroupTags
				? self::getUserGroups( $this->profileOwner->getName() )
				: null;

			if ( $wgCosmosProfileShowEditCount ) {
				$contribsURL = $contributions->getFullURL();

				$editCount = '<br/> <div class="contributions-details tally"><a href="' .
					htmlspecialchars( $contribsURL ) . '"><em>' . self::getUserEdits( $this->profileOwner->getName() ) .
					'</em><span>' . $context->msg( 'cosmosprofile-editcount-label' )->escaped() . '<br>' .
					self::getUserRegistration( $this->profileOwner->getName() ) . '</span></a></div>';
			} else {
				$editCount = null;
			}

			// experimental
			$followBioRedirects = $wgCosmosProfileFollowBioRedirects;

			$bio = $wgCosmosProfileAllowBio
				? self::getUserBio( $this->profileOwner->getName(), $followBioRedirects )
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

	/**
	 * @param string $user
	 * @return User|false
	 */
	private static function getUser( $user ) {
		$title = Title::newFromText( $user );

		if (
			is_object( $title ) &&
			$title->inNamespace( NS_USER ) &&
			!$title->isSubpage()
		) {
			$user = $title->getText();
		}

		$user = User::newFromName( $user );

		return $user;
	}

	/**
	 * @param string $user
	 * @return string|null
	 */
	private static function getUserRegistration( $user ) {
		$user = self::getUser( $user );

		if ( $user ) {
			return date( 'F j, Y', strtotime( $user->getRegistration() ) );
		}
	}

	/**
	 * @param string $user
	 * @return string|null
	 */
	private static function getUserGroups( $user ) {
		global $wgCosmosProfileTagGroups, $wgCosmosProfileNumberofGroupTags;

		$user = self::getUser( $user );

		if ( $user && $user->isBlocked() ) {
			$userTags = Html::element(
				'span',
				[ 'class' => 'tag tag-blocked' ],
				wfMessage( 'cosmosprofile-user-blocked' )->text()
			);
		} elseif ( $user ) {
			$numberOfTags = 0;
			$userTags = '';

			foreach ( $wgCosmosProfileTagGroups as $value ) {
				if ( in_array( $value, $user->getGroups() ) ) {
					$numberOfTags++;
					$numberOfTagsConfig = $wgCosmosProfileNumberofGroupTags;
					$userGroupMessage = wfMessage( "group-{$value}-member" );

					if ( $numberOfTags <= $numberOfTagsConfig ) {
						$userTags .= Html::element(
							'span',
							[ 'class' => 'tag tag-' . Sanitizer::escapeClass( $value ) ],
							ucfirst( ( !$userGroupMessage->isDisabled() ? $userGroupMessage->text() : $value ) )
						);
					}
				}
			}
		} else {
			$userTags = null;
		}

		return $userTags;
	}

	/**
	 * @param string $user
	 * @return int|null
	 */
	private static function getUserEdits( $user ) {
		$user = self::getUser( $user );

		if ( $user ) {
			return $user->getEditCount();
		}
	}

	/**
	 * @param string $user
	 * @param bool $followRedirects
	 * @return string|null
	 */
	private static function getUserBio( $user, $followRedirects ) {
		if ( $user && Title::newFromText( "User:{$user}/bio" )->isKnown() ) {
			$userBioPage = Title::newFromText( "User:{$user}/bio" );

			$wikiPage = new WikiPage( $userBioPage );

			$content = $wikiPage->getContent();

			// experimental
			if (
				$followRedirects &&
				$userBioPage->isRedirect() &&
				$content->getRedirectTarget()->isKnown() &&
				$content->getRedirectTarget()->inNamespace( NS_USER )
			) {
				$userBioPage = $content->getRedirectTarget();

				$wikiPage = new WikiPage( $userBioPage );

				$content = $wikiPage->getContent();
			}

			return $content instanceof TextContent
				? Html::element( 'p', [ 'class' => 'bio' ], $content->getText() )
				: null;
		}
	}
}
