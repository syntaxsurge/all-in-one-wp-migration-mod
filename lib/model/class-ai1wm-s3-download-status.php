<?php
/**
 * Tracks background download status for S3 objects.
 */
class Ai1wm_S3_Download_Status {

    /**
     * Get all statuses keyed by relative object key.
     *
     * @return array
     */
    public static function all() {
        $stored = get_option( AI1WM_S3_DL_STATUS_OPTION, array() );
        if ( ! is_array( $stored ) ) {
            return array();
        }

        $dirty = false;
        foreach ( $stored as $key => $status ) {
            $normalized = self::normalize_status( $key, $status );
            if ( $normalized !== $status ) {
                $stored[ $key ] = $normalized;
                $dirty = true;
            }
        }

        if ( $dirty ) {
            update_option( AI1WM_S3_DL_STATUS_OPTION, $stored, false );
        }

        return $stored;
    }

    /**
     * Get single status.
     *
     * @param  string $key Relative object key.
     * @return array
     */
    public static function get( $key ) {
        $key = self::normalize_key( $key );
        $all = self::all();
        if ( isset( $all[ $key ] ) ) {
            return self::normalize_status( $key, $all[ $key ] );
        }

        return self::defaults();
    }

    /**
     * Update status entry.
     *
     * @param string $key
     * @param string $state queued|in_progress|success|failed|cancelled
     * @param string $message
     * @param array  $extra   Additional fields to merge.
     */
    public static function update( $key, $state, $message = '', array $extra = array() ) {
        $key = self::normalize_key( $key );
        $all = self::all();
        $current = isset( $all[ $key ] ) ? $all[ $key ] : array();

        $entry = array_merge( self::defaults(), $current, array(
            'state'      => (string) $state,
            'message'    => (string) $message,
            'updated_at' => time(),
        ), $extra );

        $all[ $key ] = $entry;
        update_option( AI1WM_S3_DL_STATUS_OPTION, $all, false );
    }

    /**
     * Mark a job as cancel requested.
     */
    public static function request_cancel( $key ) {
        $key = self::normalize_key( $key );
        $all = self::all();
        $current = isset( $all[ $key ] ) ? $all[ $key ] : self::defaults();
        $current['cancel_requested'] = true;
        $current['updated_at'] = time();
        $all[ $key ] = $current;
        update_option( AI1WM_S3_DL_STATUS_OPTION, $all, false );
    }

    /**
     * Remove status entry.
     */
    public static function delete( $key ) {
        $key = self::normalize_key( $key );
        $all = self::all();
        if ( isset( $all[ $key ] ) ) {
            unset( $all[ $key ] );
            update_option( AI1WM_S3_DL_STATUS_OPTION, $all, false );
        }
    }

    private static function normalize_status( $key, $status ) {
        if ( ! is_array( $status ) ) {
            $status = array();
        }

        $status = array_merge( self::defaults(), $status );

        $status['key']    = self::normalize_key( $key );
        $status['state']  = (string) $status['state'];
        $status['message']= (string) $status['message'];
        $status['target'] = (string) $status['target'];
        $status['filename'] = (string) $status['filename'];
        $status['bytes_total'] = (int) $status['bytes_total'];
        $status['bytes_done']  = (int) $status['bytes_done'];
        $status['updated_at']  = (int) $status['updated_at'];
        $status['cancel_requested'] = ! empty( $status['cancel_requested'] );

        return $status;
    }

    private static function defaults() {
        return array(
            'key'              => '',
            'state'            => '',
            'message'          => '',
            'filename'         => '',
            'target'           => '',
            'bytes_total'      => 0,
            'bytes_done'       => 0,
            'updated_at'       => 0,
            'cancel_requested' => false,
        );
    }

    private static function normalize_key( $key ) {
        $key = ltrim( str_replace( '\\', '/', (string) $key ), '/' );
        return $key;
    }
}

