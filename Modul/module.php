<?php
// set base dir
define('__ROOT__', dirname(dirname(__FILE__)));

    // Klassendefinition
    class PoolLightControler extends IPSModule {
 
		// helper properties
		private $position = 0;


        // Der Konstruktor des Moduls
        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) {
            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);
 
            // Selbsterstellter Code
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();
 
			$this->RegisterPropertyString('url', '');
			$this->RegisterPropertyBoolean('connected', false);
			$this->RegisterPropertyBoolean('Power', false);
			$this->RegisterPropertyInteger('Color', 7);
 			$this->RegisterPropertyInteger('Scene', 15);
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();
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
 			$this->RegisterVariableBoolean('Power', $this->Translate('Power'), '~Switch', $this->_getPosition());
			$this->EnableAction('Power');
			$this->RegisterVariableInteger('Color', $this->Translate('Color'), 'PoolLight.Color', $this->_getPosition());
			$this->EnableAction('Color');
			$this->RegisterVariableInteger('Scene', $this->Translate('Scene'), 'PoolLight.Scene', $this->_getPosition());
			$this->EnableAction('Scene');
		}
		
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
        * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
        * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
        *
        * ABC_MeineErsteEigeneFunktion($id);
        *
        */
        public function SetColor(int $ColorCode)
		{
			$Url = $this->ReadPropertyString('url') . '2.html';
			$Cmd = array('B6' => 'Select a show', 'show_type' => sprintf("%'.02d", $ColorCode));
		  	$this->httpPost ($Url, $Cmd);
		}
        
		public function SetPower(bool $Power)
		{
			$Url = $this->ReadPropertyString('url') . 'HTMCUInfo';
			if ($Power)
				{$Cmd = array('B1' => 'Turn on the light');}
			else
				{$Cmd = array('B5' => 'Switch to next show');}
		  	$this->httpPost ($Url, $Cmd);
        }
        
		public function SetScene(int $Scene)
		{
			$Url = $this->ReadPropertyString('url') . '2.html';
			$Cmd = array('B6' => 'Select a show', 'show_type' => sprintf("%'.02d", $Scene));
		  	$this->httpPost ($Url, $Cmd);
        }		

		public function Connect(string $url)
		{
			$Url = $this->ReadPropertyString('url') . '2.html';
        }		

		/**
		* webfront request actions
		* @param string $Ident
		* @param $Value
		* @return bool|void
		*/
		public function RequestAction($Ident, $Value)
		{
			$this->_debug('request action', 'Invalid $Ident <' . $Ident . '>');
			switch ($Ident) {
				case 'Power':
					$this->SetPower($Value);
					break;
				case 'Color':
					$this->SetColor($Value);
					break;
				case 'Scene':
					$this->SetScene($Value);
					break;
				default:
					$this->_debug('request action', 'Invalid $Ident <' . $Ident . '>');
					throw new Exception("Invalid Ident" . $Ident);
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
			if (!IPS_VariableProfileExists($Name)) {
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