<?php
	// set base dir
	define('__ROOT__', dirname(dirname(__FILE__)));

	// load ips constants
    require_once(__ROOT__ . '/libs/ips.constants.php');

    class PoolLightControler extends IPSModule
	{
 
		// helper properties
		private $position = 0;
		private $ColorNone = 7;
		private $SceneNone = 17;


        // Der Konstruktor des Moduls
        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) 
		{
            parent::__construct($InstanceID);
 
            // Selbsterstellter Code
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
		{
            parent::Create();
 
			$this->RegisterPropertyString('url', 'http://');
			$this->RegisterPropertyBoolean('connected', false);
			// register update timer
			$this->RegisterPropertyInteger('UpdateInterval', 15);
			$this->RegisterTimer('PoolLightTimerUpdate', 0, 'HPLC_GetState(' . $this->InstanceID . ');');
			// register kernel messages
			$this->RegisterMessage(0, IPS_KERNELMESSAGE);
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
		{
            parent::ApplyChanges();

			$this->RegisterProfileAssociation(
				'PoolLight.State',
				'Network',
				'',
				'',
				0,
				1,
				0,
				0,
				0,
				[
					[0, 'Offline', '', 0xFF0000],
					[1, 'Online', '', 0x3ADF00],
				]
			);
			$this->RegisterProfileAssociation(
				'PoolLight.Color',
				'Execute',
				'',
				'',
				0,
				7,
				0,
				0,
				1,
				[
					[0, $this->Translate('White'), '', 0xFFFFFF],
					[1, $this->Translate('Red'), '', 0xFF0000],
					[2, $this->Translate('Green'), '', 0x3ADF00],
					[3, $this->Translate('Blue'), '', 0x0000FF],
					[4, $this->Translate('Cyan'), '', 0x66FFFF],
					[5, $this->Translate('Yellow'), '', 0xFFFF00],
					[6, $this->Translate('Mangenta'), '', 0xFF00CC],
					[7, $this->Translate('None'), '', -1]
				]
			);
			$this->RegisterProfileAssociation(
				'PoolLight.Scene',
				'Execute',
				'',
				'',
				7,
				16,
				0,
				0,
				1,
				[
					[7, $this->Translate('Evening Sea'), 'Wave', -1],
					[8, $this->Translate('Evening River'), 'Flame', -1],
					[9, $this->Translate('Rivera'), 'Welness', -1],
					[10, $this->Translate('Colorfull'), 'Stars', -1],
					[11, $this->Translate('Rainbow'), 'Sun', -1],
					[12, $this->Translate('River of Color'), 'Fog', -1],
					[13, $this->Translate('Disco'), 'Image', -1],
					[14, $this->Translate('Four Saison'), 'Flower', -1],
					[15, $this->Translate('Party'), 'Coctail', -1],
					[16, $this->Translate('None'), '', -1]
				]
			);
 			$this->RegisterVariableBoolean('State', $this->Translate('State'), 'PoolLight.State', $this->_getPosition());
 			$this->RegisterVariableBoolean('Power', $this->Translate('Power'), '~Switch', $this->_getPosition());
			$this->EnableAction('Power');
			$this->RegisterVariableInteger('Color', $this->Translate('Color'), 'PoolLight.Color', $this->_getPosition());
			$this->EnableAction('Color');
			$this->RegisterVariableInteger('Scene', $this->Translate('Scene'), 'PoolLight.Scene', $this->_getPosition());
			$this->EnableAction('Scene');

			// receive data only for this instance
			$this->SetReceiveDataFilter('.*"InstanceID":' . $this->InstanceID . '.*');

			// run only, when kernel is ready
			if (IPS_GetKernelRunlevel() == KR_READY) 
			{
				// validate configuration
				$valid_config = $this->ValidateConfiguration(true);
				// set interval
				$this->SetUpdateIntervall($valid_config);
			}
		}
		
		/**
		* validate configuration
		* @param bool $extended_validation
		* @return bool
		*/
		private function ValidateConfiguration($extended_validation = false)
		{
			// check if configuration is complete
			if (!$this->CheckConfiguration()) 
			{
				$this->SetStatus(201);
				return false;
			}

			// read properties
			$url = $this->ReadPropertyString('url');

			// check for valid ip address
			if (filter_var($url, FILTER_VALIDATE_URL) === false)
			{
				$this->SetStatus(203);
				return false;
			}

			// ping ip
			/*
			if (!Sys_Ping($ip, 1000)) 
			{
				$this->SetStatus(203);
				return false;
			}
			*/

			// get online status

			if ($extended_validation) 
			{

			}
			// yay, configuration is valid! =)
			$this->SetStatus(102);
			return true;
		}

		/**
		* set / unset update interval
		* @param bool $enable
		*/
		protected function SetUpdateIntervall($enable = true)
		{
			$interval = $enable ? ($this->ReadPropertyInteger('UpdateInterval') * 1000) : 0;
			$this->SetTimerInterval('PoolLightTimerUpdate', $interval);
		}

		/**
		* Handle Kernel Messages
		* @param int $TimeStamp
		* @param int $SenderID
		* @param int $Message
		* @param array $Data
		* @return bool|void
		*/
		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
			if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) 
			{
				// validate configuration & set interval
				$valid_config = $this->ValidateConfiguration();
				$this->SetUpdateIntervall($valid_config);
			}
		}

		/**
		* httpPost
		* @param string $Url
		* @param array $Data
		* @return Object $Result
		*/
		private function httpPost($url, $data)
		{
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($curl);
			curl_close($curl);
			return $response;
		}

 		/**
		* SetColor
		* @param int $ColorCode
		* @return void
		*/
 		public function SetColor($ColorCode)
		{
			$Url = $this->ReadPropertyString('url') . '2.html';
			$Cmd = array('B6' => 'Select a show', 'show_type' => sprintf("%'.02d", $ColorCode));
		  	$Response = $this->httpPost ($Url, $Cmd);
			$this->_debug('HTTPResponse', $Response);
		}
 
 		/**
		* SetPower
		* @param bool $Power
		* @return void
		*/
		public function SetPower($Power)
		{
			$Url = $this->ReadPropertyString('url') . 'HTMCUInfo';
			if ($Power)
				{$Cmd = array('B1' => 'Turn on the light');}
			else
				{$Cmd = array('B5' => 'Switch to next show');}
		  	$this->httpPost ($Url, $Cmd);
        }
        
 		/**
		* SetScene
		* @param int $Scene
		* @return void
		*/
		public function SetScene($Scene)
		{
			$Url = $this->ReadPropertyString('url') . '2.html';
			$Cmd = array('B6' => 'Select a show', 'show_type' => sprintf("%'.02d", $Scene));
			$this->httpPost ($Url, $Cmd);
		}

 		/**
		* GetState
		* @param none
		* @return bool
		*/
		public function GetState()
		{
            $Curl = curl_init($this->ReadPropertyString('url'));
            curl_exec($Curl);
            // Check if any error occurred
            if ( curl_errno($Curl) > 0)
            {
                $this->SetPoolLightValue( 'State', true );
                curl_close( $Curl );
                return false;
            }
            // Close handle
            curl_close($Curl);
			$this->SetPoolLightValue('State', false);
			return true;
		}		

		/**
		* webfront request actions
		* @param string $Ident
		* @param $Value
		* @return bool|void
		*/
		public function RequestAction($Ident, $Value)
		{
			switch ($Ident) {
				case 'Power':
					$this->SetPoolLightValue($Ident, $Value);
					$this->SetPower($Value);
					break;
				case 'Color':
					$this->SetColor($Value);
					$this->SetPoolLightValue($Ident, $Value);
					$this->SetPoolLightValue('Scene', $this->SceneNone);
					break;
				case 'Scene':
					$this->SetScene($Value);
					$this->SetPoolLightValue($Ident, $Value);
					$this->SetPoolLightValue('Color', $this->ColorNone);
					break;
				default:
					$this->_debug('request action', 'Invalid $Ident <' . $Ident . '>');
			}
		}

		/**
		* register profiles
		* @param $Name
		* @param $Icon
		* @param $Prefix
		* @param $Suffix
		* @param $MinValue
		* @param $MaxValue
		* @param $StepSize
		* @param $Digits
		* @param $Vartype
		*/
		protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype)
		{
			if (!IPS_VariableProfileExists($Name))
			{
				IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string,
			} else {
				$profile = IPS_GetVariableProfile($Name);
				if ($profile['ProfileType'] != $Vartype) {
					$this->_debug('profile', 'Variable profile type does not match for profile ' . $Name);
				}
			}
			IPS_SetVariableProfileIcon($Name, $Icon);
			IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
			IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
			IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
		}

		/**
		* register profile association
		* @param $Name
		* @param $Icon
		* @param $Prefix
		* @param $Suffix
		* @param $MinValue
		* @param $MaxValue
		* @param $Stepsize
		* @param $Digits
		* @param $Vartype
		* @param $Associations
		*/
		protected function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype, $Associations)
		{
			if (is_array($Associations) && sizeof($Associations) === 0) {
				$MinValue = 0;
				$MaxValue = 0;
			}
			$this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);
			if (is_array($Associations)) {
				foreach ($Associations AS $Association) {
					IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
				}
			} else {
				$Associations = $this->$Associations;
				foreach ($Associations AS $code => $association) {
					IPS_SetVariableProfileAssociation($Name, $code, $this->Translate($association), $Icon, -1);
				}
			}
		}
		
		/**
		* checks, if configuration is complete
		* @return bool
		*/
		private function CheckConfiguration()
		{
			// configuration is not finished
			if (!$this->ReadPropertyString('url')) 
			{
				return false;
			}
			return true;
		}

		/***********************************************************
		* Configuration Form
		***********************************************************/

		/**
		* build configuration form
		* @return string
		*/

		public function GetConfigurationForm()
		{
			// update status, when configuration is not complete
			if (!$this->CheckConfiguration()) 
			{
				$this->SetStatus(201);
			}
			// return current form
			return json_encode([
				'elements' => $this->FormHead(),
				'actions' => $this->FormActions(),
				'status' => $this->FormStatus()
			]);
		}
	
		/**
		* return form configurations on configuration step
		* @return array
		*/
		protected function FormHead()
		{
			$form = [
						[
							'type' => 'Label',
							'label' => 'Enter the Wifi Controler Url below.'
						],
						[
							'name' => 'url',
							'type' => 'ValidationTextBox',
							'caption' => 'Wifi Controler Url"'
						],
					];
			return $form;
		}

		/**
		* return form actions by token
		* @return array
		*/
		protected function FormActions()
		{
			$form = [
						[
							'type' => 'Button',
							'label' => 'Connect Controler',
							'onClick' => 'HPLC_Connect($id);'
						]
					];
			return $form;
		}

		/**
		* return from status
		* @return array
		*/
		protected function FormStatus()
		{
			$form = [
				[
					'code' => 101,
					'icon' => 'inactive',
					'caption' => 'Creating instance.'
				],
				[
					'code' => 102,
					'icon' => 'active',
					'caption' => 'Roborock created.'
				],
				[
					'code' => 104,
					'icon' => 'inactive',
					'caption' => 'interface closed.'
				],
				[
					'code' => 201,
					'icon' => 'inactive',
					'caption' => 'Please follow the instructions.'
				],
				[
					'code' => 202,
					'icon' => 'error',
					'caption' => 'IP address must not empty.'
				],
				[
					'code' => 203,
					'icon' => 'error',
					'caption' => 'No valid IP address.'
				]
			];
			return $form;
		}

        /******************************************************
         * Helper Functions
         ******************************************************/

		/**
		* send debug log
		* @param string $notification
		* @param string $message
		* @param int $format 0 = Text, 1 = Hex
		*/
		private function _debug(string $notification = NULL, string $message = NULL, $format = 0)
		{
			$this->SendDebug($notification, $message, $format);
		}
		
		/**
		* check for variable and set value
		* @param $ident
		* @param $value
		*/
		private function SetPoolLightValue($ident, $value)
		{
			if (@$this->GetIDForIdent($ident))
				$this->SetValue($ident, $value);
		}
		
		/**
		* return incremented position
		* @return int
		*/
		private function _getPosition()
		{
			$this->position++;
			return $this->position;
		}

		/***********************************************************
		* Migrations
		***********************************************************/

		/**
		* Polyfill for IP-Symcon 4.4 and older
		* @param $Ident
		* @param $Value
		*/
		protected function SetValue($Ident, $Value)
		{
			if (IPS_GetKernelVersion() >= 5) {
				parent::SetValue($Ident, $Value);
			} else if ($id = @$this->GetIDForIdent($Ident)) {
				SetValue($id, $Value);
			}
		}
  }
?>