<?php
/**
 * Plugin Name: StellarPress Federation
 * Plugin URI: https://stellarpress.org/federation/
 * Description: This plugin implements a simple Stellar Federation server within WordPress. Stellar address can be specified in the user profile. <em>This requires a blog hosted via HTTPS and in the root folder of the server!</em>
 * Version: 1.1
 * Author: StellarPress
 * Author URI: https://stellarpress.org
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * StellarPress Federation
 * Copyright (C) 2018, Helmuth Breitenfellner
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STELLARPRESS_FEDERATION_VERSION')) {
  define('STELLARPRESS_FEDERATION_VERSION', '1.1');
}

/**
 * Class containing all functions for the StellarPress plugin,
 * both admin UI and runtime functionality.
 * 
 * @since 1.0.0
 */
class StellarPress_Federation {
  /**
   * Constants for return HTTP status codes.
   * 
   * @since 1.0.0
   * @var int HTTP_OK        HTTP status for everything OK.
   * @var int HTTP_NOTFOUND  HTTP status for item not found.
   * @var int HTTP_INVALID   HTTP status for invalid request.
   * @var int HTTP_NOTIMPL   HTTP status for not (yet) implemented.
   */
  const HTTP_OK = 200;
  const HTTP_NOTFOUND = 404;
  const HTTP_INVALID = 400;
  const HTTP_NOTIMPL = 501;

  /**
   * Constants for return messages in Federation server.
   * 
   * @since 1.0.0
   * @var string CODE_NOTFOUND  Text message for HTTP_NOTFOUND.
   * @var string CODE_INVALID   Text message for HTTP_INVALID.
   * @var string CODE_NOTIMPL   Text message for HTTP_NOTIMPL.
   */
  const CODE_NOTFOUND = "not_found";
  const CODE_INVALID = "invalid_request";
  const CODE_NOTIMPL = "not_implemented";

  /**
   * Static property to hold the singleton.
   * 
   * @since 1.0.0
   * @var StellarPress_Federation $instance  The singleton object.
   */
  static $instance = false;
  
  /**
   * Constructor: register all hooks needed.
   *
   * @since 1.0.0
   * @return void
   */
  private function __construct() {
    // Register to catch URLs for the Federation protocol
    add_action('plugins_loaded', array($this, 'federation_server'));
    // Register for profile changes to allow Stellar address
    add_action('show_user_profile', array($this, 'edit_profile'));
    add_action('edit_user_profile', array($this, 'edit_profile'));
    add_action('personal_options_update', array($this, 'save_profile'));
    add_action('edit_user_profile_update', array($this, 'save_profile'));
    // Add scripts for admin user page
    add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
  }

  /**
   * Return the (one) instance of this class. If no instance exists
   * create one.
   *
   * @since 1.0.0
   * @return StellarPress_Federation  The singleton object.
   */
  public static function getInstance() {
    if (!self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * This function implements both the stellar.toml file and the
   * Federation server protocol.
   *
   * It is to be called early in the WordPress process, and will
   * look for very specific URLs (`/.well-known`, `.federation`).
   *
   * 1. Check whether we shall return the `stellar.toml` file
   * 2. Check whether we shall answer on _Federation_ requests
   *
   * @since 1.0.0
   * @link https://stellar.org/developers/guides/concepts/stellar-toml.html
   * @link https://stellar.org/developers/guides/concepts/federation.html
   * @return void
   */
  public function federation_server() {
    $current_uri = strtok($_SERVER['REQUEST_URI'], '?');
    // 1. check for stellar.toml
    if ($current_uri == '/.well-known/stellar.toml') {
      // serve the stellar.toml file
      $this->serve_stellar_toml();
      // exit further execution - do not display the site for this URL
      exit(200);    
    }
    // 2. check for Federation requests
    if (substr($current_uri, 0, 12) == '/.federation') {
      // serve the Federation response
      $this->serve_federation();
    }
    // everything else - let WordPress do its regular work
  }

  /**
   * This helper function delivers the stellar.toml file to the client.
   * 
   * @since 1.0.0
   * @return void
   */
  function serve_stellar_toml() {
    // allow CORS as required by Stellar protocol
    header('Access-Control-Allow-Origin: *');
    // use mime type 'text/toml'
    header('Content-Type: text/toml');
    // Output us as the Federation server
    $federation_url = get_site_url(null, '.federation', 'https');
    echo ("# StellarPress Federation\n");
    echo ("FEDERATION_SERVER = \"$federation_url\"\n");
  }

  /**
   * This helper function delivers the response from the Federation server.
   * It will exit the PHP execution.
   * 
   * @since 1.0.0
   */
  function serve_federation() {
    if (!isset($_GET['type']) || !isset($_GET['q'])) {
      $this->exit_federation_error(self::HTTP_INVALID,
                  "both q and type parameter are required");
    }
    $type = $_GET['type'];
    $q = $_GET['q'];
    // name = lookup
    if ($type == 'name') {
      // split name
      $parts = explode('*', $q);
      if (count($parts) != 2 || $parts[0] === '' || $parts[1] === '') {
        $this->exit_federation_error(self::HTTP_INVALID,
                    "Please use an address of the form name*domain.com");
      }
      $name = strtolower($parts[0]);
      $domain = strtolower($parts[1]);
      if (!$this->is_domain_ok($domain)) {
        $this->exit_federation_error(self::HTTP_NOTFOUND,
                    "Account not found");
      }
      // Find the user's account
      $account = $this->find_user($name);
      if ($account) {
        $this->exit_federation_result($account, $name, $domain);
      }
      else {
        $this->exit_federation_error(self::HTTP_NOTFOUND,
                    "Account not found");
      }
    }
  }

  /**
   * This helper functions checks the domain name against the
   * WordPress site_url.
   * 
   * @since 1.0.0
   * @return boolean  `true` if the domain name is matching the server.
   */
  function is_domain_ok($domain) {
    // the domain must be in the site_url
    $site_domain = strtolower(parse_url(site_url())['host']);
    $domain_len = strlen($domain);
    return (substr($site_domain, -$domain_len) === $domain);
  }

  /**
   * This helper finds a user with a specific name and domain.
   * 
   * @since 1.0.0
   * @param  string $name  The name of the user.
   * @return string|false  The stellar address, or `false` if not found.
   */
  function find_user($name) {
    $user_query = new WP_User_Query(
      array(
        'search' => $name,
        'search_columns' => array('user_login', 'user_email'),
        'fields' => 'all'
      )
    );
    $count = 0;
    $address = false;
    foreach($user_query->get_results() as $user) {
      if ($user->user_email === $name ||
          $user->user_login === $name) {
        $stellar_address = $this->get_stellar_address($user->ID);
        if ($stellar_address) {
          $address = $stellar_address;
          $count++;
        }
      }
    }
    // return the address - if only one user was found
    return ($count === 1) ? $address : false;
  }

  /**
   * This helper function extracts the Stellar account from a WordPress user
   * object.
   * 
   * @since 1.0.0
   * @param  string $user_id  User-id string for which the stellar address
   *                          shall be extracted.
   * @return string           The user's Stellar account.
   */
  function get_stellar_address($user_id) {
    return get_user_meta($user_id, 'stellar_address', true);
  }

  /**
   * This helper function returns an error text with a code to the clients of
   * the Federation server.
   * 
   * This function will exit further execution and set the status code.
   * 
   * @since 1.0.0
   * @param int    $code     The HTTP status code to be used.
   * @param string $message  Text message as part of the result.
   */
  function exit_federation_error($code, $message) {
    switch($code) {
      case self::HTTP_NOTFOUND:
        $rc['code'] = self::CODE_NOTFOUND;
        break;
      case self::HTTP_INVALID:
        $rc['code'] = self::CODE_INVALID;
        break;
      case self::HTTP_NOTIMPL:
        $rc['code'] = self::CODE_NOTIMPL;
        break;
    }
    $rc['message'] = $message;
    $this->exit_federation($rc, $code);
  }

  /**
   * This helper function will return a Stellar Federation response for a user.
   * 
   * It will exit further execution and set the status code.
   * 
   * @since 1.0.0
   * @param string $account  The Stellar account from the profile.
   * @param string $name     The name part of the Stellar address.
   * @param string $domain   The domain part of the Stellar address.
   */
  function exit_federation_result($account, $name, $domain) {
    $rc = array();
    $rc['account_id'] = $account;
    $rc['stellar_address'] = "$name*$domain";
    $this->exit_federation($rc, self::HTTP_OK);
  }

  /**
   * This helper will send the JSON result and exit execution.
   * 
   * It will also set the required headers for the Federation.
   *
   * @since 1.0.0
   * @param array $rc    An associative array to be sent as JSON.
   * @param int   $code  The HTTP status code to be used.
   */
  function exit_federation($rc, $code) {
    // allow CORS as required by Stellar Federation protocol
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rc);
    exit($code);
  }

  /**
   * This function adds a field for the Stellar address of a user
   * to the profile form.
   * 
   * Based on https://wordpress.stackexchange.com/a/214723
   * 
   * @since 1.0.0
   * @param WPUser user  A WPUser object.
   * @return void
   */

  function edit_profile($user) {
    $stellar_address = $this->get_stellar_address($user->ID);
    ?>
    <h3><?php _e("Stellar Federation", "blank"); ?></h3>

    <table class="form-table">
      <tr>
        <th><label for="stellar_address"><?php _e("Stellar Address"); ?></label></th>
        <td>
          <input type="text" name="stellar_address" id="stellar_address" value="<?php echo esc_attr($stellar_address); ?>" class="regular-text" /><br />
          <p class="description"><?php _e("Please enter your Stellar address (your public key, starting with 'G')."); ?></p>
          <p class="status" id="stellarpress-check"></p>
        </td>
      </tr>
    </table>
    <script>
      stellarpressFederation.inform_into("<?php echo esc_attr($user->user_login); ?>", "<?php echo esc_attr($stellar_address); ?>", "stellarpress-check");
    </script>
    <?php
  }

  /**
   * This function adds a field for the Stellar address of a user
   * into the database after being saved by the user.
   * 
   * Based on https://wordpress.stackexchange.com/a/214723
   * 
   * @since 1.0.0
   * @param string $user_id  A WordPress user id
   * @return void
   */
  function save_profile($user_id) {
    if (!current_user_can('edit_user', $user_id)) { 
      return false; 
    }
    update_user_meta($user_id, 'stellar_address', $_POST['stellar_address']);
  }

  /**
   * This function is used to add JS and CSS files needed for
   * the user admin page.
   * 
   * @since 1.1.0
   * @param string $hook  Hook name used to identify admin page
   * @return void
   */
  function add_admin_scripts($hook) {
    if ($hook === 'profile.php' || $hook === 'user-edit.php') {
      wp_enqueue_script('stellarsdk',
        'https://cdnjs.cloudflare.com/ajax/libs/stellar-sdk/0.8.0/stellar-sdk.js');
      wp_enqueue_script('stellarfederation',
        plugins_url('js/stellar.js', __FILE__));
      wp_enqueue_style('stellarcss',
        plugins_url('css/stellar.css', __FILE__));
    }
  }
}


// instantiate our class
$StellarPress_Federation = StellarPress_Federation::getInstance();
