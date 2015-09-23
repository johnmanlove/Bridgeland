<?php
/**
* Code Embed Options
*
* Allow the user to change the default options
*
* @package	Artiss-Code-Embed
* @since	1.4
*
* @uses	ace_get_embed_paras	Get the options
* @uses ace_help			Return help text
*/
?>
<div class="wrap">
<?php
global $wp_version;
if ( ( float ) $wp_version >= 4.3 ) { $heading = '1'; } else { $heading = '2'; }
?>
<h<?php echo $heading; ?>><?php _e( 'Code Embed Options', 'simple-embed-code' ); ?></h<?php echo $heading; ?>>
<?php
// If options have been updated on screen, update the database
if ( ( !empty( $_POST ) ) && ( check_admin_referer( 'code-embed-profile' , 'code_embed_profile_nonce' ) ) ) {

	// Update the options array from the form fields. Strip invalid tags.
	$options[ 'opening_ident' ] = strtoupper( trim( $_POST[ 'code_embed_opening' ], '[]<>' ) );
	$options[ 'keyword_ident' ] = strtoupper( trim( $_POST[ 'code_embed_keyword' ], '[]<>' ) );
	$options[ 'closing_ident' ] = strtoupper( trim( $_POST[ 'code_embed_closing' ], '[]<>' ) );
	$options[ 'debug' ] = $_POST[ 'code_embed_debug' ];

	// If any fields are blank assign default values
	if ( $options[ 'opening_ident' ] == '' ) { $options[ 'opening_ident' ] = '%'; }
	if ( $options[ 'keyword_ident' ] == '' ) { $options[ 'keyword_ident' ] = 'CODE'; }
	if ( $options[ 'closing_ident' ] == '' ) { $options[ 'closing_ident' ] = '%'; }

    update_option( 'artiss_code_embed', $options );
}

// Fetch options into an array
$options = ace_get_embed_paras();
?>

<form method="post" action="<?php echo get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=ace-options&amp;updated=true' ?>">

<table class="form-table">
<tr>
<th scope="row"><?php _e( 'Hide Debug', 'simple-embed-code' ); ?></th>
<td><input type="checkbox" name="code_embed_debug" value="1"<?php if ( $options[ 'debug' ] == "1" ) { echo ' checked="checked"'; } ?>/>&nbsp;<span class="description"><?php _e( 'Hide debug HTML comments in source.', 'simple-embed-code' ); ?></span></td>
</tr>
</table>

<?php echo '<h3>' . __( 'Identifier Format', 'simple-embed-code' ) . '</h3>' . __( 'Specify the format that will be used to define the way the code is embedded in your post. The formats are case insensitive and characters &lt; &gt [ ] are invalid.', 'simple-embed-code' ); ?>

<table class="form-table">

<tr>
<th scope="row"><?php _e( 'Keyword', 'simple-embed-code' ); ?></th>
<td><input type="text" size="12" maxlength="12" name="code_embed_keyword" value="<?php echo $options[ 'keyword_ident' ] ; ?>"/>&nbsp;<span class="description"><?php _e( 'The keyword that is used to name the custom field and then place in your post where the code should be embedded. A suffix on any type can then be placed on the end.', 'simple-embed-code' ); ?></span></td>
</tr>

<tr>
<th scope="row"><?php _e( 'Opening Identifier', 'simple-embed-code' ); ?></th>
<td><input type="text" size="4" maxlength="4" name="code_embed_opening" value="<?php echo $options[ 'opening_ident' ]; ?>"/>&nbsp;<span class="description"><?php _e( 'The character(s) that must be placed in the post before the keyword to uniquely identify it.', 'simple-embed-code' ); ?></span></td>
</tr>

<tr>
<th scope="row"><?php _e( 'Closing Identifier', 'simple-embed-code' ); ?></th>
<td><input type="text" size="4" maxlength="4" name="code_embed_closing" value="<?php echo $options[ 'closing_ident' ]; ?>"/>&nbsp;<span class="description"><?php _e( 'The character(s) that must be placed in the post after the keyword to uniquely identify it.', 'simple-embed-code' ); ?></span></td>
</tr>

</table>

<?php wp_nonce_field( 'code-embed-profile', 'code_embed_profile_nonce', true, true ); ?>

<br/><input type="submit" name="Submit" class="button-primary" value="<?php _e( 'Save Settings', 'simple-embed-code' ); ?>"/>

</form>

<?php

// How to embed

echo "<br/><h3>" . __( 'How to Embed', 'simple-embed-code' ) . "</h3>\n";
echo '<p>' . sprintf ( __( 'Based upon your current settings to embed some code simply add a custom field named %s, where %s is any suffix you wish. The code to embed is then added as the field value.', 'simple-embed-code' ), '<strong>' . $options[ 'keyword_ident' ] . 'x</strong>', '<strong>x</strong>' ) . "</p>\n";
echo '<p>' . sprintf ( __( 'Then, to add the code into your post simple add %s where you wish it to appear. %s is the suffix you used for the custom field name.', 'simple-embed-code' ), '<strong>' . $options[ 'opening_ident' ] . $options[ 'keyword_ident' ] . "x" . $options[ 'closing_ident' ] . '</strong>', '<strong>x</strong>' ) . "</p>\n";
echo '<p>' . sprintf ( __( 'For example, I may add a custom field named %s, where the value is the code I wish to embed. I would then in my post add %s where I wish the code to then appear.', 'simple-embed-code' ), '<strong>' . $options[ 'keyword_ident' ].'1</strong>', '<strong>' . $options[ 'opening_ident' ] . $options[ 'keyword_ident' ] . "1" . $options[ 'closing_ident' ] . '</strong>' ) . "</p>\n";
echo '<p>' . sprintf ( __( 'To embed the same code but to make it responsive you would use %s. To set a maximum width you would use %s, where %s is the maximum width in pixels.', 'simple-embed-code' ), '<strong>' . $options[ 'opening_ident' ] . $options[ 'keyword_ident' ] . "x_RES" . $options[ 'closing_ident' ] . '</strong>', '<strong>' . $options[ 'opening_ident' ] . $options[ 'keyword_ident' ] . "x_RES_y" . $options[ 'closing_ident' ] . '</strong>', '<strong>y</strong>' ) . "</p>\n";
echo '<p>' . sprintf ( __( 'To embed an external URL you would type %s, where %s is the URL.', 'simple-embed-code' ), '<strong>' . $options[ 'opening_ident' ] . 'url' . $options[ 'closing_ident' ] . '</strong>', '<strong>url</strong>' ) . "</p>\n";
?>

</div>