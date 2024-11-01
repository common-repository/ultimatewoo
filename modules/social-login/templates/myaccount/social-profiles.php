<?php

/**
 * Renders any linked social profiles on my account page.
 *
 * @param array $linked_profiles Profiles that are already linked to the current user's account
 * @param string $available_providers All available social login providers
 * @param string $return_url
 *
 * @version 1.1.0
 * @since 1.1.0
 */
?>

<div class="wc-social-login-profile">

	<h2><?php _e( 'My Social Login Accounts', WC_Social_Login::TEXT_DOMAIN ); ?></h2>

	<?php if ( $linked_profiles ) : ?>

		<?php
			$add_more_link = '';
			if ( count( $linked_profiles ) < count( $available_providers ) ) {
				$add_more_link = ' ' . sprintf( __( '%sAdd more...%s', WC_Social_Login::TEXT_DOMAIN ), '<a href="#" class="js-show-available-providers">', '</a>' );
			}
		?>

		<p><?php echo __( 'Your account is connected to the following social login providers.', WC_Social_Login::TEXT_DOMAIN ) . $add_more_link; ?></p>

		<table class="shop_table shop_table_responsive wc-social-login-linked-profiles">
			<thead>
				<tr>
					<th><?php _e( 'Provider', WC_Social_Login::TEXT_DOMAIN ); ?></th>
					<th><?php _e( 'Account', WC_Social_Login::TEXT_DOMAIN ); ?></th>
					<th colspan="2"><?php _e( 'Last login', WC_Social_Login::TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>

			<?php foreach ( $linked_profiles as $provider_id => $profile ) :
				$provider = $GLOBALS['wc_social_login']->get_provider( $provider_id );
				$login_timestamp = get_user_meta( get_current_user_id(), '_wc_social_login_' . $provider_id . '_login_timestamp', true );
			?>
			<tr>
				<td data-title="<?php _e( 'Provider', WC_Social_Login::TEXT_DOMAIN ); ?>">
					<?php printf( '<span class="social-badge social-badge-%1$s"><span class="si si-%1$s"></span>%2$s</a> ', esc_attr( $provider->get_id() ), esc_html( $provider->get_title() ) ); ?>
				</td>
				<td data-title="<?php _e( 'Account', WC_Social_Login::TEXT_DOMAIN ); ?>">
					<?php
						/**
						 * Filter the profile identifier displayed to the user.
						 *
						 * @since 1.0
						 * @param string $profile_identifier See https://github.com/opauth/opauth/wiki/Opauth-configuration - Strategy
						 */
						echo apply_filters( 'wc_social_login_profile_identifier', $profile->has_email() ? $profile->get_email() : $profile->get_nickname() );
					?>
				</td>
				<td data-title="<?php _e( 'Last login', WC_Social_Login::TEXT_DOMAIN ); ?>">
					<?php if ( $login_timestamp ) : ?>
						<?php printf( __( '%s @ %s', WC_Social_Login::TEXT_DOMAIN ), date_i18n( wc_date_format(), $login_timestamp ), date_i18n( wc_time_format(), $login_timestamp ) ); ?>
					<?php else : ?>
						<?php echo __( 'Never', WC_Social_Login::TEXT_DOMAIN ); ?>
					<?php endif; ?>
				</td>
				<td class="profile-actions">
					<a href="<?php echo $provider->get_auth_url( $return_url, 'unlink' ); ?>" class="button unlink-social-login-profile">
						<?php _e( 'Unlink', WC_Social_Login::TEXT_DOMAIN ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>

		</table>

	<?php else : ?>

		<p><?php printf( __( 'You have no social login profiles connected. %sConnect one now%s', WC_Social_Login::TEXT_DOMAIN ), '<a href="#" class="js-show-available-providers">', '</a>' ); ?></p>

	<?php endif; ?>

	<div class="wc-social-login-available-providers" style="display:none;">

		<p><?php _e( 'You can link your account to the following providers:', WC_Social_Login::TEXT_DOMAIN ); ?></p>

		<?php woocommerce_social_login_link_account_buttons(); ?>

	</div>

</div>

<?php
