<?php

class MC4WP_BuddyPress_Integration extends MC4WP_User_Integration {

	public $type = 'buddypress';

	public $name = 'BuddyPress';

	public function add_hooks() {
		add_action( 'bp_before_registration_submit_buttons', array( $this, 'output_checkbox' ), 20 );
		add_action( 'bp_core_signup_user', array( $this, 'subscribe_from_buddypress' ), 10, 4 );
	}

	/**
	* Subscribes from BuddyPress Registration Form
	* @param int $user_id
	* @param string $user_login
	* @param string $user_password
	* @param string $user_email
	* @param array $usermeta
	*/
	public function subscribe_from_buddypress( $user_id, $user_login, $user_password, $user_email ) {

		if( $this->is_spam() ) {
			return false;
		}

		if ( $this->checkbox_was_checked() === false ) {
			return false;
		}

		$user = get_userdata( $user_id );

		// was a user found with the given ID?
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		// gather email address and name from user who BuddyPress registered
		$email = $user->user_email;
		$merge_vars = $this->user_merge_vars( $user );

		return $this->subscribe( $email, $merge_vars, $user_id );
	}
	/* End BuddyPress functions */

}