<?php

/**
 * Our wrapper around the php OAuth class
 */
class bSocial_OAuth
{
	// curl options
	public $timeout = 30;           // default timeout
	public $connecttimeout = 30;    // default connect timeout.
	public $ssl_verifypeer = FALSE; // Verify peer SSL Cert.?
	public $useragent = 'bSocial v0.1';
	public $http_header = array();

	public $consumer = NULL;
	public $token = NULL;
	public $sha1_method = NULL;

	public function __construct( $consumer_key, $consumer_secret, $user_key = NULL, $user_secret = NULL )
	{
		if ( ! class_exists( 'OAuthRequest' ) )
		{
			require __DIR__ . '/external/OAuth.php';
		}

		$this->consumer = new OAuthConsumer( $consumer_key, $consumer_secret );

		if ( $user_key && $user_secret )
		{
			$this->token = new OAuthConsumer( $user_key, $user_secret );
		}

		$this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
	}//END __construct

	public function get_http( $query_url, $parameters = array() )
	{
		return $this->oauth_http( $query_url, 'GET', $parameters );
	}//END get_http

	/**
	 * execute an OAuth HTTP POST.
	 *
	 * to make a non-form-based post, where the data is not in the
	 * key/var format, pass in a parameter of 'custom_post_type' with
	 * the post data type ('json', 'xml', etc.) and we'll set up the
	 * Content-type and Content-length headers.
	 */
	public function post_http( $query_url, $parameters = array() )
	{
		return $this->oauth_http( $query_url, 'POST', $parameters );
	}//END post_http

	public function oauth_http( $query_url, $method, $parameters = array() )
	{
		if ( ! isset( $parameters['format'] ) )
		{
			$parameters['format'] = 'json';
		}

		$request = OAuthRequest::from_consumer_and_token( $this->consumer, $this->token, $method, $query_url, $parameters );

		$request->sign_request( $this->sha1_method, $this->consumer, $this->token );

		return json_decode( $this->http( $request->to_url(), $method, $parameters ) );
	}//END oauth_http

	// from twitteroauth library. this is the low-level curl implementation
	// see https://github.com/abraham/twitteroauth
	public function http( $url, $method, $postfields = NULL )
	{
		$this->http_info = array();
		$ci = curl_init();

		/* Curl settings */
		curl_setopt( $ci, CURLOPT_USERAGENT, $this->useragent );
		curl_setopt( $ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout );
		curl_setopt( $ci, CURLOPT_TIMEOUT, $this->timeout );
		curl_setopt( $ci, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ci, CURLOPT_HTTPHEADER, array('Expect:' ) );
		curl_setopt( $ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer );
		curl_setopt( $ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader' ) );
		curl_setopt( $ci, CURLOPT_HEADER, FALSE );

		switch ( $method )
		{
			case 'POST':
				curl_setopt($ci, CURLOPT_POST, TRUE);
				if ( ! empty( $postfields ) )
				{
					// check if we're posting a form or application content
					if ( isset( $postfields['custom_post_type'] ) )
					{
						// not a normal form post
						curl_setopt( $ci, CURLOPT_CUSTOMREQUEST, 'POST');
						curl_setopt( $ci, CURLOPT_POSTFIELDS, $postfields['post_data'] );
						curl_setopt( $ci, CURLOPT_RETURNTRANSFER, true);
						curl_setopt( $ci, CURLOPT_HTTPHEADER, array(
										 'Content-Type: application/' . $postfields['custom_post_type'],
										 'Content-Length: ' . strlen( $postfields['post_data'] ),
						) );
					}//END if
					else
					{
						curl_setopt( $ci, CURLOPT_POSTFIELDS, $postfields );
					}
				}//END if
				break;

			case 'DELETE':
				curl_setopt( $ci, CURLOPT_CUSTOMREQUEST, 'DELETE' );
				if ( ! empty( $postfields ) )
				{
					$url = "{$url}?{$postfields}";
				}
		}//END switch

		curl_setopt( $ci, CURLOPT_URL, $url );
		$response = curl_exec( $ci );

		$this->http_code = curl_getinfo( $ci, CURLINFO_HTTP_CODE );
		$this->http_info = array_merge( $this->http_info, curl_getinfo( $ci ) );
		$this->url = $url;
		curl_close( $ci );
		return $response;
	}//END http

	/**
	 * store header info
	 * see https://github.com/abraham/twitteroauth
	 */
	public function getHeader( $ch, $header )
	{
		$i = strpos($header, ':');
		if ( ! empty( $i ) )
		{
			$key = str_replace( '-', '_', strtolower( substr( $header, 0, $i ) ) );
			$value = trim( substr( $header, $i + 2 ) );
			$this->http_header[ $key ] = $value;
		}
		return strlen( $header );
	}//END getHeader
}//END class