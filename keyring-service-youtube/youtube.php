<?php
/**
 * YouTube Public API Keyring
 */

// Always extend Keyring_Service, or something else which extends it (e.g. Keyring_Service_OAuth1)
class Keyring_Service_YouTube extends Keyring_Service_HTTP_Basic {
  const NAME = 'youtube';
  const LABEL = 'YouTube';

  function __construct() {
    // If you need a custom __construct(), make sure to call the parent explicitly like this
    
    parent::__construct();
    // Optionally make this a service that we can communicate with *without*
    // requiring any sort of connection
    $this->requires_token( false );
    
    // Optionally register methods (of this object) to handle the UI for different actions
    // action is in the format "keyring_{$service}_{request/verify}_ui".
    // These are optional, and are only required if you need the user to see/do something during
    // each step.
    // add_action( 'keyring_youtube_request_ui', array( $this, 'request_ui' ) );
    // add_action( 'keyring_youtube_verify_ui', array( $this, 'verify_ui' ) );
    $kr_nonce = wp_create_nonce( 'keyring-verify' );
    $nonce    = wp_create_nonce( 'keyring-verify-youtube' );
    // Enable "basic" UI for entering key/secret, which a lot of services require
    add_action( 'keyring_youtube_manage_ui', array( $this, 'basic_ui' ) );
    $this->redirect_uri = Keyring_Util::admin_url( self::NAME, array( 'action' => 'verify', 'kr_nonce' => $kr_nonce, 'nonce' => $nonce) );

  }
  
  /**
   * Allows you to do things before any output has been sent to the browser.
   * This means you can redirect to a remote site, another page etc if need be.
   */
  function request_token() {
    wp_safe_redirect($this->redirect_uri);
  }


  function is_connected() {
    return true;
  }

  function _get_credentials() {
    if (
      defined( 'KEYRING__YOUTUBE_KEY' )
    ) {
      return array(
        'app_id' => '',
        'key'    => constant( 'KEYRING__YOUTUBE_KEY' ),
        'secret' => '',
      );
    } else {
      $all = apply_filters( 'keyring_credentials', get_option( 'keyring_credentials' ) );
      if ( !empty( $all['youtube'] ) ) {
        $creds = $all['youtube'];
        $creds['key'] = $creds['key'];
        return $creds;
      }

      // Return null to allow fall-thru to checking generic constants + DB
      return null;
    }
  }

  function is_configured() {
    $credentials = $this->get_credentials();
    return !empty( $credentials['key'] );
  }

  /**
   * You can define how a token presents itself to the user here. For youtube for Twitter,
   * we might show "@" . $screen_name.
   *
   * @param Keyring_Access_Token $token
   * @return String for use in UIs etc that helps identify this specific token
   */
  function get_display( Keyring_Access_Token $token ) {
    return $token->token;
  }

  function basic_ui_intro() {
		echo '<p>' . __( "If you haven't already, you'll need to request an API Key from YouTube:", 'keyring' ) . '</p>';
		echo '<p>' . __( "Follow the 'Getting Started' guide on <a href='https://developers.google.com/youtube/v3/getting-started'>https://developers.google.com/youtube/v3/getting-started</a> to finish the rest of these steps.", 'keyring' ) . '</p>';
		echo '<ol>';
		echo '<li>' . __( "Visit the <a href='https://cloud.google.com/console/project'>Google Cloud Console</a> and click 'Create Project'.", 'keyring' ) . '</li>';
		echo '<li>' . __( "Enter a name for your project (ie. My Example Site) and a unique id. (ie. 'my-example-site') and hit 'Create'", 'keyring' ) . '</li>';
		echo '<li>' . __( "After it finishes creating your new project, click APIs &amp; auth and click off the default APIs and scroll to the bottom of the page and turn on the 'YouTube Data API v3'", 'keyring' ) . '</li>';
		echo '<li>' . __( "Now click 'Credentials' on the left.", 'keyring' ) . '</li>';
		echo '<li>' . __( "Since we're only accessing the publicly accessible YouTube data, all we need is to click 'CREATE NEW KEY' under Public API Access.", 'keyring' ) . '</li>';
		echo '<li>' . __( "Select 'Browser Key' and leave the big field blank and hit 'Create'.", 'keyring' ) . '</li>';
		echo '</ol>';
		echo '<p>' . __( "Once you're done configuring your app, copy and paste your <strong>API Key</strong> into the field below.", 'keyring' ) . '</p>';
	}

  function build_token_meta( $token ) {
    $this->set_token(
      new Keyring_Access_Token(
        $this->get_name(),
        $token['access_token'],
        array()
      )
    );

    $response = array();
    
    //if ( Keyring_Util::is_error( $response ) ) {
    //  $meta = array();
    //} else {
      $meta = array(
        'username' => 'N/A',
        'user_id'  => 'N/A',
        'name'     => 'N/A',
        'picture'  => "http://www.nacc.org/images/vision/transparent-YouTube-logo-icon1.png",
      );
    //}

    return apply_filters( 'keyring_access_token_meta', $meta, 'youtube', $token, $response, $this );
  }
  function request( $url, array $params = array() ) {
    if ( $this->requires_token() && empty( $this->token ) )
      return new Keyring_Error( 'keyring-request-error', __( 'No token' ) );

    if ( $this->requires_token() )
      $params['headers'] = array( 'Authorization' => 'Basic ' . $this->token );

    $method = 'GET';
    if ( isset( $params['method'] ) ) {
      $method = strtoupper( $params['method'] );
      unset( $params['method'] );
    }

    $raw_response = true;
    $params['raw_response'] = true;
    // if ( isset( $params['raw_response'] ) ) {
    //   $raw_response = (bool) $params['raw_response'];
    //   unset( $params['raw_response'] );
    // }

    Keyring_Util::debug( "HTTP Basic $method $url" );
    Keyring_Util::debug( $params );

    switch ( strtoupper( $method ) ) {
    case 'GET':
      $res = wp_remote_get( $url, $params );

      break;

    case 'POST':
      $res = wp_remote_post( $url, $params );
      break;

    default:
      Keyring::error( __( 'Unsupported method specified for verify_token.', 'keyring' ) );
      exit;
    }

    Keyring_Util::debug( $res );
    $this->set_request_response_code( wp_remote_retrieve_response_code( $res ) );
    if ( 200 == wp_remote_retrieve_response_code( $res ) || 201 == wp_remote_retrieve_response_code( $res ) ) {
      if ( $raw_response )
        return wp_remote_retrieve_body( $res );
      else
        return $this->parse_response( wp_remote_retrieve_body( $res ) );
    } else {
      return new Keyring_Error( 'keyring-request-error', $res );
    }
  }
  function basic_ui() {
    if ( !isset( $_REQUEST['nonce'] ) || !wp_verify_nonce( $_REQUEST['nonce'], 'keyring-manage-' . $this->get_name() ) ) {
      Keyring::error( __( 'Invalid/missing management nonce.', 'keyring' ) );
      exit;
    }

    // Common Header
    echo '<div class="wrap">';
    screen_icon( 'ms-admin' );
    echo '<h2>' . __( 'Keyring Service Management', 'keyring' ) . '</h2>';
    echo '<p><a href="' . Keyring_Util::admin_url( false, array( 'action' => 'services' ) ) . '">' . __( '&larr; Back', 'keyring' ) . '</a></p>';
    echo '<h3>' . sprintf( __( '%s API Credentials', 'keyring' ), esc_html( $this->get_label() ) ) . '</h3>';

    // Handle actually saving credentials
    if ( isset( $_POST['api_key'] ) && isset( $_POST['api_key'] ) ) {
      // Store credentials against this service
      $this->update_credentials( array(
        //'app_id' => stripslashes( $_POST['app_id'] ),
        'key'    => stripslashes( $_POST['api_key'] )
        //'secret' => stripslashes( $_POST['api_secret'] )
      ) );
      echo '<div class="updated"><p>' . __( 'Credentials saved.', 'keyring' ) . '</p></div>';
    }

    $app_id = $api_key = $api_secret = '';
    if ( $creds = $this->get_credentials() ) {
      //$app_id     = $creds['app_id'];
      $api_key    = $creds['key'];
      //$api_secret = $creds['secret'];
    }

    echo apply_filters( 'keyring_' . $this->get_name() . '_basic_ui_intro', '' );

    // Output basic form for collecting key/secret
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="service" value="' . esc_attr( $this->get_name() ) . '" />';
    echo '<input type="hidden" name="action" value="manage" />';
    wp_nonce_field( 'keyring-manage', 'kr_nonce', '1234' );
    wp_nonce_field( 'keyring-manage-' . $this->get_name(), 'nonce', '1234' );
    echo '<table class="form-table">';
    //echo '<tr><th scope="row">' . __( 'App ID', 'keyring' ) . '</th>';
    //echo '<td><input type="text" name="app_id" value="' . esc_attr( $app_id ) . '" id="app_id" class="regular-text"></td></tr>';
    echo '<tr><th scope="row">' . __( 'API Key', 'keyring' ) . '</th>';
    echo '<td><input type="text" name="api_key" value="' . esc_attr( $api_key ) . '" id="api_key" class="regular-text"></td></tr>';
    //echo '<tr><th scope="row">' . __( 'API Secret', 'keyring' ) . '</th>';
    //echo '<td><input type="text" name="api_secret" value="' . esc_attr( $api_secret ) . '" id="api_secret" class="regular-text"></td></tr>';
    echo '</table>';
    echo '<p class="submitbox">';
    echo '<input type="submit" name="submit" value="' . __( 'Save Changes', 'keyring' ) . '" id="submit" class="button-primary">';
    echo '<a href="' . esc_url( $_SERVER['HTTP_REFERER'] ) . '" class="submitdelete" style="margin-left:2em;">' . __( 'Cancel', 'keyring' ) . '</a>';
    echo '</p>';
    echo '</form>';
    ?><script type="text/javascript" charset="utf-8">
      jQuery( document ).ready( function() {
        jQuery( '#api_key' ).focus();
      } );
    </script><?php
    echo '</div>';
  }

  /**
   * Allows you to do things before any output has been sent to the browser.
   * This means you can redirect to a remote site, another page etc if need be.
   */
  function verify_token() {
    // Generate a fake token and store it for this youtube
    $token = sha1( time() . mt_rand( 0, 1000 ) . time() );
    //$meta = array('type' => 'access', 'time' => time(), 'user' => get_current_user() );
    //$this->store_token( $token, $meta );

    $meta = $this->build_token_meta( $token );

    $access_token = new Keyring_Access_Token(
      $this->get_name(),
      $token,
      $meta
    );
    
    $access_token = apply_filters( 'keyring_access_token', $access_token );

    // If we didn't get a 401, then we'll assume it's OK
    $id = $this->store_token( $access_token );
    $this->verified( $id, $keyring_request_token );
    
  }
}

// Always hook into keyring_load_services and use your init method to initiate a Service properly (singleton)
add_action( 'keyring_load_services', array( 'Keyring_Service_YouTube', 'init' ) );
