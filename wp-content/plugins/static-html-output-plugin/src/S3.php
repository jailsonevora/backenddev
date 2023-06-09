<?php

namespace StaticHTMLOutput;

class S3 extends SitePublisher {

    /**
     * @var int
     */
    public $files_remaining;
    /**
     * @var string
     */
    public $target_path;
    /**
     * @var string
     */
    public $local_file_contents;
    /**
     * @var string
     */
    public $hash_key;

    public function __construct() {
        $this->loadSettings( 's3' );

        $this->previous_hashes_path =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-S3-PREVIOUS-HASHES.txt';

        if ( defined( 'WP_CLI' ) ) {
            return; }

        switch ( $_POST['ajax_action'] ) {
            case 'test_s3':
                $this->test_s3();
                break;
            case 's3_prepare_export':
                $this->bootstrap();
                $this->loadArchive();
                $this->prepareDeploy();
                break;
            case 's3_transfer_files':
                $this->bootstrap();
                $this->loadArchive();
                $this->upload_files();
                break;
            case 'cloudfront_invalidate_all_items':
                $this->cloudfront_invalidate_all_items();
                break;
        }
    }

    public function upload_files() : void {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining < 0 ) {
            echo 'ERROR';
            die(); }

        $this->initiateProgressIndicator();

        $batch_size = $this->settings['deployBatchSize'];

        if ( $batch_size > $this->files_remaining ) {
            $batch_size = $this->files_remaining;
        }

        $lines = $this->getItemsToDeploy( $batch_size );

        $this->openPreviousHashesFile();

        foreach ( $lines as $line ) {
            list($local_file, $this->target_path) = explode( ',', $line );

            $local_file = $this->archive->path . $local_file;

            if ( ! is_file( $local_file ) ) {
                continue; }

            if ( isset( $this->settings['s3RemotePath'] ) ) {
                $this->target_path =
                    $this->settings['s3RemotePath'] . '/' . $this->target_path;
            }

            $this->local_file_contents = (string) file_get_contents( $local_file );

            if ( ! $this->local_file_contents ) {
                continue;
            }

            $this->hash_key = $this->target_path . basename( $local_file );

            if ( isset( $this->file_paths_and_hashes[ $this->hash_key ] ) ) {
                $prev = $this->file_paths_and_hashes[ $this->hash_key ];
                $current = crc32( $this->local_file_contents );

                if ( $prev != $current ) {
                    try {
                        $this->put_s3_object(
                            $this->target_path .
                                    basename( $local_file ),
                            $this->local_file_contents,
                            MimeTypes::guess_type( $local_file )
                        );

                    } catch ( StaticHTMLOutputException $e ) {
                        $this->handleException( $e );
                    }
                }
            } else {
                try {
                    $this->put_s3_object(
                        $this->target_path .
                                basename( $local_file ),
                        $this->local_file_contents,
                        MimeTypes::guess_type( $local_file )
                    );

                } catch ( StaticHTMLOutputException $e ) {
                    $this->handleException( $e );
                }
            }

            $this->recordFilePathAndHashInMemory(
                $this->hash_key,
                $this->local_file_contents
            );

            $this->updateProgress();
        }

        $this->writeFilePathAndHashesToFile();

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function test_s3() : void {
        try {
            $this->put_s3_object(
                '.tmp_statichtmloutput.txt',
                'Test StaticHTMLOutput connectivity',
                'text/plain'
            );

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        } catch ( StaticHTMLOutputException $e ) {
            WsLog::l( 'S3 ERROR RETURNED: ' . $e );
            echo "There was an error testing S3.\n";
        }
    }

    public function put_s3_object(
        string $s3_path,
        string $content,
        string $content_type
    ) : void {
        // NOTE: quick fix for #287
        $s3_path = str_replace( '@', '%40', $s3_path );

        $host_name = $this->settings['s3Bucket'] . '.s3.' .
            $this->settings['s3Region'] . '.amazonaws.com';

        $content_acl = 'public-read';
        $content_title = $s3_path;
        $aws_service_name = 's3';
        $timestamp = gmdate( 'Ymd\THis\Z' );
        $date = gmdate( 'Ymd' );

        // HTTP request headers as key & value
        $request_headers = [];
        $request_headers['Content-Type'] = $content_type;
        $request_headers['Date'] = $timestamp;
        $request_headers['Host'] = $host_name;
        $request_headers['x-amz-acl'] = $content_acl;
        $request_headers['x-amz-content-sha256'] = hash( 'sha256', $content );

        // Sort it in ascending order
        ksort( $request_headers );

        $canonical_headers = [];

        foreach ( $request_headers as $key => $value ) {
            $canonical_headers[] = strtolower( $key ) . ':' . $value;
        }

        $canonical_headers = implode( "\n", $canonical_headers );

        $signed_headers = [];

        foreach ( $request_headers as $key => $value ) {
            $signed_headers[] = strtolower( $key );
        }

        $signed_headers = implode( ';', $signed_headers );

        $canonical_request = [];
        $canonical_request[] = 'PUT';
        $canonical_request[] = '/' . $content_title;
        $canonical_request[] = '';
        $canonical_request[] = $canonical_headers;
        $canonical_request[] = '';
        $canonical_request[] = $signed_headers;
        $canonical_request[] = hash( 'sha256', $content );
        $canonical_request = implode( "\n", $canonical_request );
        $hashed_canonical_request = hash( 'sha256', $canonical_request );

        $scope = [];
        $scope[] = $date;
        $scope[] = $this->settings['s3Region'];
        $scope[] = $aws_service_name;
        $scope[] = 'aws4_request';

        $string_to_sign = [];
        $string_to_sign[] = 'AWS4-HMAC-SHA256';
        $string_to_sign[] = $timestamp;
        $string_to_sign[] = implode( '/', $scope );
        $string_to_sign[] = $hashed_canonical_request;
        $string_to_sign = implode( "\n", $string_to_sign );

        // Signing key
        $k_secret = 'AWS4' . $this->settings['s3Secret'];
        $k_date = hash_hmac( 'sha256', $date, $k_secret, true );
        $k_region =
            hash_hmac( 'sha256', $this->settings['s3Region'], $k_date, true );
        $k_service = hash_hmac( 'sha256', $aws_service_name, $k_region, true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );

        $signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

        $authorization = [
            'Credential=' . $this->settings['s3Key'] . '/' .
                implode( '/', $scope ),
            'SignedHeaders=' . $signed_headers,
            'Signature=' . $signature,
        ];

        $authorization =
            'AWS4-HMAC-SHA256' . ' ' . implode( ',', $authorization );

        $curl_headers = [ 'Authorization: ' . $authorization ];

        foreach ( $request_headers as $key => $value ) {
            $curl_headers[] = $key . ': ' . $value;
        }

        $url = 'http://' . $host_name . '/' . $content_title;

        $ch = curl_init( $url );

        if ( ! is_resource( $ch ) ) {
            return;
        }

        curl_setopt( $ch, CURLOPT_HEADER, false );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $curl_headers );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
        curl_setopt( $ch, CURLOPT_USERAGENT, 'StaticHTMLOutput.com' );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $content );

        $output = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        $this->checkForValidResponses(
            $http_code,
            [ 200 ]
        );

        curl_close( $ch );
    }

    public function cloudfront_invalidate_all_items() : void {
        WsLog::l( 'Invalidating all CloudFront items' );

        if ( ! isset( $this->settings['cfDistributionId'] ) ) {
            WsLog::l( 'no Cloudfront ID found' );

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS'; }

            return;
        }

        $distribution = $this->settings['cfDistributionId'];
        $access_key = $this->settings['s3Key'];
        $secret_key = $this->settings['s3Secret'];

        $epoch = gmdate( 'U' );

        $xml = <<<EOD
<InvalidationBatch>
    <Path>/*</Path>
    <CallerReference>{$distribution}{$epoch}</CallerReference>
</InvalidationBatch>
EOD;

        $len = strlen( $xml );
        $date = gmdate( 'D, d M Y G:i:s T' );
        $sig = base64_encode(
            hash_hmac( 'sha1', $date, $secret_key, true )
        );
        $msg = 'POST /2010-11-01/distribution/';
        $msg .= "{$distribution}/invalidation HTTP/1.0\r\n";
        $msg .= "Host: cloudfront.amazonaws.com\r\n";
        $msg .= "Date: {$date}\r\n";
        $msg .= "Content-Type: text/xml; charset=UTF-8\r\n";
        $msg .= "Authorization: AWS {$access_key}:{$sig}\r\n";
        $msg .= "Content-Length: {$len}\r\n\r\n";
        $msg .= $xml;
        $fp = fsockopen(
            'ssl://cloudfront.amazonaws.com',
            443,
            $errno,
            $errstr,
            30
        );

        if ( ! $fp ) {
            WsLog::l( "CLOUDFRONT CONNECTION ERROR: {$errno} {$errstr}" );
            die( "Connection failed: {$errno} {$errstr}\n" );
        }

        fwrite( $fp, $msg );
        $resp = '';

        while ( ! feof( $fp ) ) {
            $resp .= fgets( $fp, 1024 );
        }

        WsLog::l( "CloudFront response body: {$resp}" );

        fclose( $fp );

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }
}

