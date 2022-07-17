<?php
/*
Plugin Name: Stripe Payments Subscriptions & Simple WP Membership Cancellation Interface
Version: 0.1
Plugin URI: https://www.abis-ltd.com/
Author: Ben Bois
Author URI: https://www.abis-ltd.com/
Description: Hook implementation to fire Simple WP Membership action during Stripe Payments Subscriptions Cancellation
Date: 20220715
 */
class Stripe_SWPM_Cancellation {
	public function __construct() {
		add_action( 'asp_subscription_ended', array( $this, 'handle_stripe_swpm_cancellation' ), 10, 2 );
		add_action( 'asp_subscription_canceled', array( $this, 'handle_stripe_swpm_cancellation' ), 10, 2 );
	}
	public function handle_stripe_swpm_cancellation( $sub_post_id, $data ) {
		
		// Subscription ID
		$sub_id = $data[id];

		// Simple WP Member id based on Subscription ID
		global $wpdb;
		$wpdb_tablename = $wpdb->prefix . 'swpm_members_tbl';
		$query          = 'SELECT `member_id` FROM `' . $wpdb_tablename . '` WHERE `subscr_id` = "' . $sub_id . '" LIMIT 1';
		$result         = $wpdb->get_results( $query, ARRAY_A);

		$member_id = $result[0]['member_id'];
		
		ASP_Debug_Logger::log( json_encode( '[Sub] Simple WP Member ID to cancel:' . $member_id ) );
		
		// User exists
		if( $member_id > 0 ) {
			
			// Set inactive flag to user member account
			SwpmMemberUtils::update_account_state($member_id, 'inactive');
			
			// Log action
			ASP_Debug_Logger::log( json_encode( '[Sub] Subscription Inactive in Simple WP Membership.' ) );
			
			// Email parameters
			$user_name = SwpmMemberUtils::get_member_field_by_id( $member_id, 'user_name' );
			$user_email = SwpmMemberUtils::get_member_field_by_id( $member_id, 'email' );
			$from = 'WebSite <contact@website.dom>';
			$time = date("Y-m-d H:i:s");
			$chr = "\r\n";
			$headers = array( 'Content-Type: text/html; charset=UTF-8' . $chr,'From: ' . $from . $chr, 'Reply-To: ' . $from . $chr, );
			
			// Send to MEMBER
			$msg_subject = 'Your Membership has been cancelled' . $chr;
      $msg_body = 'Dear ' . $user_name . $chr;
            . '<br/><br/>Your membership is now cancelled!' . $chr 
						. '<br/><br/>You can renew anytime by <a href="' . get_home_url() . '/membership/renewal/">clicking HERE!</a>'
						. '<br/><br/>Thank you' . $chr;
			wp_mail( $user_email, $msg_subject, $msg_body,  $headers);
			
			// Log action
			ASP_Debug_Logger::log( json_encode( '[Sub] Email to Member sent: ' . $user_email ) );
			
			// Send to ADMIN
			$stripe_subscr_link = 'https://dashboard.stripe.com/subscriptions/' . $sub_id;
			$msg_subject = 'Notification of a Membership Cancellation' . $chr;
			$msg_body = 'Member ' . $user_name . ' has cancelled its registration.' . $chr
			. '<br/><b>The linking Stripe subscription has been also cancelled!</b>' . $chr
			. '<br/><br/>Email: '. $user_email
			. '<br/>Date: ' . $time . $chr 
			. '<br/>Stripe Subsc.: <a href="' . $stripe_subscr_link . '">' . $sub_id . '</a>' . $chr ;
			$to_emails = get_option( 'admin_email' );
			wp_mail( $to_emails, $msg_subject, $msg_body, $headers);        
			
			// Log action
			ASP_Debug_Logger::log( json_encode( '[Sub] Email to Admin sent: ' . $to_emails ) );
		}
	}
}
new Stripe_SWPM_Cancellation();
