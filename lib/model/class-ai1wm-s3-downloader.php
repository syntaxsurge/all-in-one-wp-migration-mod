<?php
/**
 * Background downloader for S3 objects into local backups directory.
 */
class Ai1wm_S3_Downloader {

    /**
     * Queue a new download job.
     *
     * @param string $relative_key Object key relative to configured prefix.
     * @param string $preferred_filename Optional target filename.
     */
    public static function dispatch( $relative_key, $preferred_filename = '' ) {
        $relative_key = ltrim( str_replace( '\\', '/', (string) $relative_key ), '/' );

        if ( $relative_key === '' ) {
            throw new Ai1wm_S3_Exception( __( 'Missing object key.', AI1WM_PLUGIN_NAME ) );
        }

        // Ensure backups folder
        if ( ! is_dir( AI1WM_BACKUPS_PATH ) ) {
            if ( ! Ai1wm_Directory::create( AI1WM_BACKUPS_PATH ) ) {
                throw new Ai1wm_S3_Exception( sprintf( __( 'Unable to create backups folder: %s', AI1WM_PLUGIN_NAME ), AI1WM_BACKUPS_PATH ) );
            }
        }
        if ( ! is_writable( AI1WM_BACKUPS_PATH ) ) {
            throw new Ai1wm_S3_Exception( sprintf( __( 'Backups folder is not writable: %s', AI1WM_PLUGIN_NAME ), AI1WM_BACKUPS_PATH ) );
        }

        $filename = $preferred_filename !== '' ? basename( $preferred_filename ) : basename( $relative_key );
        if ( stripos( $filename, '.wpress' ) === false ) {
            // enforce extension if missing
            $filename .= '.wpress';
        }

        $target = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $filename;
        // Avoid overwriting existing files
        if ( file_exists( $target ) ) {
            $base = preg_replace( '/\.wpress$/i', '', $filename );
            $i = 1;
            do {
                $alt = sprintf( '%s-(%d).wpress', $base, $i );
                $target = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $alt;
                $i++;
            } while ( file_exists( $target ) && $i < 1000 );
        }

        $temp = $target . '.part';

        Ai1wm_S3_Download_Status::update( $relative_key, 'queued', __( 'Download scheduled.', AI1WM_PLUGIN_NAME ), array(
            'filename'   => basename( $target ),
            'target'     => $target,
            'bytes_total'=> 0,
            'bytes_done' => 0,
        ) );

        if ( ! wp_next_scheduled( AI1WM_S3_DL_CRON_HOOK, array( $relative_key, $target ) ) ) {
            wp_schedule_single_event( time(), AI1WM_S3_DL_CRON_HOOK, array( $relative_key, $target ) );
        }

        if ( function_exists( 'spawn_cron' ) ) {
            spawn_cron();
        }
    }

    /**
     * Cancel a running/queued job.
     */
    public static function cancel( $relative_key ) {
        $relative_key = ltrim( str_replace( '\\', '/', (string) $relative_key ), '/' );
        $status = Ai1wm_S3_Download_Status::get( $relative_key );
        Ai1wm_S3_Download_Status::request_cancel( $relative_key );

        // Attempt cleanup of partial file if exists
        $temp = isset( $status['target'] ) ? ( $status['target'] . '.part' ) : '';
        if ( $temp && file_exists( $temp ) ) {
            @unlink( $temp );
        }
        Ai1wm_S3_Download_Status::update( $relative_key, 'cancelled', __( 'Download cancelled.', AI1WM_PLUGIN_NAME ) );
    }

    /**
     * Cron runner.
     *
     * @param string $relative_key
     * @param string $target
     */
    public static function run( $relative_key, $target ) {
        $relative_key = ltrim( str_replace( '\\', '/', (string) $relative_key ), '/' );
        $target       = (string) $target;

        ignore_user_abort( true );
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 );
        }

        $temp = $target . '.part';

        try {
            $settings = Ai1wm_S3_Settings::get();
            $client   = new Ai1wm_S3_Client( $settings );

            // Determine object size
            $bytes_total = 0;
            try {
                $bytes_total = self::get_object_size( $client, $relative_key );
            } catch ( Exception $e ) {
                $bytes_total = 0; // proceed and try to infer from first chunk
            }

            Ai1wm_S3_Download_Status::update( $relative_key, 'in_progress', __( 'Downloading from S3...', AI1WM_PLUGIN_NAME ), array(
                'bytes_total' => (int) $bytes_total,
                'bytes_done'  => 0,
                'target'      => $target,
                'filename'    => basename( $target ),
            ) );

            $chunk_size = (int) AI1WM_S3_DOWNLOAD_CHUNK_SIZE;
            $offset     = 0;

            // Resume if partial exists (best effort)
            if ( file_exists( $temp ) ) {
                $offset = filesize( $temp );
            }

            $out = @fopen( $temp, $offset > 0 ? 'ab' : 'wb' );
            if ( ! $out ) {
                throw new Ai1wm_S3_Exception( __( 'Unable to open local file for writing.', AI1WM_PLUGIN_NAME ) );
            }

            try {
                while ( true ) {
                    $st = Ai1wm_S3_Download_Status::get( $relative_key );
                    if ( ! empty( $st['cancel_requested'] ) ) {
                        @fclose( $out );
                        @unlink( $temp );
                        Ai1wm_S3_Download_Status::update( $relative_key, 'cancelled', __( 'Download cancelled.', AI1WM_PLUGIN_NAME ) );
                        return;
                    }

                    $range_end = $offset + $chunk_size - 1;
                    $headers = array( 'Range' => 'bytes=' . $offset . '-' . $range_end );
                    $request = self::prepare_signed_get( $client, $relative_key, array(), $headers );
                    $args = array(
                        'headers' => $request['headers'],
                        'method'  => 'GET',
                        'timeout' => 120,
                    );
                    $response = wp_remote_request( $request['url'], $args );
                    $code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
                    if ( is_wp_error( $response ) || ( $code < 200 || $code >= 300 ) ) {
                        $message = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
                        throw new Ai1wm_S3_Exception( sprintf( __( 'Download failed: %s', AI1WM_PLUGIN_NAME ), $message ) );
                    }

                    $body = wp_remote_retrieve_body( $response );
                    if ( $body === '' ) {
                        // No more data
                        break;
                    }

                    $written = fwrite( $out, $body );
                    if ( $written === false ) {
                        throw new Ai1wm_S3_Exception( __( 'Unable to write to local file.', AI1WM_PLUGIN_NAME ) );
                    }
                    $offset += strlen( $body );

                    // Try infer total size from Content-Range
                    if ( $bytes_total <= 0 ) {
                        $cr = wp_remote_retrieve_header( $response, 'content-range' );
                        if ( $cr && preg_match( '/\/(\d+)$/', (string) $cr, $m ) ) {
                            $bytes_total = (int) $m[1];
                        }
                    }

                    Ai1wm_S3_Download_Status::update( $relative_key, 'in_progress', '', array(
                        'bytes_done'  => (int) $offset,
                        'bytes_total' => (int) $bytes_total,
                    ) );

                    // Stop if we reached total (when known)
                    if ( $bytes_total > 0 && $offset >= $bytes_total ) {
                        break;
                    }

                    // If response smaller than chunk, assume end
                    if ( strlen( $body ) < $chunk_size ) {
                        break;
                    }
                }
            } finally {
                @fclose( $out );
            }

            // Finalize
            if ( ! @rename( $temp, $target ) ) {
                // Fallback: copy then unlink
                if ( ! @copy( $temp, $target ) ) {
                    throw new Ai1wm_S3_Exception( __( 'Unable to finalize downloaded file.', AI1WM_PLUGIN_NAME ) );
                }
                @unlink( $temp );
            }

            Ai1wm_S3_Download_Status::update( $relative_key, 'success', __( 'Download completed successfully.', AI1WM_PLUGIN_NAME ), array(
                'bytes_done'  => (int) filesize( $target ),
                'bytes_total' => (int) filesize( $target ),
            ) );
        } catch ( Exception $e ) {
            // Cleanup partial
            if ( file_exists( $temp ) ) {
                @unlink( $temp );
            }
            Ai1wm_S3_Download_Status::update( $relative_key, 'failed', $e->getMessage() );
        }
    }

    private static function get_object_size( Ai1wm_S3_Client $client, $relative_key ) {
        $request = self::prepare_signed_get( $client, $relative_key, array(), array( 'Range' => 'bytes=0-0' ) );
        $response = wp_remote_request( $request['url'], array( 'headers' => $request['headers'], 'method' => 'GET', 'timeout' => 30 ) );
        if ( is_wp_error( $response ) ) {
            throw new Ai1wm_S3_Exception( $response->get_error_message() );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            throw new Ai1wm_S3_Exception( wp_remote_retrieve_body( $response ) ?: (string) $code );
        }
        $cr = wp_remote_retrieve_header( $response, 'content-range' );
        if ( $cr && preg_match( '/\/(\d+)$/', (string) $cr, $m ) ) {
            return (int) $m[1];
        }
        $cl = wp_remote_retrieve_header( $response, 'content-length' );
        if ( $cl ) {
            return (int) $cl;
        }
        return 0;
    }

    private static function prepare_signed_get( Ai1wm_S3_Client $client, $relative_key, array $query = array(), array $headers = array() ) {
        // Use S3 client internals to build signed request without exposing private methods.
        // We can call a lightweight wrapper via reflection is not desired; instead reuse prepare_signed_request via a public proxy.
        // For simplicity, we piggyback on upload signing fields using a small helper here.

        // Since Ai1wm_S3_Client::prepare_signed_request is private, we replicate minimal needed path using object URL + headers from build pipeline
        // However to avoid duplication, we reuse existing private via a small subclass-like helper is not feasible.
        // We therefore expose a signed GET by calling a small accessor method added: using object_url then signing. As a fallback,
        // we can build using reflection. Simpler approach: construct a new signed request using public methods available
        // Not available; but we can improvise by requesting unsigned if endpoint allows public? No.

        // Instead of accessing private, we call this class's sibling: we can leverage Ai1wm_S3_Client::object_url via settings but signatures are required.
        // We will call a dedicated method via call_user_func to access private prepare_signed_request; PHP allows closure binding hack:
        $fn = function( $method, $relative_key, $query, $headers ) {
            $remote_key = $this->prefix . ltrim( $relative_key, '/' );
            return $this->prepare_signed_request( $method, $remote_key, $query, $headers, '' );
        };
        $bound = \Closure::bind( $fn, $client, get_class( $client ) );
        $req = $bound( 'GET', $relative_key, $query, $headers );
        return $req;
    }
}
