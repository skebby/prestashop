<?php


define("NET_ERROR", "Network+error,+unable+to+send+the+message");
define("SENDER_ERROR", "You+can+specify+only+one+type+of+sender,+numeric+or+alphanumeric");

define ("SMS_TYPE_CLASSIC", "classic");
define ("SMS_TYPE_CLASSIC_PLUS", "classic_plus");
define ("SMS_TYPE_BASIC", "basic");
define ("SMS_TYPE_TEST_CLASSIC", "test_classic");
define ("SMS_TYPE_TEST_CLASSIC_PLUS", "test_classic_plus");
define ("SMS_TYPE_TEST_BASIC", "test_basic");


/**
 * 
 * @author Skebby Dev Team
 * @license  
 *
 */
class SkebbyApiClient {
	
	
	
	/**
	 * 
	 * @param unknown $recipients
	 * @param unknown $text
	 * @param unknown $sms_type
	 */
	public function sendSMS($recipients, $text, $sms_type){
		
	}
	
	
	
	
	/**
	 *
	 * @param unknown $username
	 * @param unknown $password
	 * @param unknown $recipients
	 * @param unknown $text
	 * @param string $sms_type
	 * @param string $sender_number
	 * @param string $sender_string
	 * @param string $user_reference
	 * @param string $charset
	 * @param string $optional_headers
	 * @return unknown
	 */
	private function _sendSMS($username, $password, $recipients, $text, $sms_type = SMS_TYPE_CLASSIC, $sender_number = '', $sender_string = '', $user_reference = '', $charset = '', $optional_headers = null) {
		$url = 'http://gateway.skebby.it/api/send/smseasy/advanced/http.php';
	
		if (! is_array ( $recipients )) {
			$recipients = array (
					$recipients
			);
		}
	
		switch ($sms_type) {
			case SMS_TYPE_CLASSIC :
			default :
				$method = 'send_sms_classic';
				break;
			case SMS_TYPE_CLASSIC_PLUS :
				$method = 'send_sms_classic_report';
				break;
			case SMS_TYPE_BASIC :
				$method = 'send_sms_basic';
				break;
			case SMS_TYPE_TEST_CLASSIC :
				$method = 'test_send_sms_classic';
				break;
			case SMS_TYPE_TEST_CLASSIC_PLUS :
				$method = 'test_send_sms_classic_report';
				break;
			case SMS_TYPE_TEST_BASIC :
				$method = 'test_send_sms_basic';
				break;
		}
	
		$parameters = 'method=' . urlencode ( $method ) . '&' . 'username=' . urlencode ( $username ) . '&' . 'password=' . urlencode ( $password ) . '&' . 'text=' . urlencode ( $text ) . '&' . 'recipients[]=' . implode ( '&recipients[]=', $recipients );
	
		if ($sender_number != '' && $sender_string != '') {
			parse_str ( 'status=failed&message=' . SENDER_ERROR, $result );
			return $result;
		}
		$parameters .= $sender_number != '' ? '&sender_number=' . urlencode ( $sender_number ) : '';
		$parameters .= $sender_string != '' ? '&sender_string=' . urlencode ( $sender_string ) : '';
	
		$parameters .= $user_reference != '' ? '&user_reference=' . urlencode ( $user_reference ) : '';
	
		switch ($charset) {
			case 'UTF-8' :
				$parameters .= '&charset=' . urlencode ( 'UTF-8' );
				break;
			case '' :
			case 'ISO-8859-1' :
			default :
				break;
		}
	
		parse_str ( $this->doPostRequest ( $url, $parameters, $optional_headers ), $result );
	
		return $result;
	}
	
	/**
	 * 
	 * @param unknown $url
	 * @param unknown $data
	 * @param string $optional_headers
	 * @return string|unknown|mixed
	 */
	private function doPostRequest($url, $data, $optional_headers = null) {
		if (! function_exists ( 'curl_init' )) {
			$params = array (
					'http' => array (
							'method' => 'POST',
							'content' => $data 
					) 
			);
			if ($optional_headers !== null) {
				$params ['http'] ['header'] = $optional_headers;
			}
			$ctx = stream_context_create ( $params );
			$fp = @fopen ( $url, 'rb', false, $ctx );
			if (! $fp) {
				return 'status=failed&message=' . NET_ERROR;
			}
			$response = @stream_get_contents ( $fp );
			if ($response === false) {
				return 'status=failed&message=' . NET_ERROR;
			}
			return $response;
		} else {
			$ch = curl_init ();
			curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt ( $ch, CURLOPT_TIMEOUT, 60 );
			curl_setopt ( $ch, CURLOPT_USERAGENT, 'Generic Client' );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
			curl_setopt ( $ch, CURLOPT_URL, $url );
			
			if ($optional_headers !== null) {
				curl_setopt ( $ch, CURLOPT_HTTPHEADER, $optional_headers );
			}
			
			$response = curl_exec ( $ch );
			curl_close ( $ch );
			if (! $response) {
				return 'status=failed&message=' . NET_ERROR;
			}
			return $response;
		}
	}
	
	
	

	
	
	
	
	/**
	 * 
	 * @param unknown $username
	 * @param unknown $password
	 * @param string $charset
	 * @return unknown
	 */
	public function getGatewayCredit($username, $password, $charset = '') {
		$url = "http://gateway.skebby.it/api/send/smseasy/advanced/http.php";
		$method = "get_credit";
		
		$parameters = 'method=' . urlencode ( $method ) . '&' . 'username=' . urlencode ( $username ) . '&' . 'password=' . urlencode ( $password );
		
		switch ($charset) {
			case 'UTF-8' :
				$parameters .= '&charset=' . urlencode ( 'UTF-8' );
				break;
			default :
		}
		
		parse_str ( $this->doPostRequest ( $url, $parameters ), $result );
		return $result;
	}
}