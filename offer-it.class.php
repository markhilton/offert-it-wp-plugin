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
		$check         = true;
		$this->options = array(
			'domain', 			// offerIT tracking domain
			'username', 		// offerIT username
			'apikey', 			// offerIT API key
			'tracking_code', 	// default ocode if non is detected on landing_url request
			'landing_url', 		// landing URL to trigger redirection bounce vs OfferIT system
			'conversion_url', 	// conversion URL to trigger post to OfferIT system
			'conversion_ouid', 	// conversion unique variable
			'log'               // log plugin activity
		);

		$required = array(
			'domain',
			'username',
			'apikey',
			'tracking_code',
			'landing_url',
			'conversion_url',
			'conversion_ouid'
		);

		foreach($this->options as $option) {
			$this->config[ $option ] = trim(get_option('offer_it_' . $option));
			if(in_array($option, $required) && !$this->config[ $option ]) $check = false;
		}

		// start PHP session if not present
		if(!session_id()) session_start();

	
	    // store GET variables into a SESSION to be able to access later
	    if(isset($_GET['ocode'])   && $_GET['ocode'])   $_SESSION['offer_it']['ocode']   = $_GET['ocode'];
	    if(isset($_GET['transid']) && $_GET['transid']) $_SESSION['offer_it']['transid'] = $_GET['transid'];

		// terminate plugin if required setup variables is missing
		if($check === false) return $this;

#unset($_SESSION['offer_it']['transid']); // DEBUG: remove session transaction ID

	    // trigger landing page track redirect if landing URL string is matched
	    if(strpos($_SERVER['REQUEST_URI'], $this->config['landing_url']) !== false && !$_SESSION['offer_it']['transid']):

			$ocode = $_SESSION['offer_it']['ocode'] ? $_SESSION['offer_it']['ocode'] : $this->config['tracking_code'];
			$url   = 'http://' . str_replace('//', '/', sprintf('%s/track/%s/%s', $this->config['domain'], $ocode, $_SERVER['REQUEST_URI']));
			$data  = array(
				'address'  => $_SERVER['REMOTE_ADDR'],
				'request'  => 'Landing URL request detected: ' . $_SERVER['REQUEST_URI'],
				'response' => 'Redirecting to: ' . $url
			);

			if($this->config['log']) $this->wpdb->insert($this->wpdb->prefix . 'offerit_log', $data);

			header( 'Location: ' . $url ); die();
	    endif;

	    // trigger conversion post if conversion URL string is matched
	    if(isset($_GET[ $this->config['conversion_ouid'] ]) && strpos($_SERVER['REQUEST_URI'], $this->config['conversion_url']) !== false):
			// query database for order details by order unique ID
			$query = sprintf('SELECT * FROM %s%s WHERE %s="%s" LIMIT 1', 
					 $this->wpdb->prefix, $this->config['db_table'], $this->config['db_column'], $_GET[ $this->config['conversion_ouid'] ]);
			$order = $this->wpdb->get_row($query);
			$data  = array(
				'address'  => $_SERVER['REMOTE_ADDR'],
				'request'  => 'Querying order from DB: ' . $query,
				'response' => substr(print_r($order, true), 6)
			);

			if($this->config['log']) $this->wpdb->insert($this->wpdb->prefix . 'offerit_log', $data);

			if(is_object($data)) $this->offer_it_conversion($order);
	    endif;
	    
	    
	    return $this;
	}


	
	// plugin installation - add config options to database
	function offer_it_install() {
		// create db log table if does not exist
	    $this->offer_it_create_db_table();
	    
		foreach($this->options as $option)
			add_option( 'offer_it_' . $option, null );
	}



	// plugin removal - remove DB options
	function offer_it_uninstall() {
#		foreach($this->options as $option)
#			delete_option( 'offer_it_' . $option, null );

#		$this->wpdb->query("DROP TABLE IF EXISTS " . $this->wpdb->prefix . 'offerit_log');
	}



	// post conversion pixel
	function offer_it_conversion($data) {
		if(!$_SESSION['offer_it']['transid'] || !$this->config['domain'] || !$this->config['conversion_ouid']) return false;
	
		$mapper    = $this->config['mapper_post']['fields'];
		$post_url  = sprintf('http://%s/signup/process_pixel.php', $this->config['domain']);
		$post_data = array(
			'transid'   => $_SESSION['offer_it']['transid'],			// offerIT transaction ID
			'orderid'   => $_GET[ $this->config['conversion_ouid'] ],	// order ID - has to be unique for OfferIT to accept conversion POST call

			'gross'     => $data->$this->config['map_total'],			// transaction total
	
			'firstname' => $data->$this->config['map_first_name'],
			'lastname'  => $data->$this->config['map_last_name'] ,
			'address1'  => $this->config['map_address1'],
			'address2'  => $this->config['map_address2'],
			'city'      => $this->config['map_city']    ,
			'state'     => $this->config['map_state']   ,
			'zip'       => $this->config['map_zip']     ,
			'country'   => $this->config['map_country'] ,
	
			'email'     => $this->config['map_email']   ,
			'phone'     => $this->config['map_phone']   ,
	
			'custom1'   => $this->config['map_custom1'] ,
			'custom2'   => $this->config['map_custom2'] ,
			'custom3'   => $this->config['map_custom3'] ,
			'custom4'   => $this->config['map_custom4'] ,
			'custom5'   => $this->config['map_custom5'] ,
			'ip'     	=> $_SERVER['REMOTE_ADDR']
		);


		// post transactions
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_POST,            true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,  true); 
		curl_setopt($ch, CURLOPT_HEADER,          false); 
		curl_setopt($ch, CURLOPT_URL,             $post_url);
		curl_setopt($ch, CURLOPT_POSTFIELDS,      http_build_query($post_data));
		
		$response = curl_exec($ch);
		
		curl_close($ch);
	

		// store CURL post results in database
		$data = array(
			'address'  => $_SERVER['REMOTE_ADDR'],
			'request'  => substr(print_r($post_data, true), 6),
			'response' => $response
		);

		if($this->config['log']) $this->wpdb->insert($this->wpdb->prefix . 'offerit_log', $data);
	}
	
	

	// create table for logging API queries
	function offer_it_create_db_table() {
	    $table = $this->wpdb->prefix . 'offerit_log';
	    $query = "CREATE TABLE IF NOT EXISTS " . $table . " (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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
} // END: OfferIT class

?>