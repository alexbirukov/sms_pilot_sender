<?php

	Class Extension_sms_pilot_sender extends Extension {
		
		public function about() {
			return array(
				'name' => 'SMS Pilot Sender',
				'version' => '0.1',
				'release-date' => '2013-10-19',
				'author' => array(
					'name' => 'Александр Бирюков',
					'website' => 'http://alexbirukov.ru/',
					'email' => 'info@alexbirukov.ru'
				),
				'description' => 'SMS Pilot Sender plug-in for Symphony CMS.'
			);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => '__addPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => '__savePreferences'
				),
				
				array(
					'page' => '/blueprints/events/new/',
					'delegate' => 'AppendEventFilter',
					'callback' => '__appendEventFilter'
				),
				array(
					'page' => '/blueprints/events/edit/',
					'delegate' => 'AppendEventFilter',
					'callback' => '__appendEventFilter'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventFinalSaveFilter',
					'callback' => '__eventFinalSaveFilter'
				),
			);
		}
		
		/**
		 * Add site preferences
		 */
		public function __addPreferences($context) {

			// Get settings
			$selection = Symphony::Configuration()->get('smspilot');
			if(empty($selection)) $selection = array();

			// Create Group
			$group = new XMLElement('fieldset', '<legend>' . __('SMSPilot Sender') . '</legend>', array('class' => 'settings'));
			
			// Add API Key field
			$label_api = Widget::Label(__('API Key'),
                Widget::Input(
                    'settings[smspilot][api_key]',
                    Symphony::Configuration()->get('api_key', 'smspilot')
                ));
			$group->appendChild($label_api);
			$help = new XMLElement('p', __('To get the API key you need visit <a href="http://www.smspilot.ru/?r=6708">SMS Pilot</a>.'), array('class' => 'help'));
			$group->appendChild($help);
			
			// Add FROM field
			$label_api = Widget::Label(__('From'),
                Widget::Input(
                    'settings[smspilot][from]',
                    Symphony::Configuration()->get('from', 'smspilot')
                ));
			$group->appendChild($label_api);
			$help = new XMLElement('p', __('Optional. Set sender name in the settings <a href="http://www.smspilot.ru/?r=6708">SMS Pilot</a> and fill this field.'), array('class' => 'help'));
			$group->appendChild($help);
			
			// Add phones field
			$label_phones = Widget::Label(__('Phones'),
                Widget::Input(
                    'settings[smspilot][phones]',
                    Symphony::Configuration()->get('phones', 'smspilot')
                ));
			$group->appendChild($label_phones);
			$help = new XMLElement('p', __('Enter your phone number in the format 7XXXXXXXXXX or 8XXXXXXXXXX. The phone numbers are separated by commas.'), array('class' => 'help'));
			$group->appendChild($help);
			
			// Add message field
			$message = Widget::Label(__('Message text'),
                Widget::Input(
                    'settings[smspilot][message]',
                    Symphony::Configuration()->get('message', 'smspilot')
                ));
			$group->appendChild($message);
			$help = new XMLElement('p', __('Enter your message text.'), array('class' => 'help'));
			$group->appendChild($help);
			
			$context['wrapper']->appendChild($group);
		}
		
		/**
		 * Save preferences
		 */
		public function __savePreferences($context) {

			// Remove chars
			$chars = array("(", ")", "-", "+", " ");
			$tmp = str_replace($chars, "",$context['settings']['smspilot']['phones']);
			$context['settings']['smspilot']['phones'] = str_replace(",", ", ",$tmp);
			
			// Trim message text
			$context['settings']['smspilot']['message'] = trim($context['settings']['smspilot']['message']);

		}
		
		/**
		 * Append filter after save
		 */
		public function __eventFinalSaveFilter($context){
		
			$sms_errors = array();
			$sms_errors['10'] = __('INPUT data is required');
			$sms_errors['11'] = __('Unknown INPUT format');
			$sms_errors['12'] = __('XML structure is invalid');
			$sms_errors['13'] = __('JSON structure is invalid');
			$sms_errors['14'] = __('Unknown COMMAND');
			$sms_errors['100'] = __('APIKEY is required');
			$sms_errors['101'] = __('APIKEY is invalid');
			$sms_errors['106'] = __('APIKEY is blocked (spam)');
			$sms_errors['110'] = __('SYSTEM ERROR');
			$sms_errors['113'] = __('IP RESTRICTION');
			$sms_errors['201'] = __('FROM is invalid');
			$sms_errors['202'] = __('FROM is depricated');
			$sms_errors['204'] = __('FROM not found');
			$sms_errors['210'] = __('TO is required');
			$sms_errors['211'] = __('TO is invalid');
			$sms_errors['212'] = __('TO is depricated');
			$sms_errors['213'] = __('Unsupported zone');
			$sms_errors['220'] = __('TEXT is required');
			$sms_errors['221'] = __('TEXT too long');
			$sms_errors['230'] = __('ID is invalid');
			$sms_errors['231'] = __('PACKET_ID is invalid');
			$sms_errors['240'] = __('Invalid INPUT list');
			$sms_errors['241'] = __('You don\'t have enough credit');
			$sms_errors['242'] = __('SMS count limit (trial)');
			$sms_errors['243'] = __('Loop protection');
			$sms_errors['250'] = __('SEND_DATETIME is invalid');
			$sms_errors['300'] = __('SMS server_id is required');
			$sms_errors['301'] = __('SMS server_id is invalid');
			$sms_errors['302'] = __('SMS server_id not found');
			$sms_errors['303'] = __('Invalid SMS check list');
			$sms_errors['304'] = __('SERVER_PACKET_ID is invalid');
			$sms_errors['400'] = __('User not found');
			$sms_errors['401'] = __('Invalid login details');
			
			// Send SMS
			
			// Get settings
			$apikey = Symphony::Configuration()->get('api_key', 'smspilot');
			//$apikey = 'XXXXXXXXXXXXYYYYYYYYYYYYZZZZZZZZXXXXXXXXXXXXYYYYYYYYYYYYZZZZZZZZ';
			$phones = Symphony::Configuration()->get('phones', 'smspilot');
			$message = Symphony::Configuration()->get('message', 'smspilot');
			$from = Symphony::Configuration()->get('from', 'smspilot');

			
			// API 2
			$send = array(
				'apikey' => $apikey,
				'send' => array(
					array('from' => $from, 'to' => $phones, 'text' => $message)
				)
			);
			$result = file_get_contents("http://smspilot.ru/api2.php?r=6708", false, stream_context_create(array(
				"http" => array(
					"method" => "POST",
					"header" => "Content-Type: application/json\r\n",
					"content" => json_encode( $send ),
				),
			)));
			
			// Get response
			$response = json_decode($result);
			
			// Check response			
			if (isset($response->{'error'}->{'code'})) {
				$context['errors'][] = array(
					'smspilot', 
					FALSE, 
					$sms_errors[$response->{'error'}->{'code'}]
				);
				
				Symphony::Log()->pushToLog(__('SMSPilot sender: ') . $sms_errors[$response->{'error'}->{'code'}], 1, true);
			}	
			else
			{
				if ($response->{'send'}[0]->{'error'} != 0) {
					$context['errors'][] = array(
						'smspilot', 
						FALSE, 
						$sms_errors[$response->{'send'}[0]->{'error'}]
					);
					
					Symphony::Log()->pushToLog(__('SMSPilot sender: ') . $sms_errors[$response->{'send'}[0]->{'error'}], 1, true);
				}
				else
				{
					$context['errors'][] = array(
						'smspilot', 
						TRUE
					);
				}
			}
			
		}
		
		/**
		 * Append Filter
		 */
		public function __appendEventFilter(array $context) {
			$context['options'][] = array(
				'smspilot',
				is_array($context['selected']) ? in_array('smspilot', $context['selected']) : false,
				__('SMS Pilot: Send SMS')
			);
		}

	}
