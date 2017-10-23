<?php

GFForms::include_feed_addon_framework();

class GFSendgridFeedAddOn extends GFFeedAddOn {
    
	protected $_version = GF_SENDGRID_FEED_ADDON_VERSION;
    
	protected $_min_gravityforms_version = '1.9.16';
    
	protected $_slug = 'sendgrid-gravityforms';
    
	protected $_path = 'sendgrid-gravityforms/sendgrid-gravityforms.php';
    
	protected $_full_path = __FILE__;
    
	protected $_title = 'Gravity Forms - Sendgrid Feed Add-On';
    
	protected $_short_title = 'Sendgrid Feed Add-On';
    
	private static $_instance = null;
    
	
	public static function get_instance() {
        
		if ( self::$_instance == null ) {
            
			self::$_instance = new GFSendgridFeedAddOn();
            
		}
        
		return self::$_instance;
	}
    
	public function init() {
        
		parent::init();
        
		$this->add_delayed_payment_support(
            
			array(
            
				'option_label' => esc_html__( 'Subscribe contact to service x only when payment is received.', 'sendgrid-gravityforms' )
            
			)
            
		);
        
	}
    
	public function process_feed( $feed, $entry, $form ) {
        
        $settings = $this->get_plugin_settings();
        
        $sendgrid_username = $settings['sendgrid_username']; 
        
        $sendgrid_userpass = $settings['sendgrid_password']; 
        
        $sendgrid_api = $settings['sendgrid_api_key']; 
                       
        $sendgrid_feed_name = $feed['meta']['sendgrid_feed_name'];
        
        $sendgrid_selected_list = $feed['meta']['sendgrid_lists'];
                                
        $first_name = $feed['meta']['first_name'];
            
        $last_name = $feed['meta']['last_name'];
            
        $email = $feed['meta']['email'];
        
        $source_url = $feed['meta']['source_url'];
            
        $ip = $feed['meta']['ip'];
        
        $form_name = $feed['meta']['form_title'];
                            		
		$field_map = $this->get_field_map_fields( $feed, 'mapfields_for_sendgrid' );
		        
		$merge_vars = array();
        
		foreach ( $field_map as $name => $field_id ) {
            			
			$merge_vars[ $name ] = $this->get_field_value( $form, $entry, $field_id );
            
		}
        
        $first_name = $merge_vars['first_name'];
            
        $last_name = $merge_vars['last_name'];
            
        $email = $merge_vars['email'];
        
        $source_url = $merge_vars['source_url'];
            
        $ip = $merge_vars['ip'];
        
        $form_name  = $merge_vars['form_title'];
                
        $isEnabled = true;
        
        if($sendgrid_username == "" || $sendgrid_userpass == "" || $sendgrid_api == "") $isEnabled = false;
        
        //$isEnabled = false;
        
        $customFields = '';
        
        $value_fields = array( 'checkbox', 'radio', 'select' );
        
        $details = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));
            
        $city = "";
            
        $country = "";
            
        if( isset($details->city) ) $city = $details->city;
            
        if($city == "") {
                
            if( isset($details->region) ) $city = $details->region;
        }
            
        if( isset($details->country) ) $country = country_code_to_country($details->country);
                        
        foreach( $form['fields'] as &$field ) {
            
            if (0 === strpos($field['adminLabel'], 'sendgrid_')) {
                
                $customFields .= ', "' . str_replace('sendgrid_', '', $field['adminLabel']) . '": ';
                
                
                if ( in_array( $field->get_input_type(), $value_fields ) ) { 
                
                    $customFields .= '"' . $field->get_value_entry_detail( RGFormsModel::get_lead_field_value( $entry, $field ), '', true, 'text' ) . '"';
                    
                } else  { 
                
                    $customFields .= '"' . $entry[ $field['id'] ] . '"';
                    
                }
                
            }
            
            if (0 === strpos($field['label'], 'City')) {
                
                GFAPI::update_entry_field( $entry['id'], $field['id'], $city );
                                                
            }
            
            if (0 === strpos($field['label'], 'Country')) {
                
                GFAPI::update_entry_field( $entry['id'], $field['id'], $country );
                                                
            }
            
        }
        
		//Send the values to the third-party service.
        /*$handle = fopen("E:\\wamp\\www\\wp-gf\\wp-content\\plugins\\sendgrid-gravityforms\\resource.txt", "a+");
        
        fwrite($handle, "Feed\n\n");
        
        fwrite($handle, print_r($feed, true));

        fwrite($handle, "\r\nEntry\n\n");
        
        fwrite($handle, print_r($entry, true));

        fwrite($handle, "Form\n\n");
        
        fwrite($handle, print_r($form, true));
        
        fclose($handle);*/
                                
        if( $isEnabled == true ) {
            
            require 'vendor/autoload.php';
        
            $sendgrid = new SendGrid( $sendgrid_api );                                                                                    
            
            $subscribed_date = date('m/d/Y');
                                    
            $tmpJsonString = '[ { "email": "' . $email . '", "first_name": "' . $first_name . '", "last_name": "' . $last_name . '", 
            
                                  "url": "' . $source_url . '", "ip_address": "' . $ip . '", "city": "' . $city . '",
                                  
                                  "country": "' . $country . '", "form_name": "' . $form_name . '", "date_subscribed": "' . $subscribed_date . '"' . $customFields . ' } ]';
            
            //fwrite($handle, $tmpJsonString);
                                    
            $request_body = json_decode( $tmpJsonString );
            
            if($sendgrid_selected_list == 0) {
                
                $response = $sendgrid->client->contactdb()->recipients()->post($request_body);
                
            } else {
                
                $response = $sendgrid->client->contactdb()->recipients()->post($request_body);
                
                $recipent_id = json_decode($response->body(), true);
                
                if( isset($recipent_id['persisted_recipients']) ) {
                    
                    $tmpJsonString = '[ "' . $recipent_id['persisted_recipients'][0] . '"]';
                    
                    $request_body = json_decode($tmpJsonString);
                    
                    $response = $sendgrid->client->contactdb()->lists()->_($sendgrid_selected_list)->recipients()->post($request_body);
                    
                }
                
            }
            
            //$msg = "Status Code: " . $response->statusCode() . "\r\n";
            
            //$msg .= "Response Body: " . $response->body() . "\r\n";
                                    
            //fwrite($handle, $msg);
            
        } else {
            
            //fwrite($handle, "Something went Wrong. Sorry.\r\n\r\n");
            
        }
        
                        
        //fclose($handle);
        
	}
    
	
	
	
    
	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
        
		return array(
            
			array(
            
				'title'  => esc_html__( 'Sendgrid User and API Informations', 'sendgrid-gravityforms' ),
            
				'fields' => array(
            
					array(
            
						'name'    => 'sendgrid_username',
            
						'tooltip' => esc_html__( 'Enter your Sendgrid Username', 'sendgrid-gravityforms' ),
            
						'label'   => esc_html__( 'Username', 'sendgrid-gravityforms' ),
            
						'type'    => 'text',
            
						'class'   => 'medium',
            
                        'required' => true,
					),
            
                    array(
            
						'name'    => 'sendgrid_password',
            
						'tooltip' => esc_html__( 'Enter your Sendgrid Password', 'sendgrid-gravityforms' ),
            
						'label'   => esc_html__( 'Password', 'sendgrid-gravityforms' ),
            
						'type'    => 'text',
            
						'class'   => 'medium',
            
                        'required' => true,
					),
            
                    array(
            
						'name'    => 'sendgrid_api_key',
            
						'tooltip' => esc_html__( 'Enter your Sendgrid API Key', 'sendgrid-gravityforms' ),
            
						'label'   => esc_html__( 'API Key', 'sendgrid-gravityforms' ),
            
						'type'    => 'text',
            
						'class'   => 'medium',
            
                        'required' => true,
					),
            
				),
			),
		);
	}
    
	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
	 *
	 * @return array
	 */
    
	public function feed_settings_fields() {
        
        $settings = $this->get_plugin_settings();
        
        $sendgrid_username = $settings['sendgrid_username']; 
        
        $sendgrid_userpass = $settings['sendgrid_password']; 
        
        $sendgrid_api = $settings['sendgrid_api_key']; 
                
        $finalList = array();
        
        array_push($finalList, array("label" => "Select a List", "value" => "0" ));
        
        if($sendgrid_api != "") {

            require 'vendor/autoload.php';
        
            $sendgrid = new SendGrid( $sendgrid_api );
            
            $response = $sendgrid->client->contactdb()->lists()->get();
            
            $lists = json_decode($response->body(), true);
            
            if( is_array($lists) ) {
            
                foreach($lists as $key => $topLists) {

                    if(is_array($topLists) ) {

                        foreach($topLists as $key => $topList) {

                            array_push($finalList, array("label" => $topList['name'], "value" => $topList['id'] ));

                        }

                    }

                }
                
            }
            
        }
                                            
		return array(
            
			array(
            
				'title'  => esc_html__( 'Sendgrid Feed Settings', 'sendgrid-gravityforms' ),
            
				'fields' => array(
					
					array(
            
						'label'   => esc_html__( 'Feed name', 'sendgrid-gravityforms' ),
            
						'type'    => 'text',
            
						'name'    => 'sendgrid_feed_name',
            
						'tooltip' => esc_html__( 'Enter the name of the Feed.', 'sendgrid-gravityforms' ),
            
						'class'   => 'medium',
            
                        'required' => true,
            
					),
            
                    array(
            
						'label'   => esc_html__( 'Lists', 'sendgrid-gravityforms' ),
            
                        'type'    => 'select',
            
                        'name'    => 'sendgrid_lists',
            
                        'tooltip' => 'Select the Lists (These lists are created in the sendgrid)',
            
                        'choices' => $finalList
            
                    ),
            					            
					array(
            
						'name'      => 'mapfields_for_sendgrid',
            
						'label'     => esc_html__( 'Map Fields', 'sendgrid-gravityforms' ),
            
						'type'      => 'field_map',
            
						'field_map' => array(
            
                            array(
            
								'name'     => 'first_name',
            
								'label'    => esc_html__( 'First Name', 'sendgrid-gravityforms' ),
            
                                'field_type' => array( 'text', 'name', 'hidden' ),
            
                                'tooltip' => esc_html__( 'First Name to capture for processing sendgrid.', 'sendgrid-gravityforms' ),
            
								'required' => 1,
            
							),

                            array(
            
								'name'     => 'last_name',
            
								'label'    => esc_html__( 'Last Name', 'sendgrid-gravityforms' ),
            
                                'field_type' => array( 'text', 'name', 'hidden' ),
            
                                'tooltip' => esc_html__( 'Last Name to capture for processing sendgrid.', 'sendgrid-gravityforms' ),
            
								'required' => 1,
            
							),
            
							array(
            
								'name'       => 'email',
            
								'label'      => esc_html__( 'Email', 'sendgrid-gravityforms' ),            								
            
								'field_type' => array( 'email', 'hidden' ),
            
								'tooltip' => esc_html__( 'Email to capture for processing sendgrid.', 'sendgrid-gravityforms' ),
            
                                'required'   => 1,
            
							),
            
                            array(
            
								'name'       => 'form_title',
            
								'label'      => esc_html__( 'Form Title', 'sendgrid-gravityforms' ),            								
            								            
								'tooltip' => esc_html__( 'Name of the form you want to capture...', 'sendgrid-gravityforms' ),
            
                                'required'   => 1,
            
							),
            
                            array(
            
								'name'       => 'source_url',
            
								'label'      => esc_html__( 'Form URL', 'sendgrid-gravityforms' ),            								
            								            
								'tooltip' => esc_html__( 'Form from which sendgrid list is subscribed to', 'sendgrid-gravityforms' ),
            
                                'required'   => 1,
            
							),
            
                            array(
            
								'name'       => 'ip',
            
								'label'      => esc_html__( 'User IP', 'sendgrid-gravityforms' ),            								
            								            
								'tooltip' => esc_html__( 'IP of the user who subscribed', 'sendgrid-gravityforms' ),
            
                                'required'   => 1,
            
							),
            							
						),
            
					),
            
					array(
            
						'name'           => 'condition',
            
						'label'          => esc_html__( 'Condition', 'sendgrid-gravityforms' ),
            
						'type'           => 'feed_condition',
            
						'checkbox_label' => esc_html__( 'Enable Condition', 'sendgrid-gravityforms' ),
            
						'instructions'   => esc_html__( 'Process this sendgrid feed if', 'sendgrid-gravityforms' ),
            
					),
            
				),
            
			),
            
		);
        
	}
    
	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
                        
		return array(
                                                            
			'sendgrid_feed_name'  => esc_html__( 'Feed Name', 'sendgrid-gravityforms' )
                                    
		);
        
	}
        	
	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		// Get the plugin settings.
		$settings = $this->get_plugin_settings();
		// Access a specific setting e.g. an api key
		$key = rgar( $settings, 'apiKey' );
		return true;
	}
}