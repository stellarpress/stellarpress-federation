<?php
/**
 * Plugin Name: StellarPress Federation
 * Plugin URI: https://stellarpress.org/federation/
 * Description: This plugin implements a simple Stellar Federation server within WordPress. Stellar address can be specified in the user profile. <em>This requires a blog hosted via HTTPS and in the root folder of the server!</em>
 * Version: 1.0
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
  define('STELLARPRESS_FEDERATION_VERSION', '1.0');
}

class StellarPress_Federation {
  /**
   * constants for return HTTP status codes
   */
  const HTTP_OK = 200;
  const HTTP_NOTFOUND = 404;
  const HTTP_INVALID = 400;
  const HTTP_NOTIMPL = 501;

  /**
   * constants for return messages in Federation server
   */
  const CODE_NOTFOUND = "not_found";
  const CODE_INVALID = "invalid_request";
  const CODE_NOTIMPL = "not_implemented";

  /**
   * Static property to hold the singleton
   */
  static $instance = false;
  
  /**
   * Constructor: register all hooks needed
   *
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
  }

  /**
   * Return the (one) instance of this class. If no instance exists
   * create one.
   *
   * @return StellarPress_Federation
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
   * look for very specific URLs ('/.well-known', '.federation').
   *
   * 1. Check whether we shall return the 'stellar.toml' file
   * More details:
   * https://www.stellar.org/developers/guides/concepts/stellar-toml.html
   * 2. Check whether we shall answer on Federation requests
   * More details:
   * https://www.stellar.org/developers/guides/concepts/federation.html
   *
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
   * @return Boolean true if the domain name is matching the server
   */
  function is_domain_ok($domain) {
    // the domain must be in the site_url
    $site_domain = strtolower(parse_url(site_url())['host']);
    $domain_len = strlen($domain);
    return (substr($site_domain, -$domain_len) === $domain);
  }

  /**
   * This helper finds a user with a specific name & domain.
   * 
   * @param name the name of the user
   * @return String the stellar address, or false if not found
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
   * @param user a user-id string for which the stellar address shall be extracted
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
   * @param code    the HTTP status code to be used
   * @param message text message as part of the result
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
   * @param account the Stellar account from the profile
   * @param name    the name part of the Stellar address
   * @param domain  the domain part of the Stellar address
   * @return void
   */
  function exit_federation_result($account, $name, $domain) {
    $rc = array();
    $rc['account_id'] = $account;
    $rc['stellar_address'] = "$name*$domain";
    $this->exit_federation($rc, self::HTTP_OK);
  }

  /**
   * This helper will send the JSON result and exit execution.
   * It will also set the required headers for the Federation.
   *
   * @param rc   an associative array to be sent as JSON
   * @param code the HTTP status code to be used
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
   * Based on https://wordpress.stackexchange.com/a/214723
   * @param user a WPUser object
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
  function save_profile($user_id) {
    if (!current_user_can('edit_user', $user_id)) { 
      return false; 
    }
    update_user_meta($user_id, 'stellar_address', $_POST['stellar_address']);
  }
}


// instantiate our class
$StellarPress_Federation = StellarPress_Federation::getInstance();
