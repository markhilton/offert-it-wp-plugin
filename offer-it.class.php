<?php
/*  Copyright YEAR  PLUGIN_AUTHOR_NAME  (email : PLUGIN AUTHOR EMAIL)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require('php-ga-1.1.1/src/autoload.php');

use UnitedPrototype\GoogleAnalytics;




class offer_it {

	public $wpdb    = null;		// WordPress DB hookup
	public $config  = array(); 	// plugin configuration
	public $options = array(); 	// plugin configuration options 
	

	

	// constructor
	function __construct() {
	
		global $wpdb;
		
		$this->wpdb = $wpdb;
	

		if(is_admin()):
			add_action('admin_menu', array(&$this, 'offer_it_admin_menu'));
			
			
		endif;


		// install WP options
		$this->options = array(
			'domain', 			// offerIT tracking domain
			'username', 		// offerIT username
			'apikey', 			// offerIT API key
			'ga_code', 			// Google Analytics code
			'ga_domain', 		// Google Analytics domain
			'conversion_url', 	// conversion URL to trigger post
			'conversion_ouid', 	// conversion unique variable
			'mapper_get', 		// $_GET filed mapper to offerIT variables
			'mapper_post', 		// $_POST field mapper to offerIT variables
		);

		foreach($this->options as $option)
			$this->config[ $option ] = get_option('offer_it_' . $option);


		// start PHP session if not present
		if(!session_id()) session_start();

	
	    // store GET variables into a SESSION to be able to access later
	    if(isset($_GET['ocode'])   && $_GET['ocode'])   $_SESSION['offer_it']['ocode']   = $_GET['ocode'];
	    if(isset($_GET['transid']) && $_GET['transid']) $_SESSION['offer_it']['transid'] = $_GET['transid'];
	

	    // trigger Google Analytics tracking
	    # $this->offer_it_track_pageview();
	

	    // query offerIT for affiliate details
	    // only if ocode is present in URL string and not already stored in the session
	    if(isset($_GET[ ['ocode'] ]) && $_GET[ ['ocode'] ] && $_GET[ ['ocode'] ] != $_SESSION['offer_it']['ocode']):
			$url      = sprintf('http://%s/admin_api.php?wsdl', $this->config['domain']);
			$client   = new nusoap_client($url, true);
			$data     = array(
				'ts' 		=> date('Y-m-d H:i:s'),
				'request' 	=> $url
			);

			
			$client->setCredentials($this->config['username'], $this->config['apikey']);

			// Check for an API error
			if($error = $client->getError()):
				// store CURL post results in database
				$data['response'] = $error;
		
				$this->wpdb->insert($this->wpdb->prefix . 'offerit_log', $data);

				die('Offer IT plugin API call constructor error' . $error . "\n");
			endif;


		    $values = array('offeritcode' => $_SESSION['offer_it']['code']);
			$_SESSION['offer_it']['decode']    = $client->call('decode_offeritcode', $values, 'offeritapiadmin_wsdl');
			
			$values = array('loginid' => $_SESSION['offer_it']['decode']['loginid']);
			$_SESSION['offer_it']['affiliate'] = $client->call('list_aff_details',   $values, 'offeritapiadmin_wsdl');

			$data['response'] = print_r($_SESSION['offer_it'], true);

			$this->wpdb->insert($this->wpdb->prefix . 'offerit_log', $data);
	    endif;



# $this->debug($this->config); $this->debug($_SESSION, true); // DEBUG

	    // redirect URL to hide OfferIT variables is required
	    # some code here


	    // trigger conversion post if conversion URL string is detected
	    if(strpos($_SERVER['REQUEST_URI'], $this->config['conversion_url']) !== false && isset($_GET[ $this->config['conversion_ouid'] ])):
			// query database for order details by order unique ID
			$query = sprintf('SELECT * FROM %s%s WHERE %s="%s" LIMIT 1', 
					 $this->wpdb->prefix, $this->config['mapper_post']['table'], $this->config['mapper_post']['order'], $_GET[ $this->config['conversion_ouid'] ]);
			$data  = $this->wpdb->get_row($query); # $this->debug($data, true); // DEBUG

			if(is_object($data)) $this->offer_it_conversion($data);
	    endif;
	    
	    
	    return $this;
	}


	
	// plugin installation - add config options to database
	function offer_it_install() {
		// create db log table if does not exist
	    $this->offer_it_create_db_table();
	    
		foreach($this->options as $option)
			add_option( 'offer_it_' . $option, null );

		// default hardcoded Cart66 mappings
		update_option( 'offer_it_mapper_post', 'a:3:{s:5:"table";s:13:"cart66_orders";s:5:"order";s:4:"ouid";s:6:"fields";a:16:{s:9:"firstname";s:15:"bill_first_name";s:8:"lastname";s:14:"bill_last_name";s:8:"address1";s:12:"bill_address";s:8:"address2";s:13:"bill_address2";s:4:"city";s:9:"bill_city";s:5:"state";s:10:"bill_state";s:3:"zip";s:12:"bill_country";s:7:"country";s:8:"bill_zip";s:5:"email";s:5:"email";s:5:"phone";s:5:"phone";s:5:"gross";s:5:"total";s:7:"custom1";s:6:"coupon";s:7:"custom2";s:15:"discount_amount";s:7:"custom3";s:8:"trans_id";s:7:"custom4";s:12:"custom_field";s:7:"custom5";s:17:"additional_fields";}}');
	}



	// plugin removal - remove DB options
	function offer_it_uninstall() {
#		foreach($this->options as $option)
#			delete_option( 'offer_it_' . $option, null );

#		$this->wpdb->query("DROP TABLE IF EXISTS " . $this->wpdb->prefix . 'offerit_log');
	}



	// post conversion pixel
	function offer_it_conversion($data) {
		$mapper    = $this->config['mapper_post']['fields'];
		$post_url  = sprintf('http://%s/signup/process_pixel.php', $this->config['domain']);
		$post_data = array(
			'transid'   => $_SESSION['offer_it']['transid'],			// offerIT transaction ID
			'orderid'   => $_GET[ $this->config['conversion_ouid'] ],	// order ID - has to be unique for OfferIT to accept conversion POST call

			'gross'     => $data->$mapper['gross']    ,					// transaction total
	
			'firstname' => $data->$mapper['firstname'],
			'lastname'  => $data->$mapper['lastname'] ,
			'address1'  => $data->$mapper['address1'] ,
			'address2'  => $data->$mapper['address2'] ,
			'city'      => $data->$mapper['city']     ,
			'state'     => $data->$mapper['state']    ,
			'zip'       => $data->$mapper['zip']      ,
			'country'   => $data->$mapper['country']  ,
	
			'email'     => $data->$mapper['email']    ,
			'phone'     => $data->$mapper['phone']    ,
	
			'custom1'   => $data->$mapper['custom1']  ,
			'custom2'   => $data->$mapper['custom2']  ,
			'custom3'   => $data->$mapper['custom3']  ,
			'custom4'   => $data->$mapper['custom4']  ,
			'custom5'   => $data->$mapper['custom5']  ,
			'ip'     	=> $_SERVER['REMOTE_ADDR']
		);


		// post transactions
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_POST,            true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,  true); 
		curl_setopt($ch, CURLOPT_HEADER,          false); 
		curl_setopt($ch, CURLOPT_URL,             $post_url);
		curl_setopt($ch, CURLOPT_POSTFIELDS,      http_build_query($post_data));
		
		$response = curl_exec($ch); # $this->debug($response, true); 
		
		curl_close($ch);
	

		// store CURL post results in database
		$data = array(
			'ts' 		=> date('Y-m-d H:i:s'),
			'request' 	=> substr(print_r($post_data, true), 6),
			'response' 	=> $response
		);

		$this->wpdb->insert($this->wpdb->prefix . 'offerit_log', $data);
	}
	
	

	// track with Google Analytics
	function offer_it_track_pageview() {
		if(!$this->config['ga_code'] || !$this->config['ga_domain']) return false;
	
		// Initilize GA Tracker
#		$tracker = new GoogleAnalytics\Tracker($this->config['ga_code'], $this->config['ga_domain']);
		
		// Assemble Visitor information
		$visitor = new GoogleAnalytics\Visitor();
		$visitor->setIpAddress($_SERVER['REMOTE_ADDR']);
		$visitor->setUserAgent($_SERVER['HTTP_USER_AGENT']);
		$visitor->setScreenResolution('1024x768');
		
		// Assemble Session information
#		$session = new GoogleAnalytics\Session();
		
		// Assemble Page information
		$page    = new GoogleAnalytics\Page($_SERVER['REQUEST_URI']);
		$page->setTitle(basename($_SERVER['REQUEST_URI']));
		
		// Track page view
#		$tracker->trackPageview($page, $session, $visitor);
	}

	

	// create table for logging API queries
	function offer_it_create_db_table() {
	    $table = $this->wpdb->prefix . 'offerit_log';
	    $query = "CREATE TABLE IF NOT EXISTS " . $table . " (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`ts` datetime DEFAULT NULL,
				`request`  text COLLATE utf8_unicode_ci,
				`response` text COLLATE utf8_unicode_ci,
				PRIMARY KEY (`id`)
				) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
	
	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	    dbDelta($query);
	}



	// add admin menu item
	function offer_it_admin_menu() {
		add_options_page('OfferIT Configuration', 'OfferIT setup', 'administrator', 'offer-it', array(&$this, 'offer_it_admin_template'));
	}



	// render admin config panel HTML
	function offer_it_admin_template() {
		require('offer-it-admin.php');
	}



	// throw debug data arrays on the screen
	function debug($data, $stop = false) {

		echo '<pre>' . print_r($data, true) . '</pre>';

		if($stop) die('DEBUG: STOP');
	}

} // END: OfferIT class

?>