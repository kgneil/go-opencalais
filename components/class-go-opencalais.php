<?php

class GO_OpenCalais
{
	public $slug = 'go-opencalais';
	public $post_meta_key = 'go-opencalais';
	public $autotagger = NULL;
	public $admin = NULL;
	public $config = NULL;

	/**
	 * constructor
	 */
	public function __construct()
	{
		$this->config();

		// Make sure we've got an api_key and are in the admin panel before continuing
		if ( ! $this->config( 'api_key' ) || ! is_admin() )
		{
			return;
		} // END if

		$this->admin();
	}//end __construct

	/**
	 * Get config settings
	 */
	public function config( $key = NULL )
	{
		if ( ! $this->config )
		{
			$this->config = apply_filters(
				'go_config',
				array(
					'api_key' => FALSE,
					'post_types' => array( 'post' ),
					'confidence_threshold' => 0,
					// Number of characters to allow for stuggested tags
					'max_tag_length' => 100,
					// The number of allowed ignored tags per post
					'max_ignored_tags' => 30,
					'mapping' => array(
						// In the format of 'OpenCalais entity name' => 'WordPress taxonomy',
						'socialTag' => 'post_tag',
					),
					'autotagger' => array(
						// Term to indicate when a post has been autotagged already
						'term' => 'autotagged',
						// The relevance threshold at which to not add a tag
						'threshold' => 0.29,
						// Number of posts to auto tag per batch
						'num' => 5,
					),
				),
				'go-opencalais'
			);
		}//END if

		if ( ! empty( $key ) )
		{
			return isset( $this->config[ $key ] ) ? $this->config[ $key ] : NULL ;
		}

		return $this->config;
	}//end config

	/**
	 * a object accessor for the admin object
	 */
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-go-opencalais-admin.php';
			$this->admin = new GO_OpenCalais_Admin();
		}//end if

		return $this->admin;
	} // END admin

	/**
	 * a object accessor for the enrich object
	 */
	public function enrich( $post )
	{
		return $this->admin()->enrich( $post );
	}//end enrich

	/**
	 * a object accessor for the autotagger object
	 */
	public function autotagger()
	{
		if ( ! $this->autotagger )
		{
			require_once __DIR__ . '/class-go-opencalais-autotagger.php';
			$this->autotagger = new GO_OpenCalais_AutoTagger();

			// also load the admin object, in case it hasn't already been loaded
			$this->admin();
		}// end if

		return $this->autotagger;
	} // END autotagger

	/**
	 * Return an array of the local taxonomies that go-opencalais is configured to work with
	 */
	public function get_local_taxonomies()
	{
		$local_taxonomies = array();

		foreach ( $this->config( 'mapping' ) as $local_taxonomy )
		{
			if ( ! isset( $local_taxonomies[ $local_taxonomy ] ) )
			{
				$local_taxonomies[ $local_taxonomy ] = $local_taxonomy;
			} // END if
		} // END foreach

		return $local_taxonomies;
	} // END get_local_taxonomies

	/**
	 * Get post meta
	 */
	public function get_post_meta( $post_id )
	{
		if ( ! $meta = get_post_meta( $post_id, $this->post_meta_key, TRUE ) )
		{
			return array();
		} // END if

		return $meta;
	} // END get_post_meta

	public function get_tags( $content )
	{
		if ( empty( $content ) )
		{
			return new WP_Error( 'empty-content', 'Cannot enrich empty post', $this->post );
		}//end if

		$args = array(
			'body'    => $content,
			'headers' => array(
			'X-calais-licenseID' => go_opencalais()->config( 'api_key' ),
		    'Accept'             => 'application/json',
			'Content-type'       => 'text/html',
			'enableMetadataType' => 'SocialTags',
			),
		);

        $response         = wp_remote_post( 'http://api.opencalais.com/tag/rs/enrich', $args );
        $response_code    = wp_remote_retrieve_response_code( $response );
        $response_message = wp_remote_retrieve_response_message( $response );
        $response_body    = wp_remote_retrieve_body( $response );

        if ( is_wp_error( $response ) )
        {
            return $response;
        }//end if
        elseif ( 200 != $response_code && ! empty( $response_message ) )
        {
            return new WP_Error( 'ajax-error', $response_message );
        }//end elseif
        elseif ( 200 != $response_code && ! empty( $response_body ) )
        {
            return new WP_Error( 'ajax-error', $response_body );
        }//end elseif
        elseif ( 200 != $response_code )
        {
            return new WP_Error( 'ajax-error', 'Unknown error occurred' );
        }//end elseif
	        return $response;
    }//end get_tags

    public function ajax_get_tags ( )
    {
    	$POST[ 'content' ];
    }//END ajax_get_tags
}//end class

function go_opencalais()
{
	global $go_opencalais;

	if ( ! isset( $go_opencalais ) )
	{
		$go_opencalais = new GO_OpenCalais();
	}// end if

	return $go_opencalais;
}// end go_opencalais