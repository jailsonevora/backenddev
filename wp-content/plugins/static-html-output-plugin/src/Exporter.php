<?php

namespace StaticHTMLOutput;

class Exporter extends StaticHTMLOutput {

    public function __construct() {
        $this->loadSettings(
            [
                'wpenv',
                'crawling',
                'advanced',
            ]
        );
    }

    public function pre_export_cleanup() : void {
        $files_to_clean = [
            'WP-STATIC-2ND-CRAWL-LIST.txt',
            'WP-STATIC-404-LOG.txt',
            'WP-STATIC-CRAWLED-LINKS.txt',
            'WP-STATIC-DISCOVERED-URLS-LOG.txt',
            'WP-STATIC-DISCOVERED-URLS.txt',
            'WP2STATIC-FILES-TO-DEPLOY.txt',
            'WP-STATIC-EXPORT-LOG.txt',
            'WP-STATIC-FINAL-2ND-CRAWL-LIST.txt',
            'WP-STATIC-FINAL-CRAWL-LIST.txt',
            'WP2STATIC-GITLAB-FILES-IN-REPO.txt',
        ];

        foreach ( $files_to_clean as $file_to_clean ) {
            if ( file_exists(
                $this->settings['wp_uploads_path'] . '/' . $file_to_clean
            ) ) {
                unlink(
                    $this->settings['wp_uploads_path'] . '/' .
                        $file_to_clean
                );
            }
        }
    }

    public function cleanup_working_files() : void {
        // keep log files here for debugging
        // skip first export state
        if ( is_file(
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-CURRENT-ARCHIVE.txt'
        ) ) {

            $handle = fopen(
                $this->settings['wp_uploads_path'] .
                    '/WP2STATIC-CURRENT-ARCHIVE.txt',
                'r'
            );

            if ( is_resource( $handle ) ) {
                // TODO: looks like a random place for this...
                $this->settings['archive_dir'] = stream_get_line( $handle, 0 );
            }
        }

        $files_to_clean = [
            '/WP-STATIC-2ND-CRAWL-LIST.txt',
            '/WP-STATIC-CRAWLED-LINKS.txt',
            '/WP-STATIC-DISCOVERED-URLS.txt',
            '/WP2STATIC-FILES-TO-DEPLOY.txt',
            '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt',
            '/WP-STATIC-FINAL-CRAWL-LIST.txt',
            '/WP2STATIC-GITLAB-FILES-IN-REPO.txt',
        ];

        foreach ( $files_to_clean as $file_to_clean ) {
            if ( file_exists(
                $this->settings['wp_uploads_path'] . '/' . $file_to_clean
            ) ) {
                unlink(
                    $this->settings['wp_uploads_path'] . '/' . $file_to_clean
                );
            }
        }
    }

    public function initialize_cache_files() : void {
        // TODO: is this still necessary?
        $crawled_links_file =
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-CRAWLED-LINKS.txt';

        $resource = fopen( $crawled_links_file, 'w' );

        if ( ! is_resource( $resource ) ) {
            return;
        }

        fwrite( $resource, '' );
        fclose( $resource );
    }

    public function cleanup_leftover_archives() : void {
        $upload_dir_paths = scandir( $this->settings['wp_uploads_path'] );

        if ( ! $upload_dir_paths ) {
            return;
        }

        $leftover_files =
            preg_grep(
                '/^([^.])/',
                $upload_dir_paths
            );

        foreach ( $leftover_files as $filename ) {
            if ( strpos( $filename, 'wp-static-html-output-' ) !== false ) {
                $deletion_target = $this->settings['wp_uploads_path'] .
                    '/' . $filename;
                if ( is_dir( $deletion_target ) ) {
                    FilesHelper::delete_dir_with_files(
                        $deletion_target
                    );
                } else {
                    unlink( $deletion_target );
                }
            }
        }

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function generateModifiedFileList() : void {
        // preserve the initial crawl list, to be used in debugging + more
        copy(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-INITIAL-CRAWL-LIST.txt',
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-MODIFIED-CRAWL-LIST.txt'
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-MODIFIED-CRAWL-LIST.txt',
            0664
        );

        // if no excludes or includes, just copy to new target
        if ( ! isset( $this->settings['excludeURLs'] ) &&
            ! isset( $this->settings['additionalUrls'] ) ) {
            copy(
                $this->settings['wp_uploads_path'] .
                    '/WP-STATIC-INITIAL-CRAWL-LIST.txt',
                $this->settings['wp_uploads_path'] .
                    '/WP-STATIC-FINAL-CRAWL-LIST.txt'
            );

            return;
        }

        $modified_crawl_list = [];

        // load crawl list into array
        $crawl_list = file(
            $this->settings['wp_uploads_path'] .
            '/WP-STATIC-MODIFIED-CRAWL-LIST.txt'
        );

        if ( ! $crawl_list ) {
            return;
        }

        // applying exclusions before inclusions
        if ( isset( $this->settings['excludeURLs'] ) ) {
            $exclusions = explode(
                "\n",
                str_replace( "\r", '', $this->settings['excludeURLs'] )
            );

            // iterate through crawl list and add any that aren't excluded
            foreach ( $crawl_list as $url_to_crawl ) {
                $url_to_crawl = trim( $url_to_crawl );
                $match = false;

                foreach ( $exclusions as $exclusion ) {
                    $exclusion = trim( $exclusion );

                    if ( $exclusion != '' ) {
                        if ( strpos( $url_to_crawl, $exclusion ) !== false ) {
                            WsLog::l( "Excluding $url_to_crawl because of rule $exclusion" );

                            $match = true;
                        }
                    }

                    if ( ! $match ) {
                        $modified_crawl_list[] = $url_to_crawl;
                    }
                }
            }
        } else {
            $modified_crawl_list = $crawl_list;
        }

        if ( isset( $this->settings['additionalUrls'] ) ) {
            $inclusions = explode(
                "\n",
                str_replace( "\r", '', $this->settings['additionalUrls'] )
            );

            foreach ( $inclusions as $inclusion ) {
                $inclusion = trim( $inclusion );
                $inclusion = $inclusion;

                $modified_crawl_list[] = $inclusion;
            }
        }

        if ( ! is_array( $modified_crawl_list ) ) {
            return;
        }

        $modified_crawl_list = array_unique( $modified_crawl_list );

        $str = implode( PHP_EOL, $modified_crawl_list );

        file_put_contents(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-FINAL-CRAWL-LIST.txt',
            $str
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-FINAL-CRAWL-LIST.txt',
            0664
        );
    }
}

