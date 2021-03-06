<?php
/**
 * wrapper for our connection to the twitter services
 */
class bSocial_Twitter
{
	public $oauth = NULL;
	public $comments = NULL;
	public $meta = NULL;
	public $search = NULL;
	public $user_stream = NULL;

	/**
	 * get an oauth instance
	 */
	public function oauth()
	{
		if ( $this->oauth )
		{
			return $this->oauth;
		}

		// check if we have the user token and secret or not
		if ( ! empty( bsocial()->options()->twitter->access_token ) && ! empty( bsocial()->options()->twitter->access_secret ) )
		{
			$this->oauth = bsocial()->new_oauth(
				bsocial()->options()->twitter->consumer_key,
				bsocial()->options()->twitter->consumer_secret,
				bsocial()->options()->twitter->access_token,
				bsocial()->options()->twitter->access_secret
			);
		}
		else
		{
			$this->oauth = bsocial()->new_oauth(
				bsocial()->options()->twitter->consumer_key,
				bsocial()->options()->twitter->consumer_secret
			);
		}

		return $this->oauth;
	}//END oauth

	// prepend the twitter api url if $query_url is not absolute
	public function validate_query_url( $query_url, $parameters )
	{
		if (
			0 !== strpos( $query_url, 'http://' ) &&
			0 !== strpos( $query_url, 'https://' )
		)
		{
			$query_url = 'https://api.twitter.com/1.1/' . $query_url;

			if ( ! isset( $parameters['format'] ) )
			{
				$query_url .= '.json';
			}
			else
			{
				$query_url .= '.' . $parameters['format'];
			}
		}//END if

		return $query_url;
	}//END validate_query_url

	public function get_http( $query_url, $parameters = array() )
	{
		return $this->oauth()->get_http(
			$this->validate_query_url( $query_url, $parameters ),
			$parameters
		);
	}//END get_http

	public function post_http( $query_url, $parameters = array() )
	{
		return $this->oauth()->post_http(
			$this->validate_query_url( $query_url, $parameters ),
			$parameters
		);
	}//END post_http

	public function meta()
	{
		if ( ! $this->meta )
		{
			if ( ! class_exists( 'bSocial_Twitter_Meta' ) )
			{
				require __DIR__ .'/class-bsocial-twitter-meta.php';
			}

			$this->meta = new bSocial_Twitter_Meta;
		}//END if

		return $this->meta;
	}//END meta

	public function comments()
	{
		if ( ! $this->comments )
		{
			if ( ! class_exists( 'bSocial_Twitter_Comments' ) )
			{
				require __DIR__ .'/class-bsocial-twitter-comments.php';
			}

			$this->comments = new bSocial_Twitter_Comments;
		}//END if

		return $this->comments;
	}//END comments

	/**
	 * return the twitter search object
	 */
	public function search()
	{
		if ( ! $this->search )
		{
			if ( ! class_exists( 'bSocial_Twitter_Search' ) )
			{
				require __DIR__ .'/class-bsocial-twitter-search.php';
			}

			$this->search = new bSocial_Twitter_Search( $this );
		}//END if

		return $this->search;
	}//END search

	public function user_stream()
	{
		if ( ! $this->user_stream )
		{
			if ( ! class_exists( 'bSocial_Twitter_User_Stream' ) )
			{
				require __DIR__ .'/class-bsocial-twitter-user-stream.php';
			}

			$this->user_stream = new bSocial_Twitter_User_Stream( $this );
		}//END if

		return $this->user_stream;
	}//END user_stream

	/**
	 * Look up info about the twitter user by their screen name or ID
	 * Note: the ID here is not compatible with the user ID returned from
	 * the search API. This is a Twitter limitation.
	 *
	 * method docs: https://dev.twitter.com/docs/api/1.1/get/users/show
	 * useful: $user->name, $user->screen_name, $user->id_str,
	 *         $user->followers_count
	 *
	 * @param $screen_name user screen name or id
	 * @param $by 'screen_name' or 'id'
	 */
	public function get_user_info( $screen_name, $by = 'screen_name' )
	{
		// are we searching by screen name or ID?
		$by = in_array( $by, array( 'screen_name', 'id' )) ? $by : 'screen_name';

		// check the cache for the user info
		if ( ! $user = wp_cache_get( (string) $screen_name, 'twitter_' . $by ) )
		{
			// check Twitter for the user info
			$user = $this->get_http( 'users/show', array( $by => $screen_name ) );

			if ( empty( $user->errors ) )
			{
				wp_cache_set( (string) $screen_name, $user, 'twitter_screen_name', 604801 ); // cache for 7 days
			}
		}//END if

		return $user;
	}//END get_user_info

	/**
	 * @param $message the message to tweet
	 */
	public function post_tweet( $message )
	{
		return $this->post_http( 'statuses/update', array( 'status' => $message ) );
	}//END post_tweet
}//END class