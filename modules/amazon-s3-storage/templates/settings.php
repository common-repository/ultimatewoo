<form method="post" id="mainform" action="">
    <h2><?php _e( 'Amazon S3 Storage', 'wc_amazon_s3' ); ?></h2>
    <h3><?php _e( 'Security Credentials', 'wc_amazon_s3'); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <th scope="row" class="titledesc"><?php _e( 'Access Key ID', 'wc_amazon_s3' ); ?></th>
            <td class="forminp"><input name="woo_amazon_access_key" id="woo_amazon_access_key" type="text" style="min-width:300px;" value="<?php echo $admin_options['amazon_access_key']; ?>"><span class="description"><?php _e( 'Your Amazon Web Services access key id.', 'wc_amazon_s3'); ?></span></td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php _e( 'Secret Access Key', 'wc_amazon_s3' ); ?></th>
            <td class="forminp"><input name="woo_amazon_access_secret" id="woo_amazon_access_secret" type="text" style="min-width:300px;" value="<?php echo $admin_options['amazon_access_secret']; ?>"><span class="description"><?php _e( 'Your Amazon Web Services secret access key.', 'wc_amazon_s3'); ?></span></td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php _e( 'Disable SSL Verification', 'wc_amazon_s3' ); ?></th>
            <td class="forminp">
                <input id="woo_amazon_disable_ssl" type="checkbox" <?php checked( $admin_options['amazon_disable_ssl'], '1' ); ?> name="woo_amazon_disable_ssl" />
                <span class="description"><?php _e( 'Disable SSL certificate verification to the Amazon S3 service.', 'wc_amazon_s3'); ?></span>
            </td>
        </tr>
		<tr valign="top">
            <th scope="row" class="titledesc"><?php _e( 'HTTPS File Serving', 'wc_amazon_s3' ); ?></th>
            <td class="forminp">
                <input id="woo_amazon_https_downloads" type="checkbox" <?php checked( $admin_options['amazon_https_downloads'], '1' ); ?> name="woo_amazon_https_downloads" />
                <span class="description"><?php _e( 'Serve downloads via a https url.', 'wc_amazon_s3'); ?></span>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php _e( 'URL Valid Period', 'wc_amazon_s3' ); ?></th>
            <td class="forminp">
                <input name="woo_amazon_url_period" id="woo_amazon_url_period" type="text" style="min-width:100px;" value="<?php echo $admin_options['amazon_url_period']; ?>">
                <span class="description"><?php _e( 'Time in minutes the URL are valid for downloading, leave blank to disable. Setting this will prevent Amazon S3 link sharing.', 'wc_amazon_s3'); ?></span>
            </td>
        </tr>
    </table>
    <p class="submit"><input name="save" class="button-primary" type="submit" value="<?php _e( 'Save changes', 'wc_amazon_s3' ); ?>" /></p>
</form>