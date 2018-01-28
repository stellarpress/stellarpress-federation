<?php
/**
 * Plugin Name: StellarPress Federation
 * Plugin URI: https://stellarpress.org/federation
 * Description: This plugin implements a simple Stellar Federation 
 *    server within WordPress
 * Version: 0.1.0
 * Author: StellarPress
 * Author URI: https://stellarpress.org
 * License: GPL2 or later
 */

// Register at the very beginning: catch URLs for the Federation protocol
add_action('plugins_loaded', 'stellarpress_federation');
// Register for profile changes to allow Stellar address
add_action( 'show_user_profile', 'stellarpress_profile' );
add_action( 'edit_user_profile', 'stellarpress_profile' );
add_action( 'personal_options_update', 'stellarpress_profile_save' );
add_action( 'edit_user_profile_update', 'stellarpress_profile_save' );


/**
 * This function implements both the stellar.toml file and the
 * Federation server protocol.
 *
 * It is to be called early in the WordPress process, and will
 * look for very specific URLs ('/.well-known', '.federation').
 *
 * 1. Check whether we shall return the 'stellar.toml' file
 * More details:
 * https://www.stellar.org/developers/guides/concepts/stellar-toml.html
 * 2. Check whether we shall answer on Federation requests
 * More details:
 * https://www.stellar.org/developers/guides/concepts/federation.html
 */
function stellarpress_federation() {
  $current_uri = strtok($_SERVER['REQUEST_URI'], '?');
  // 1. check for stellar.toml
  if ($current_uri == '/.well-known/stellar.toml') {
    // allow CORS as required by Stellar protocol
    header('Access-Control-Allow-Origin: *');
    // use mime type 'text/toml'
    header('Content-Type: text/toml');
    // Output us as the Federation server
    $federation_url = get_site_url(null, '.federation', 'https');
    echo ("# StellarPress Federation\n");
    echo ("FEDERATION_SERVER = \"$federation_url\"\n");
    // We are done - say goodbye
    exit(200);
  }
  // 2. check for Federation requests
  if ($current_uri == '/.federation') {
    // allow CORS as required by Stellar Federation protocol
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    $rc = array();
    $code = 200;
    if (!isset($_GET['type']) || !isset($_GET['q'])) {
      $rc['code'] = "invalid_request";
      $rc['message'] = "both q and type parameter are required";
      $code = 400;
    }
    else {
      $type = $_GET['type'];
      $q = $_GET['q'];
      // name = lookup
      if ($type == 'name') {
        // split name
        $parts = explode('*', $q);
        if (count($parts) != 2 || $parts[0] === '' || $parts[1] === '') {
          $rc['code'] = "invalid_query";
          $rc['message'] = "Please use an address of the form name*domain.com";
          $code = 400;
        }
        else {
          $name = strtolower($parts[0]);
          $domain = strtolower($parts[1]);
          // the domain must be in the site_url
          $site_domain = strtolower(parse_url(site_url())['host']);
          $domain_len = strlen($domain);
          if (substr($site_domain, -$domain_len) !== $domain) {
            $rc['code'] = "not_found";
            $rc['message'] = "Account not found";
            $code = 404;
          }
          else {
            $user_query = new WP_User_Query(
              array(
                'search' => $name,
                'search_columns' => array('user_login', 'user_email'),
                'fields' => 'all'
              )
            );
            $code = 404;
            foreach($user_query->get_results() as $user) {
              if ($user->user_email === $name ||
                $user->user_login === $name) {
                $stellar_address = get_user_meta($user->ID, 'stellar_address');
                if (count($stellar_address) > 0) {
                  $rc['account_id'] = $stellar_address[0];
                  $rc['stellar_address'] = "$name*$domain";
                  $code = 200;
                  break;
                }
              }
            }
            if ($code === 404) {
              $rc['code'] = "not_found";
              $rc['message'] = "Account not found";
            }
          }
        }
      }
      // other operations not implemented
      else {
        $rc['code'] = "not_implemented";
        $rc['message'] = "This operation is not implemented. Only type=name is supported.";
        $code = 501;
      }
    }
    echo json_encode($rc);
    exit($code);
  }
}

/**
 * This function adds a field for the Stellar address of a user
 * to the profile form.
 * Based on https://wordpress.stackexchange.com/a/214723
 * @param user a WPUser object
 * @return void
 */

function stellarpress_profile($user) {
  $stellar_address = get_user_meta($user->ID, 'stellar_address');
  if (count($stellar_address) > 0) {
    $stellar_address = $stellar_address[0];
  }
  else {
    $stellar_address = "";
  }
  ?>
  <h3><?php _e("Stellar Federation", "blank"); ?></h3>

  <table class="form-table">
    <tr>
      <th><label for="stellar_address"><?php _e("Stellar Address"); ?></label></th>
      <td>
        <input type="text" name="stellar_address" id="stellar_address" value="<?php echo esc_attr($stellar_address); ?>" class="regular-text" /><br />
        <span class="description"><?php _e("Please enter your Stellar address (your public key, starting with 'G')."); ?></span>
      </td>
    </tr>
  </table>
  <?php
}

/**
 * This function adds a field for the Stellar address of a user
 * into the database after being saved by the user.
 * Based on https://wordpress.stackexchange.com/a/214723
 * @param user_id a WordPress user id
 * @return void
 */

function stellarpress_profile_save($user_id) {
  if (!current_user_can('edit_user', $user_id)) { 
    return false; 
  }
  update_user_meta($user_id, 'stellar_address', $_POST['stellar_address']);
}
?>
