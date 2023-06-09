<?php

namespace StaticHTMLOutput;

use Net_URL2;

class SiteCrawler extends StaticHTMLOutput {

    /**
     * @var string
     */
    public $processed_file;
    /**
     * @var string
     */
    public $file_type;
    /**
     * @var string
     */
    public $response;
    /**
     * @var string
     */
    public $content_type;
    /**
     * @var string
     */
    public $url;
    /**
     * @var string
     */
    public $full_url;
    /**
     * @var string
     */
    public $extension;
    /**
     * @var string
     */
    public $archive_dir;
    /**
     * @var string
     */
    public $list_of_urls_to_crawl_path;
    /**
     * @var mixed[]
     */
    public $urls_to_crawl;
    /**
     * @var string
     */
    public $curl_content_type;
    /**
     * @var string
     */
    public $file_extension;
    /**
     * @var string
     */
    public $crawled_links_file;
    /**
     * @var int
     */
    public $remaining_urls_to_crawl;

    public function __construct() {
        $this->loadSettings(
            [
                'wpenv',
                'crawling',
                'processing',
                'advanced',
            ]
        );

        if ( isset( $this->settings['crawl_delay'] ) ) {
            sleep( $this->settings['crawl_delay'] );
        }

        $this->processed_file = '';
        $this->file_type = '';
        $this->response = '';
        $this->content_type = '';
        $this->url = '';
        $this->extension = '';
        $this->archive_dir = '';
        $this->list_of_urls_to_crawl_path = '';
        $this->urls_to_crawl = [];

        if ( ! defined( 'WP_CLI' ) ) {
            // @codingStandardsIgnoreStart
            if ( $_POST['ajax_action'] === 'crawl_again' ) {
                $this->crawl_discovered_links();
            } elseif ( $_POST['ajax_action'] === 'crawl_site' ) {
                $this->crawl_site();
            }
            // @codingStandardsIgnoreEnd
        }
    }

    public function generate_discovered_links_list() : void {
        $second_crawl_file_path = $this->settings['wp_uploads_path'] .
        '/WP-STATIC-2ND-CRAWL-LIST.txt';

        $already_crawled = file(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-INITIAL-CRAWL-LIST.txt',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ( ! $already_crawled ) {
            $already_crawled = [];
        }

        $unique_discovered_links = [];

        $discovered_links_file = $this->settings['wp_uploads_path'] .
            '/WP-STATIC-DISCOVERED-URLS.txt';

        if ( is_file( $discovered_links_file ) ) {
            $discovered_links = file(
                $discovered_links_file,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            if ( ! $discovered_links ) {
                $discovered_links = [];
            }

            $unique_discovered_links = array_unique( $discovered_links );
            sort( $unique_discovered_links );
        }

        file_put_contents(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-LOG.txt',
            implode( PHP_EOL, $unique_discovered_links )
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-LOG.txt',
            0664
        );

        file_put_contents(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-TOTAL.txt',
            count( $unique_discovered_links )
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-TOTAL.txt',
            0664
        );

        $discovered_links = array_diff(
            $unique_discovered_links,
            $already_crawled
        );

        file_put_contents(
            $second_crawl_file_path,
            implode( PHP_EOL, $discovered_links )
        );

        chmod( $second_crawl_file_path, 0664 );

        copy(
            $second_crawl_file_path,
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt'
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt',
            0664
        );
    }

    public function crawl_discovered_links() : void {
        if ( defined( 'WP_CLI' ) && ! defined( 'CRAWLING_DISCOVERED' ) ) {
            define( 'CRAWLING_DISCOVERED', true );
        }

        $second_crawl_file_path = $this->settings['wp_uploads_path'] .
        '/WP-STATIC-2ND-CRAWL-LIST.txt';

        // NOTE: the first iteration of the 2nd crawl phase,
        // the list of URLs for 2nd crawl is prepared
        if ( ! is_file( $second_crawl_file_path ) ) {
            $this->generate_discovered_links_list();
        }

        $this->list_of_urls_to_crawl_path =
            $this->settings['wp_uploads_path'] .
            '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt';

        if ( ! is_file( $this->list_of_urls_to_crawl_path ) ) {
            WsLog::l(
                'ERROR: LIST OF URLS TO CRAWL NOT FOUND AT: ' .
                    $this->list_of_urls_to_crawl_path
            );
            die();
        } else {
            if ( filesize( $this->list_of_urls_to_crawl_path ) ) {
                $this->crawlABitMore();
            } else {
                if ( ! defined( 'WP_CLI' ) ) {
                    echo 'SUCCESS';
                }
            }
        }
    }

    public function crawl_site() : void {
        // crude detection for CLI export to use 2nd crawl phase
        $this->list_of_urls_to_crawl_path =
            $this->settings['wp_uploads_path'] .
            '/WP-STATIC-FINAL-2ND-CRAWL-LIST.txt';

        if ( is_file( $this->list_of_urls_to_crawl_path ) ) {
            $this->crawl_discovered_links();

            return;
        }

        $this->list_of_urls_to_crawl_path =
            $this->settings['wp_uploads_path'] .
            '/WP-STATIC-FINAL-CRAWL-LIST.txt';

        if ( ! is_file( $this->list_of_urls_to_crawl_path ) ) {
            WsLog::l(
                'ERROR: LIST OF URLS TO CRAWL NOT FOUND AT: ' .
                    $this->list_of_urls_to_crawl_path
            );
            die();
        } else {
            if ( filesize( $this->list_of_urls_to_crawl_path ) ) {
                $this->crawlABitMore();
            } else {
                if ( ! defined( 'WP_CLI' ) ) {
                    echo 'SUCCESS';
                }
            }
        }
    }

    public function crawlABitMore() : void {
        $batch_of_links_to_crawl = [];

        $crawl_list = file(
            $this->list_of_urls_to_crawl_path,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ( ! $crawl_list ) {
            return;
        }

        $this->urls_to_crawl = $crawl_list;

        if ( is_array( $this->urls_to_crawl ) ) {
            $total_links = count( $this->urls_to_crawl );

            if ( $total_links < 1 ) {
                WsLog::l(
                    'ERROR: LIST OF URLS TO CRAWL NOT FOUND AT: ' .
                    $this->list_of_urls_to_crawl_path
                );
                die();
            }

            if ( $this->settings['crawl_increment'] > $total_links ) {
                $this->settings['crawl_increment'] = $total_links;
            }

            for ( $i = 0; $i < $this->settings['crawl_increment']; $i++ ) {
                $link_from_crawl_list = array_shift( $this->urls_to_crawl );

                if ( $link_from_crawl_list ) {
                    $batch_of_links_to_crawl[] = $link_from_crawl_list;
                }
            }

            $this->remaining_urls_to_crawl = count( $this->urls_to_crawl );

            // resave crawl list file, minus those from this batch
            file_put_contents(
                $this->list_of_urls_to_crawl_path,
                implode( "\r\n", $this->urls_to_crawl )
            );

            chmod( $this->list_of_urls_to_crawl_path, 0664 );

            // TODO: required in saving/copying, but not here? optimize...
            $handle = fopen(
                $this->settings['wp_uploads_path'] .
                    '/WP2STATIC-CURRENT-ARCHIVE.txt',
                'r'
            );

            if ( ! is_resource( $handle ) ) {
                return;
            }

            $line = stream_get_line( $handle, 0 );

            if ( ! is_string( $line ) ) {
                return;
            }

            $this->archive_dir = $line;

            $total_urls_path = $this->settings['wp_uploads_path'] .
                '/WP-STATIC-INITIAL-CRAWL-TOTAL.txt';

            // TODO: avoid mutation
            // @codingStandardsIgnoreStart
            if (
                defined( 'CRAWLING_DISCOVERED' ) ||
                ( isset( $_POST['ajax_action'] ) &&
                    $_POST['ajax_action'] == 'crawl_again'
                )
            ) {
                $total_urls_path = $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS-TOTAL.txt';
            }
            // @codingStandardsIgnoreEnd

            $total_urls_to_crawl = (int) file_get_contents( $total_urls_path );

            $batch_index = 0;

            $exclusions = [ 'wp-json' ];

            if ( isset( $this->settings['excludeURLs'] ) ) {
                $user_exclusions = explode(
                    "\n",
                    str_replace( "\r", '', $this->settings['excludeURLs'] )
                );

                $exclusions = array_merge(
                    $exclusions,
                    $user_exclusions
                );
            }

            WsLog::l(
                'Exclusion rules ' . implode( PHP_EOL, $exclusions )
            );

            foreach ( $batch_of_links_to_crawl as $link_to_crawl ) {
                $this->url = $link_to_crawl;

                $this->full_url = $this->settings['wp_site_url'] .
                    ltrim( $this->url, '/' );

                foreach ( $exclusions as $exclusion ) {
                    $exclusion = trim( $exclusion );
                    if ( $exclusion != '' ) {
                        if ( false !== strpos( $this->url, $exclusion ) ) {
                            WsLog::l(
                                'Excluding ' . $this->url .
                                ' because of rule ' . $exclusion
                            );

                            // skip the outer foreach loop
                            continue 2;
                        }
                    }
                }

                $this->file_extension = $this->getExtensionFromURL();

                if ( $this->loadFileForProcessing() ) {
                    $this->saveFile();
                }

                $batch_index++;

                $completed_urls =
                    $total_urls_to_crawl -
                    $this->remaining_urls_to_crawl -
                    count( $batch_of_links_to_crawl ) +
                    $batch_index;

                ProgressLog::l( $completed_urls, $total_urls_to_crawl );
            }
        }

        $this->checkIfMoreCrawlingNeeded();
        // reclaim memory after each crawl
        $url_reponse = null;
        unset( $url_reponse );
    }

    public function loadFileForProcessing() : bool {
        $ch = curl_init();

        if ( isset( $this->settings['crawlPort'] ) ) {
            curl_setopt(
                $ch,
                CURLOPT_PORT,
                $this->settings['crawlPort']
            );
        }

        curl_setopt( $ch, CURLOPT_URL, $this->full_url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
        curl_setopt( $ch, CURLOPT_USERAGENT, 'StaticHTMLOutput.com' );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

        if ( isset( $this->settings['useBasicAuth'] ) ) {
            curl_setopt(
                $ch,
                CURLOPT_USERPWD,
                $this->settings['basicAuthUser'] . ':' .
                    $this->settings['basicAuthPassword']
            );
        }

        $this->response = (string) curl_exec( $ch );

        $this->checkForCurlErrors( $this->response, $ch );

        $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        $this->curl_content_type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );

        curl_close( $ch );

        $this->crawled_links_file =
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-CRAWLED-LINKS.txt';

        $good_response_codes = [ 200, 201, 301, 302, 304 ];

        if ( ! in_array( $status_code, $good_response_codes ) ) {
            WsLog::l(
                'BAD RESPONSE STATUS (' . $status_code . '): ' . $this->url
            );

            file_put_contents(
                $this->settings['wp_uploads_path'] .
                    '/WP-STATIC-404-LOG.txt',
                $status_code . ':' . $this->url . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            chmod(
                $this->settings['wp_uploads_path'] .
                    '/WP-STATIC-404-LOG.txt',
                0664
            );

            return false;
        } else {
            file_put_contents(
                $this->crawled_links_file,
                $this->url . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            chmod( $this->crawled_links_file, 0664 );
        }

        $base_url = $this->settings['baseUrl'];

        $this->detectFileType();

        switch ( $this->file_type ) {
            case 'html':
                $processor = new HTMLProcessor();

                $processed = $processor->processHTML(
                    $this->response,
                    $this->full_url
                );

                if ( $processed ) {
                    $this->processed_file = $processor->getHTML();
                }

                break;

            case 'css':
                $processor = new CSSProcessor();

                $processed = $processor->processCSS(
                    $this->response,
                    $this->full_url
                );

                if ( $processed ) {
                    $this->processed_file = $processor->getCSS();
                }

                break;

            case 'txt':
            case 'js':
            case 'json':
            case 'xml':
                $processor = new TXTProcessor();

                $processed = $processor->processTXT(
                    $this->response,
                    $this->full_url
                );

                if ( $processed ) {
                    $this->processed_file = $processor->getTXT();
                }

                break;

            default:
                $this->processed_file = $this->response;

                break;
        }

        return true;
    }

    public function checkIfMoreCrawlingNeeded() : void {
        if ( $this->remaining_urls_to_crawl > 0 ) {
            if ( ! defined( 'WP_CLI' ) ) {
                echo $this->remaining_urls_to_crawl;
            } else {
                $this->crawl_site();
            }
        } else {
            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        }
    }

    public function saveFile() : void {
        $file_writer = new FileWriter(
            $this->url,
            $this->processed_file,
            $this->file_type,
            $this->content_type
        );

        $file_writer->saveFile( $this->archive_dir );
    }

    public function getExtensionFromURL() : string {
        $url_path = parse_url( $this->url, PHP_URL_PATH );

        if ( ! $url_path ) {
            return '';
        }

        $extension = pathinfo( $url_path, PATHINFO_EXTENSION );

        if ( ! $extension ) {
            return '';
        }

        return $extension;
    }

    public function detectFileType() : void {
        if ( $this->file_extension ) {
            $this->file_type = $this->file_extension;
        } else {
            $type = $this->content_type =
                $this->curl_content_type;

            if ( stripos( $type, 'text/html' ) !== false ) {
                $this->file_type = 'html';
            } elseif ( stripos( $type, 'rss+xml' ) !== false ) {
                $this->file_type = 'xml';
            } elseif ( stripos( $type, 'text/xml' ) !== false ) {
                $this->file_type = 'xml';
            } elseif ( stripos( $type, 'application/xml' ) !== false ) {
                $this->file_type = 'xml';
            } elseif ( stripos( $type, 'application/json' ) !== false ) {
                $this->file_type = 'json';
            } else {
                WsLog::l(
                    'no filetype inferred from content-type: ' .
                    $this->curl_content_type .
                    ' url: ' . $this->url
                );
            }
        }
    }

    /**
     * @param resource $curl_handle to the resource
     */
    public function checkForCurlErrors( string $response, $curl_handle ) : void {
        if ( ! $response ) {
            $response = curl_error( $curl_handle );
            WsLog::l(
                'cURL error:' .
                stripslashes( $response )
            );
        }
    }
}
