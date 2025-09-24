<?php
/**
 * Minimal S3 compatible client with multipart upload support.
 */
class Ai1wm_S3_Client {

	/**
	 * @var string
	 */
	private $endpoint;

	/**
	 * @var string
	 */
	private $region;

	/**
	 * @var string
	 */
	private $bucket;

	/**
	 * @var string
	 */
	private $prefix;

	/**
	 * @var string
	 */
	private $access_key;

	/**
	 * @var string
	 */
	private $secret_key;

	/**
	 * @var bool
	 */
	private $use_path_style = false;

	/**
	 * @var string
	 */
	private $scheme = 'https';

	/**
	 * @var string
	 */
	private $host = '';

	/**
	 * @var string
	 */
	private $port = '';

	/**
	 * @var string
	 */
	private $base_path = '';

	/**
	 * Service name for AWS signatures.
	 *
	 * @var string
	 */
	private $service = 's3';

	/**
	 * @param array $settings Normalised S3 settings.
	 */
	public function __construct( array $settings ) {
		$endpoint = isset( $settings['endpoint'] ) ? $settings['endpoint'] : '';
		$parts    = wp_parse_url( $endpoint );

		if ( empty( $parts['host'] ) ) {
			throw new Ai1wm_S3_Exception( __( 'S3 endpoint is invalid.', AI1WM_PLUGIN_NAME ) );
		}

		$this->scheme        = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$this->host          = strtolower( $parts['host'] );
		$this->port          = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$this->base_path     = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
		$this->endpoint      = $this->scheme . '://' . $this->host . $this->port;
		$this->region        = isset( $settings['region'] ) ? $settings['region'] : '';
		$this->bucket        = isset( $settings['bucket'] ) ? $settings['bucket'] : '';
		$this->prefix        = isset( $settings['prefix'] ) ? $settings['prefix'] : '';
		$this->access_key    = isset( $settings['access_key'] ) ? $settings['access_key'] : '';
		$this->secret_key    = isset( $settings['secret_key'] ) ? $settings['secret_key'] : '';
		$this->use_path_style = ! empty( $settings['use_path_style'] );

		if ( empty( $this->region ) || empty( $this->bucket ) ) {
			throw new Ai1wm_S3_Exception( __( 'S3 region or bucket is not configured.', AI1WM_PLUGIN_NAME ) );
		}

		if ( empty( $this->access_key ) || empty( $this->secret_key ) ) {
			throw new Ai1wm_S3_Exception( __( 'S3 credentials are not configured.', AI1WM_PLUGIN_NAME ) );
		}
	}

	/**
	 * Upload file as multipart object.
	 *
	 * @param  string  $file_path  Absolute path to archive.
	 * @param  string  $remote_key Object key relative to bucket.
	 * @param  integer $chunk_size Chunk size in bytes.
	 * @throws Ai1wm_S3_Exception When upload fails.
	 */
	public function upload( $file_path, $remote_key, $chunk_size = AI1WM_S3_MULTIPART_CHUNK_SIZE ) {
		if ( ! is_readable( $file_path ) ) {
			throw new Ai1wm_S3_Exception( sprintf( __( 'Backup file %s is not readable.', AI1WM_PLUGIN_NAME ), basename( $file_path ) ) );
		}

		$remote_key = $this->prefix . ltrim( str_replace( '\\', '/', $remote_key ), '/' );
		$remote_key = $this->trim_double_slashes( $remote_key );

		$upload_id = null;
		$parts     = array();
		$handle    = ai1wm_open( $file_path, 'rb' );

		if ( ! $handle ) {
			throw new Ai1wm_S3_Exception( __( 'Unable to open backup for reading.', AI1WM_PLUGIN_NAME ) );
		}

		try {
			$upload_id = $this->create_multipart_upload( $remote_key );

			$part_number = 1;

			while ( ! feof( $handle ) ) {
				$chunk = fread( $handle, $chunk_size );

				if ( $chunk === false ) {
					throw new Ai1wm_S3_Exception( __( 'Error while reading backup chunk.', AI1WM_PLUGIN_NAME ) );
				}

				if ( $chunk === '' ) {
					continue;
				}

				$etag = $this->upload_part_with_retry( $remote_key, $upload_id, $part_number, $chunk );

				$parts[] = array(
					'PartNumber' => $part_number,
					'ETag'       => $etag,
				);

				$part_number++;
			}

			if ( empty( $parts ) ) {
				throw new Ai1wm_S3_Exception( __( 'Backup file is empty.', AI1WM_PLUGIN_NAME ) );
			}

			$this->complete_multipart_upload( $remote_key, $upload_id, $parts );
		} catch ( Exception $e ) {
			ai1wm_close( $handle );

			if ( $upload_id ) {
				$this->abort_multipart_upload( $remote_key, $upload_id );
			}

			throw $e;
		}

		ai1wm_close( $handle );
	}

	/**
	 * Initiate multipart upload session.
	 *
	 * @param  string $remote_key Object key.
	 * @return string Upload ID.
	 */
	private function create_multipart_upload( $remote_key ) {
		$response = $this->signed_request( 'POST', $remote_key, array( 'uploads' => '' ) );

		$this->guard_response( $response, __( 'Unable to start multipart upload.', AI1WM_PLUGIN_NAME ) );

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			throw new Ai1wm_S3_Exception( __( 'Unexpected response from S3 while initiating upload.', AI1WM_PLUGIN_NAME ) );
		}

		try {
			$xml = new SimpleXMLElement( $body );
		} catch ( Exception $e ) {
			throw new Ai1wm_S3_Exception( __( 'Failed to parse S3 initiate response.', AI1WM_PLUGIN_NAME ) );
		}

		if ( empty( $xml->UploadId ) ) {
			throw new Ai1wm_S3_Exception( __( 'S3 did not return an upload identifier.', AI1WM_PLUGIN_NAME ) );
		}

		return (string) $xml->UploadId;
	}

	/**
	 * Upload part with retry logic.
	 *
	 * @param  string $remote_key Object key.
	 * @param  string $upload_id  Upload session identifier.
	 * @param  int    $part       Part number.
	 * @param  string $body       Binary payload.
	 * @return string ETag header value.
	 */
	private function upload_part_with_retry( $remote_key, $upload_id, $part, $body ) {
		$attempt = 0;
		$last_error = null;

		while ( $attempt < AI1WM_S3_MAX_RETRIES ) {
			$attempt++;

			try {
				$response = $this->signed_request(
					'PUT',
					$remote_key,
					array(
						'partNumber' => (string) $part,
						'uploadId'   => $upload_id,
					),
					array(),
					$body
				);

				$this->guard_response( $response, __( 'Failed to upload multipart chunk.', AI1WM_PLUGIN_NAME ) );

				$etag = wp_remote_retrieve_header( $response, 'etag' );
				if ( empty( $etag ) ) {
					throw new Ai1wm_S3_Exception( __( 'S3 response did not include an ETag header.', AI1WM_PLUGIN_NAME ) );
				}

				return trim( $etag );
			} catch ( Exception $e ) {
				$last_error = $e;
				usleep( 250000 );
			}
		}

		if ( $last_error ) {
			throw $last_error;
		}

		throw new Ai1wm_S3_Exception( __( 'Multipart upload failed after maximum retries.', AI1WM_PLUGIN_NAME ) );
	}

	/**
	 * Complete multipart upload request.
	 *
	 * @param string $remote_key Object key.
	 * @param string $upload_id  Upload ID.
	 * @param array  $parts      Uploaded part descriptors.
	 */
	private function complete_multipart_upload( $remote_key, $upload_id, array $parts ) {
		$document = new SimpleXMLElement( '<CompleteMultipartUpload/>' );
		foreach ( $parts as $part ) {
			$node = $document->addChild( 'Part' );
			$node->addChild( 'PartNumber', (int) $part['PartNumber'] );
			$node->addChild( 'ETag', $part['ETag'] );
		}

		$body = $document->asXML();

		$response = $this->signed_request(
			'POST',
			$remote_key,
			array( 'uploadId' => $upload_id ),
			array( 'Content-Type' => 'application/xml' ),
			$body
		);

		$this->guard_response( $response, __( 'Failed to finalise multipart upload.', AI1WM_PLUGIN_NAME ) );
	}

	/**
	 * Abort multipart upload.
	 *
	 * @param string $remote_key Object key.
	 * @param string $upload_id  Upload ID to abort.
	 */
	private function abort_multipart_upload( $remote_key, $upload_id ) {
		$this->signed_request(
			'DELETE',
			$remote_key,
			array( 'uploadId' => $upload_id )
		);
	}

	/**
	 * Send signed request to S3 endpoint.
	 *
	 * @param string $method HTTP method.
	 * @param string $remote_key Object key relative to bucket.
	 * @param array  $query Query parameters.
	 * @param array  $headers Additional headers.
	 * @param string $body Request body.
	 *
	 * @return array|WP_Error
	 */
	private function signed_request( $method, $remote_key, array $query = array(), array $headers = array(), $body = '' ) {
		$datetime   = gmdate( 'Ymd\THis\Z' );
		$datestamp  = gmdate( 'Ymd' );
		$payload    = is_string( $body ) ? $body : '';
		$payload_hash = hash( 'sha256', $payload );

		$canonical = $this->build_canonical_request( $method, $remote_key, $query, $headers, $payload_hash, $datetime );
		$signature = $this->build_authorization_header( $canonical, $datestamp, $datetime, $headers );

		$request_headers = $canonical['headers'];
		$request_headers['Authorization'] = $signature;

		$args = array(
			'body'    => $payload,
			'headers' => $request_headers,
			'method'  => $method,
			'timeout' => 60,
		);

		$url = $this->build_request_url( $canonical['uri_path'], $canonical['query_string'] );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Ai1wm_S3_Exception( $response->get_error_message() );
		}

		return $response;
	}

	/**
	 * Validate status code and throw descriptive error.
	 *
	 * @param array|WP_Error $response Response payload.
	 * @param string         $message  Error message.
	 */
	private function guard_response( $response, $message ) {
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			throw new Ai1wm_S3_Exception( sprintf( '%s (%s)', $message, $body ? $body : $code ) );
		}
	}

	/**
	 * Build canonical request for signature v4.
	 *
	 * @param string $method
	 * @param string $remote_key
	 * @param array  $query
	 * @param array  $headers
	 * @param string $payload_hash
	 * @param string $datetime
	 *
	 * @return array
	 */
	private function build_canonical_request( $method, $remote_key, array $query, array $headers, $payload_hash, $datetime ) {
		list( $uri_path, $canonical_uri ) = $this->build_uris( $remote_key );

		$canonical_query = $this->build_canonical_query( $query );

		$base_headers = array(
			'host'                 => $this->build_host_header(),
			'x-amz-content-sha256' => $payload_hash,
			'x-amz-date'           => $datetime,
		);

		$headers = array_change_key_case( $headers, CASE_LOWER );
		$headers = array_merge( $base_headers, $headers );

		ksort( $headers );

		$canonical_headers = array();
		foreach ( $headers as $name => $value ) {
			$canonical_headers[] = $name . ':' . $this->trim_header_values( $value );
		}

		$signed_headers = implode( ';', array_keys( $headers ) );

		$canonical_request = implode( "\n", array(
			$method,
			$canonical_uri,
			$canonical_query,
			implode( "\n", $canonical_headers ) . "\n",
			$signed_headers,
			$payload_hash,
		) );

		return array(
			'canonical_request' => $canonical_request,
			'signed_headers'    => $signed_headers,
			'headers'           => $headers,
			'uri_path'          => $uri_path,
			'query_string'      => $canonical_query,
		);
	}

	/**
	 * Create authorization header value.
	 *
	 * @param array  $canonical Canonical data array.
	 * @param string $datestamp Date part.
	 * @param string $datetime  ISO8601 date.
	 * @param array  $headers   Request headers (unused but kept for signature context).
	 *
	 * @return string
	 */
	private function build_authorization_header( array $canonical, $datestamp, $datetime, array $headers ) {
		$credential_scope = implode( '/', array( $datestamp, $this->region, $this->service, 'aws4_request' ) );

		$string_to_sign = implode( "\n", array(
			'AWS4-HMAC-SHA256',
			$datetime,
			$credential_scope,
			hash( 'sha256', $canonical['canonical_request'] ),
		) );

		$signing_key = $this->get_signing_key( $datestamp );
		$signature   = hash_hmac( 'sha256', $string_to_sign, $signing_key );

		return sprintf(
			'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
			$this->access_key,
			$credential_scope,
			$canonical['signed_headers'],
			$signature
		);
	}

	/**
	 * Resolve signing key for date region scope.
	 *
	 * @param  string $datestamp Date string.
	 * @return string
	 */
	private function get_signing_key( $datestamp ) {
		$key    = 'AWS4' . $this->secret_key;
		$date   = hash_hmac( 'sha256', $datestamp, $key, true );
		$region = hash_hmac( 'sha256', $this->region, $date, true );
		$svc    = hash_hmac( 'sha256', $this->service, $region, true );

		return hash_hmac( 'sha256', 'aws4_request', $svc, true );
	}

	/**
	 * Calculate request URL from URI path and query string.
	 *
	 * @param  string $uri_path
	 * @param  string $query_string
	 * @return string
	 */
	private function build_request_url( $uri_path, $query_string ) {
		$url = $this->endpoint;

		if ( $this->use_path_style ) {
			$url .= $uri_path;
		} else {
			$url = $this->scheme . '://' . $this->bucket . '.' . $this->host . $this->port . $uri_path;
		}

		if ( $query_string !== '' ) {
			$url .= '?' . $query_string;
		}

		return $url;
	}

	/**
	 * Build URI path and canonical URI for signing.
	 *
	 * @param  string $remote_key
	 * @return array
	 */
	private function build_uris( $remote_key ) {
		$encoded_key = $this->encode_key( $remote_key );

		if ( $this->use_path_style ) {
			$uri_path = $this->base_path . '/' . rawurlencode( $this->bucket ) . '/' . $encoded_key;
		} else {
			$uri_path = $this->base_path . '/' . $encoded_key;
		}

		$uri_path = $this->trim_double_slashes( $uri_path );

		return array( $uri_path, $this->canonicalize_path( $uri_path ) );
	}

	/**
	 * Generate host header value.
	 *
	 * @return string
	 */
	private function build_host_header() {
		if ( $this->use_path_style ) {
			return $this->host . $this->port;
		}

		return $this->bucket . '.' . $this->host . $this->port;
	}

	/**
	 * Encode each path segment.
	 *
	 * @param  string $key Object key.
	 * @return string
	 */
	private function encode_key( $key ) {
		$segments = explode( '/', $key );
		$encoded  = array();

		foreach ( $segments as $segment ) {
			$encoded[] = rawurlencode( $segment );
		}

		return implode( '/', $encoded );
	}

	/**
	 * Build canonical path for signing.
	 *
	 * @param  string $path
	 * @return string
	 */
	private function canonicalize_path( $path ) {
		$segments = explode( '/', $path );
		$encoded  = array();

		foreach ( $segments as $index => $segment ) {
			if ( $segment === '' && $index === 0 ) {
				$encoded[] = '';
				continue;
			}

			$encoded[] = rawurlencode( $segment );
		}

		$canonical = implode( '/', $encoded );

		return $canonical === '' ? '/' : $canonical;
	}

	/**
	 * Construct canonical query string sorted by key/value.
	 *
	 * @param  array  $params
	 * @return string
	 */
	private function build_canonical_query( array $params ) {
		if ( empty( $params ) ) {
			return '';
		}

		$query = array();
		ksort( $params );

		foreach ( $params as $name => $value ) {
			$name = rawurlencode( $name );

			if ( is_array( $value ) ) {
				sort( $value );
				foreach ( $value as $item ) {
					$query[] = $name . '=' . rawurlencode( (string) $item );
				}
			} else {
				$value = (string) $value;
				$query[] = $name . '=' . rawurlencode( $value );
			}
		}

		return implode( '&', $query );
	}

	/**
	 * Normalise header values to single spaced strings.
	 *
	 * @param  mixed $value Header value.
	 * @return string
	 */
	private function trim_header_values( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}

		return preg_replace( '/\s+/', ' ', trim( (string) $value ) );
	}

	/**
	 * Remove duplicate slashes except protocol delimiter.
	 *
	 * @param  string $path
	 * @return string
	 */
	private function trim_double_slashes( $path ) {
		return preg_replace( '#(?<!:)//+#', '/', $path );
	}
}
