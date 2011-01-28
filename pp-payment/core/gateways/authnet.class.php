<?php

class PP_Invoice_Authnet
{
    private $login;
    private $transkey;
    private $params   = array();
    private $results  = array();

    private $approved = false;
    private $declined = false;
    private $error    = true;
	private $error_message;

    private $fields;
    private $response;

    static $instances = 0;

    public function __construct( $user_id = false ) {
	
		if(!$user_id )
			$user_id = $user_ID;
	
        if (self::$instances == 0) {

			$this->url = pp_invoice_user_settings( "gateway_url", $user_id );

            $this->params['x_delim_data']     = pp_invoice_user_settings( "gateway_delim_data", $user_id );
            $this->params['x_delim_char']     = pp_invoice_user_settings( "gateway_delim_char", $user_id );
            $this->params['x_encap_char']     = pp_invoice_user_settings( "gateway_encap_char", $user_id );
            $this->params['x_relay_response'] = "FALSE";
            $this->params['x_url']            = "FALSE";
            $this->params['x_version']        = "3.1";
            $this->params['x_method']         = "CC";
            $this->params['x_type']           = "AUTH_CAPTURE";
            $this->params['x_login']          = pp_invoice_user_settings( "gateway_username", $user_id );
            $this->params['x_tran_key']       = pp_invoice_user_settings( "gateway_tran_key", $user_id );
            $this->params['x_test_request']   = "false";

			self::$instances++;
		} else {
			return false;
		}
	}

	public function transaction($cardnum) {
        $this->params['x_card_num']  = trim($cardnum);

    }

    public function process( $retries = 1 ) {
        $this->_prepareParameters();
        $ch = curl_init( $this->url );

        $count = 0;
        while( $count < $retries ) {
		
			//required for GoDaddy
			if( get_option( 'pp_invoice_using_godaddy' ) == 'true' ) {
				curl_setopt( $ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
				curl_setopt( $ch, CURLOPT_PROXY,"http://proxy.shr.secureserver.net:3128" );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
				curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
			}
			
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, rtrim( $this->fields, "&" ) );
            curl_setopt( $ch, CURLOPT_POST, 1);
			if( get_option( 'pp_invoice_force_https' ) != 'true' )
    			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

            $this->response = curl_exec( $ch );
            $this->parseResults();

			if ( $this->getResultResponseFull() == "Approved" ) {
				$this->approved = true;
				$this->declined = false;
				$this->error    = false;
				break;
			} elseif ($this->getResultResponseFull() == "Declined") {
				$this->approved = false;
				$this->declined = true;
				$this->error    = false;
				break;
			} else {
				$this->approved = false;
				$this->declined = false;
				$this->error    = true;
				$this->error_message = curl_error($ch);
			}
			$count++;
		}

        curl_close( $ch );
    }

    function parseResults() {
        $this->results = explode($this->params['x_delim_char'], $this->response);
    }

    public function setParameter($param, $value) {
        $param                = trim($param);
        $value                = trim($value);
        $this->params[$param] = $value;
    }

    public function setTransactionType($type) {
        $this->params['x_type'] = strtoupper(trim($type));
    }

    private function _prepareParameters() {
        foreach( $this->params as $key => $value ) {
            $this->fields .= "$key=" . urlencode( $value ) . "&";
        }
    }

    public function getGatewayResponse() {
        return str_replace( $this->params['x_encap_char'], '', $this->results[0] );
    }
   
    public function getResultResponseFull() {
        $response = array( "", "Approved", "Declined", "Error" );
        return $response[ str_replace( $this->params['x_encap_char'], '', $this->results[0] ) ];
    }

    public function isApproved() {
        return $this->approved;
    }

    public function isDeclined() {
        return $this->declined;
    }

    public function isError() {
        return $this->error;
    }

    public function getResponseText() {
		$strip = array($this->params['x_delim_char'],$this->params['x_encap_char'],'|',',');
        return str_replace($strip,'',$this->results[3]);
    }

    public function getAuthCode() {
	   return str_replace($this->params['x_encap_char'],'',$this->results[4]);
    }

    public function getAVSResponse() {
	   return str_replace($this->params['x_encap_char'],'',$this->results[5]);
    }

    public function getTransactionID() {
	   return str_replace($this->params['x_encap_char'],'',$this->results[6]);
    }

	// Added for Prospress
    public function getErrorMessage() {
		return $this->error_message;
    }
}

