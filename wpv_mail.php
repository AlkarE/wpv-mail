<?php
/*
Plugin Name: Wpv mail
Plugin URI: 
Description: get form data, validate, send  email
Version: 1.0
Author: Alkar. E. 
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} 

if(!valid_wp_core()) {
	wp_die( __('This plugin requires WP version greater/equal 4.7') );
}
$wpv_error = array();
$wpv_error['target_email'] = '';

add_action('admin_menu', 'wpv_add_page');
function wpv_add_page(){
	add_options_page('WPV Settings', 'WPV Settings', 'manage_options', 'wpv_menu', 'wpv_settings_page');
}

function wpv_settings_page() {

	if (!current_user_can('manage_options'))
	{
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	$opt_name = 'wpv_target_email';
	$hidden_field_name = 'wpv_submit_hidden';
	$data_field_name = 'recipient_email';

	$target_email = get_option( $opt_name );
	
	if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
		$target_email = trim($_POST[ $data_field_name ]);
		if( valid_email($target_email) ) {
			update_option( $opt_name, $target_email );
			$wpv_error['target_email'] = 0;
		} else {
			$wpv_error['target_email'] = 1;
		};


		if( !empty($wpv_error['target_email'] )) :; ?>
		<div class="notice notice-error is-dismissible">
			<p>Please check entered email.</p>
		</div>
	<?php else :; ?>
		<div class="updated"><p><strong><?php _e('settings saved.' ); ?></strong></p></div>
		
	<?php endif; ?>
	<?php
}
echo '<div class="wrap">';
echo "<h2>" . __( 'WPV Settings' ) . "</h2>";    
?>

<form name="wpv_form" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

	<p><?php _e("Enter recipient email:" ); ?> 
		<input type="text" name="<?php echo $data_field_name; ?>" value="<?php echo $target_email; ?>" size="20">
	</p><hr />

	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
	</p>
</form>
</div>
<?php }


add_action( 'rest_api_init', function(){
	$route 		= 'wpvform/v';
	$version  = '2';
	$base     = 'wpvdata';
	register_rest_route( $route . $version, '/' . $base, array(
		'methods' => 'POST',
		'callback' => 'get_wpv_data',
		'args'     => array()
		) );
} );

function get_wpv_data( WP_REST_Request $request ){

	$success = array(
		'code'=>'WPV data',
		'message'=> 'Sucess',
		'data'=> array('status'=>200)
	); 

	$wpv_data = json_decode($request->get_body());

	if( !is_object($wpv_data)  ){
		return new WP_Error( 'WPV data', 'Wrong format', array( 'status' => 500 ) );
	}

	if( !isset($wpv_data) ) {
		return new WP_Error( 'WPV data', 'Data missing', array( 'status' => 500 ) );
	}	

	$verification = wpv_check_data($wpv_data);
	if( empty($verification) ){
		$is_sent = wpv_send_mail($wpv_data);
		if( $is_sent ){
			return new WP_REST_Response( $success );
		} else {
			return new WP_Error( 'WPV data', 'Mail delivery failed', array( 'status' => 500 ) );
		}
	} else {
		return new WP_Error( 'WPV data', $verification, array( 'status' => 400 ) );
	}
} 

function wpv_check_data($obj){
	$errors = array();
	if( !valid_name($obj -> name) )
		$errors[] = 'Name';
	if( !valid_name($obj -> surname) )
		$errors[] = 'Surname';
	if( !valid_email($obj -> email) )
		$errors[] = 'email';
	if( !valid_message($obj -> message) )
		$errors[] = 'message';
  return $errors;	
}

function wpv_send_mail($obj){
	
	$subject = '';
	$headers = '';
	$from_email = '';
	$target_email = get_option( 'wpv_target_email' );
	$name = ucfirst(trim($obj->name));
	$surname = ucfirst(trim($obj->surname));
	$subject .= 'Letter from ' . $name . ' ' . $surname;
	$message_body = esc_html(trim($obj->message));
	$message = 'Message from: ' . $name .' '. $surname . '<' . $obj->email . '>' ."\r\n";
	$message .= 'Message body: ' . $message_body;

	return wp_mail($target_email, $subject, $message);	
	}

function valid_name($name){
	$pattern = '/^(\w|\s){3,12}$/';
	if( preg_match($pattern, trim($name)) )
		return true;
	return false;
}

function valid_message($text){
	$pattern = '/^(\w|\W){3,}$/';
	if( preg_match($pattern, trim($text)) )
		return true;
	return false;
}

function valid_email($email) 
{
	if(is_array($email) || is_numeric($email) || is_bool($email) || is_float($email) || is_file($email) || is_dir($email) || is_int($email))
		return false;
	else
	{
		$email=trim(strtolower($email));
		if(filter_var($email, FILTER_VALIDATE_EMAIL)!==false) return $email;
		else
		{
			$pattern = '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD';
			return (preg_match($pattern, $email) === 1) ? $email : false;
		}
	}
}

function valid_wp_core(){
	$version = get_bloginfo('version');
	if ($version < 4.7) {
	return false;
	} else {
	return true;
	};
}

function uninstall_wpv_mail(){
	delete_option('wpv_target_email');
}

register_uninstall_hook(__FILE__, 'uninstall_wpv_mail');

?>