<?php

namespace StaticHTMLOutput;

abstract class SitePublisher {

    /**
     * @var mixed[]
     */
    public $settings;
    /**
     * @var string
     */
    public $export_file_list;
    /**
     * @var string
     */
    public $archive_dir;
    /**
     * @var int
     */
    public $batch_index;
    /**
     * @var int
     */
    public $total_urls_to_crawl;
    /**
     * @var int
     */
    public $files_remaining;
    /**
     * @var string
     */
    public $deploy_count_path;
    /**
     * @var mixed[]
     */
    public $file_paths_and_hashes;
    /**
     * @var string
     */
    public $previous_hashes_path;
    /**
     * @var Archive
     */
    public $archive;

    abstract public function upload_files() : void;

    public function loadSettings( string $deploy_method ) : void {
        $target_settings = [
            'general',
            'wpenv',
            'advanced',
        ];

        $target_settings[] = $deploy_method;

        if ( isset( $_POST['selected_deployment_option'] ) ) {
            $this->settings = PostSettings::get( $target_settings );
        } else {
            $this->settings = DBSettings::get( $target_settings );
        }
    }

    public function loadArchive() : void {
        $this->archive = new Archive();
        $this->archive->setToCurrentArchive();
    }

    public function bootstrap() : void {
        $this->export_file_list =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-FILES-TO-DEPLOY.txt';

        $this->archive_dir = (string) file_get_contents(
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-CURRENT-ARCHIVE.txt'
        );
    }

    public function pauseBetweenAPICalls() : void {
        if ( isset( $this->settings['delayBetweenAPICalls'] ) &&
            $this->settings['delayBetweenAPICalls'] > 0 ) {
            sleep( $this->settings['delayBetweenAPICalls'] );
        }
    }

    public function updateProgress() : void {
        $this->batch_index++;

        $completed_urls =
            $this->total_urls_to_crawl -
            $this->files_remaining +
            $this->batch_index;

        ProgressLog::l( $completed_urls, $this->total_urls_to_crawl );
    }

    public function initiateProgressIndicator() : void {
        $this->deploy_count_path = $this->settings['wp_uploads_path'] .
                '/WP-STATIC-TOTAL-FILES-TO-DEPLOY.txt';
        $this->total_urls_to_crawl =
            (int) file_get_contents( $this->deploy_count_path );

        $this->batch_index = 0;
    }


    public function clearFileList() : void {
        if ( is_file( $this->export_file_list ) ) {
            $f = fopen( $this->export_file_list, 'r+' );

            if ( ! is_resource( $f ) ) {
                return;
            }

            if ( $f !== false ) {
                ftruncate( $f, 0 );
                fclose( $f );
            }
        }

        if ( isset( $this->glob_hash_path_list ) ) {
            if ( is_file( $this->glob_hash_path_list ) ) {
                $f = fopen( $this->glob_hash_path_list, 'r+' );

                if ( ! is_resource( $f ) ) {
                    return;
                }

                if ( $f !== false ) {
                    ftruncate( $f, 0 );
                    fclose( $f );
                }
            }
        }
    }

    public function isSkippableFile( string $file ) : bool {
        if ( $file == '.' || $file == '..' || $file == '.git' ) {
            return true;
        }

        return false;
    }

    public function getLocalFileToDeploy( string $file_in_archive, string $replace_path ) : string {
        // NOTE: untested fix for Windows filepaths
        // https://github.com/leonstafford/statichtmloutput/issues/221
        $original_filepath = str_replace(
            '\\',
            '\\\\',
            $file_in_archive
        );

        $original_file_without_archive = str_replace(
            $replace_path,
            '',
            $original_filepath
        );

        $original_file_without_archive = ltrim(
            $original_file_without_archive,
            '/'
        );

        return $original_file_without_archive;
    }

    public function getArchivePathForReplacement( string $archive_path ) : string {
        $local_path_to_strip = $archive_path;
        $local_path_to_strip = rtrim( $local_path_to_strip, '/' );

        $local_path_to_strip = str_replace(
            '//',
            '/',
            $local_path_to_strip
        );

        return $local_path_to_strip;
    }

    public function getRemoteDeploymentPath(
        string $dir,
        string $file_in_archive,
        string $archive_path_to_replace,
        bool $basename_in_target
        ) : string {
        $deploy_path = str_replace(
            $archive_path_to_replace,
            '',
            $dir
        );

        $deploy_path = ltrim( $deploy_path, '/' );
        $deploy_path .= '/';

        if ( $basename_in_target ) {
            $deploy_path .= basename(
                $file_in_archive
            );
        }

        $deploy_path = ltrim( $deploy_path, '/' );

        return $deploy_path;
    }

    public function createDeploymentList( string $dir, bool $basename_in_target ) : void {
        $deployable_files = scandir( $dir );

        if ( ! $deployable_files ) {
            return;
        }

        $archive_path_to_replace =
            $this->getArchivePathForReplacement( $this->archive->path );

        foreach ( $deployable_files as $item ) {
            if ( $this->isSkippableFile( $item ) ) {
                continue;
            }

            $file_in_archive = $dir . '/' . $item;

            if ( is_dir( $file_in_archive ) ) {
                $this->createDeploymentList(
                    $file_in_archive,
                    $basename_in_target
                );
            } elseif ( is_file( $file_in_archive ) ) {
                $local_file_path =
                    $this->getLocalFileToDeploy(
                        $file_in_archive,
                        $archive_path_to_replace
                    );

                $remote_deployment_path =
                    $this->getRemoteDeploymentPath(
                        $dir,
                        $file_in_archive,
                        $archive_path_to_replace,
                        $basename_in_target
                    );

                file_put_contents(
                    $this->export_file_list,
                    $local_file_path . ',' . $remote_deployment_path . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );

                chmod( $this->export_file_list, 0664 );
            }
        }
    }

    public function prepareDeploy( bool $basename_in_target = false ) : void {
        $this->clearFileList();

        $this->createDeploymentList(
            $this->settings['wp_uploads_path'] . '/' . $this->archive->name,
            $basename_in_target
        );

        // TODO: detect and use `cat | wc -l` if available
        $linecount = 0;
        $handle = fopen( $this->export_file_list, 'r' );

        if ( ! is_resource( $handle ) ) {
            return;
        }

        while ( ! feof( $handle ) ) {
            $line = fgets( $handle );
            $linecount++;
        }

        fclose( $handle );

        $deploy_count_path = $this->settings['wp_uploads_path'] .
                '/WP-STATIC-TOTAL-FILES-TO-DEPLOY.txt';

        file_put_contents(
            $deploy_count_path,
            $linecount,
            LOCK_EX
        );

        chmod( $deploy_count_path, 0664 );

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    /**
     * @return string[] list of files to deploy
     */
    public function getItemsToDeploy( int $batch_size = 1 ) : array {
        $lines = [];

        $f = fopen( $this->export_file_list, 'r' );

        if ( ! is_resource( $f ) ) {
            return [];
        }

        for ( $i = 0; $i < $batch_size; $i++ ) {
            $file_list = fgets( $f );

            if ( ! $file_list ) {
                return [];
            }

            $lines[] = rtrim( $file_list );
        }

        fclose( $f );

        // TODO: optimize this for just one read, one write within func
        $contents = file( $this->export_file_list, FILE_IGNORE_NEW_LINES );

        if ( ! $contents ) {
            return [];
        }

        for ( $i = 0; $i < $batch_size; $i++ ) {
            // rewrite file minus the lines we took
            array_shift( $contents );
        }

        file_put_contents(
            $this->export_file_list,
            implode( PHP_EOL, $contents )
        );

        chmod( $this->export_file_list, 0664 );

        return $lines;
    }

    public function getRemainingItemsCount() : int {
        $contents = file( $this->export_file_list, FILE_IGNORE_NEW_LINES );

        if ( ! is_array( $contents ) ) {
            return 0;
        }

        return count( $contents );
    }

    // TODO: rename to signalSuccessfulAction or such
    // as is used in deployment tests/not just finalizing deploys
    public function finalizeDeployment() : void {
        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS'; }
    }

    public function uploadsCompleted() : bool {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining <= 0 ) {
            return true;
        } else {
            if ( defined( 'WP_CLI' ) ) {
                $this->upload_files();
            } else {
                echo $this->files_remaining;
            }

            return false;
        }
    }

    /**
     * @throws StaticHTMLOutputException
     */
    public function handleException( string $e ) : void {
        WsLog::l( 'Deployment: error encountered' );
        WsLog::l( $e );
        throw new StaticHTMLOutputException( $e );
    }

    /**
     * @param int[] $good_codes valid HTTP response codes
     * @throws StaticHTMLOutputException
     */
    public function checkForValidResponses( int $code, array $good_codes ) : void {
        if ( ! in_array( $code, $good_codes ) ) {
            WsLog::l(
                'BAD RESPONSE STATUS FROM API (' . $code . ')'
            );

            http_response_code( $code );

            throw new StaticHTMLOutputException(
                'BAD RESPONSE STATUS FROM API (' . $code . ')'
            );
        }
    }

    public function openPreviousHashesFile() : void {
        $this->file_paths_and_hashes = [];

        if ( is_file( $this->previous_hashes_path ) ) {
            $file = fopen( $this->previous_hashes_path, 'r' );

            if ( ! is_resource( $file ) ) {
                return;
            }

            while ( ( $line = fgetcsv( $file ) ) !== false ) {
                if ( isset( $line[0] ) && isset( $line[1] ) ) {
                    $this->file_paths_and_hashes[ $line[0] ] = $line[1];
                }
            }

            fclose( $file );
        }
    }

    public function recordFilePathAndHashInMemory(
        string $target_path,
        string $local_file_contents
        ) : void {
        $this->file_paths_and_hashes[ $target_path ] =
            crc32( $local_file_contents );
    }

    public function writeFilePathAndHashesToFile() : void {
        $fp = fopen( $this->previous_hashes_path, 'w' );

        if ( ! is_resource( $fp ) ) {
            return;
        }

        foreach ( $this->file_paths_and_hashes as $key => $value ) {
            fwrite( $fp, $key . ',' . $value . PHP_EOL );
        }

        fclose( $fp );
    }
}

