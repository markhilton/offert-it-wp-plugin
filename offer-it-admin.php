<div class="wrap">
	<h2>OfferIT Integration Configuration</h2>
	<p>This plugin allows you to integrate <a target="_blank" href="http://offerit.com">OfferIT.com</a> Affiliate Tracking System with your current e-commerce Word Press plugin.</p>
	
	<form method="post" action="options.php">
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="offer_it_domain,offer_it_username,offer_it_apikey,offer_it_conversion_url,offer_it_conversion_ouid,offer_it_ga_code,offer_it_ga_domain" />
		
		<?php wp_nonce_field('update-options'); ?>
	
		<table>
			<tr>
				<td colspan="2" align="left">
					<h3>Offer IT API access</h3>
					<p>Here's some more information how to <a target="_blank" href="http://wiki.offerit.com/index.php/Offerit_API">generate API access key</a>.<br />
					Your OfferIT landing page URL needs to contain %%transid%% variable in order to match affiliate forwarding traffic to your website.
				</td>
			</tr>
			<tr>
				<th align="left">Tracking domain:</th>
				<td align="left"><input name="offer_it_domain"   type="text" size="32" value="<?php echo get_option('offer_it_domain'); ?>" /></td>
			</tr>
			<tr>
				<th align="left">User name:</th>
				<td align="left"><input name="offer_it_username" type="text" size="32" value="<?php echo get_option('offer_it_username'); ?>" /></td>
			</tr>
			<tr>
				<th align="left">API key:</th>
				<td align="left"><input name="offer_it_apikey"   type="text" size="32" value="<?php echo get_option('offer_it_apikey'); ?>" /></td>
			</tr>


			<tr>
				<td colspan="2" align="left">
					<h3>Conversion Tracking</h3>
					<p>In order to fire up <a target="_blank" href="http://wiki.offerit.com/index.php/Affiliate_Custom_Pixels">OfferIT conversion tracking pixel</a> 
					you need to specify your conversion URL<br />and unique order variable, that will be used to track placed orders and affiliate commissions.</p>

					<p>For example for <a targe="_blank" href="http://cart66.com/">Cart66 plugin</a> default conversion page is: &quot;<u>/store/receipt/?ouid=</u>&quot; 
					and its unique variable used to identify orders: &quot;<u>ouid&quot;</u>.<br />
					When plugin detects visitor landing on conversion page, it will trigger conversion POST to OfferIT system.</p>
				</td>
			</tr>
			<tr>
				<th align="left">Conversion URL:</th>
				<td align="left">
					<input name="offer_it_conversion_url"  type="text" size="32" value="<?php echo get_option('offer_it_conversion_url'); ?>" />
					(ex. /store/receipt/?ouid=)
				</td>
			</tr>
			<tr>
				<th align="left">Conversion variable:</th>
				<td align="left">
					<input name="offer_it_conversion_ouid" type="text" size="32" value="<?php echo get_option('offer_it_conversion_ouid'); ?>" />
					(ex. ouid)
				</td>
			</tr>


			<tr>
				<td colspan="2" align="left">
					<h3>Google Analytics <i>(optional)</i></h3>
					<p>This configuration will push custom events in order to track traffic and e-commerce conversions for individual affiliates in your 
					<a target="_blank" href="www.google.com/analytics/">Google Analytics</a> account.</p>
				</td>
			</tr>
			<tr>
				<th align="left">Property ID:</th>
				<td align="left"><input name="offer_it_ga_code" type="text" size="32" value="<?php echo get_option('offer_it_ga_code'); ?>" />
					(ex. UA-12345678-1)
				</td>
			</tr>
			<tr>
				<th align="left">Tracking domain:</th>
				<td align="left"><input name="offer_it_ga_domain" type="text" size="32" value="<?php echo get_option('offer_it_ga_domain'); ?>" />
					(ex. <?php echo 'mydomain.com' ?>)
				</td>
			</tr>
		</table>
		
		<p><input type="submit" value="<?php _e('Save Changes') ?>" /></p>
	</form>
</div>
