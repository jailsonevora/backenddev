<?php

namespace StaticHTMLOutput;

use Sabberworm;
use Net_URL2;

class CSSProcessor extends StaticHTMLOutput {

    /**
     * @var string
     */
    public $placeholder_url;
    /**
     * @var string
     */
    public $raw_css;
    /**
     * @var string
     */
    public $page_url;
    /**
     * @var Sabberworm\CSS\CSSList\Document
     */
    public $css_doc;
    /**
     * @var string[]
     */
    public $discovered_urls;
    /**
     * @var bool
     */
    public $harvest_new_urls;

    public function __construct() {
        $this->loadSettings(
            [
                'crawling',
                'wpenv',
                'processing',
                'advanced',
            ]
        );
    }

    public function processCSS( string $css_document, string $page_url ) : bool {
        if ( $css_document == '' ) {
            return false;
        }

        $protocol = $this->getTargetSiteProtocol( $this->settings['baseUrl'] );

        $this->placeholder_url = $protocol . 'PLACEHOLDER.wpsho/';

        $this->raw_css = $css_document;
        // initial rewrite of all site URLs to placeholder URLs
        $this->rewriteSiteURLsToPlaceholder();

        $css_parser = new Sabberworm\CSS\Parser( $this->raw_css );
        $this->css_doc = $css_parser->parse();

        $this->page_url = new Net_URL2( $page_url );

        $this->detectIfURLsShouldBeHarvested();

        $this->discovered_urls = [];

        foreach ( $this->css_doc->getAllValues() as $node_value ) {
            if ( $node_value instanceof Sabberworm\CSS\Value\URL ) {
                $original_link = $node_value->getURL();

                $original_link = trim( trim( $original_link, "'" ), '"' );

                $inline_img =
                    strpos( $original_link, 'data:image' );

                if ( $inline_img !== false ) {
                    continue;
                }

                $this->addDiscoveredURL( $original_link );

                if ( $this->isInternalLink( $original_link ) ) {
                    if ( ! isset( $this->settings['rewrite_rules'] ) ) {
                        $this->settings['rewrite_rules'] = '';
                    }

                    // add base URL to rewrite_rules
                    $this->settings['rewrite_rules'] .=
                        PHP_EOL .
                            $this->placeholder_url . ',' .
                            $this->settings['baseUrl'];

                    $rewrite_from = [];
                    $rewrite_to = [];

                    $rewrite_rules = explode(
                        "\n",
                        str_replace(
                            "\r",
                            '',
                            $this->settings['rewrite_rules']
                        )
                    );

                    foreach ( $rewrite_rules as $rewrite_rule_line ) {
                        if ( $rewrite_rule_line ) {
                            list($from, $to) =
                                explode( ',', $rewrite_rule_line );

                            $rewrite_from[] = $from;
                            $rewrite_to[] = $to;
                        }
                    }

                    $rewritten_url = str_replace(
                        $rewrite_from,
                        $rewrite_to,
                        $original_link
                    );

                    $rewritten_url = new Sabberworm\CSS\Value\CSSString(
                        $rewritten_url
                    );

                    $node_value->setURL( $rewritten_url );
                }
            }
        }

        $this->writeDiscoveredURLs();

        return true;
    }

    public function isInternalLink( string $link, string $domain = '' ) : bool {
        if ( ! $domain ) {
            $domain = $this->placeholder_url;
        }

        $is_internal_link = parse_url( $link, PHP_URL_HOST ) === parse_url(
            $domain,
            PHP_URL_HOST
        );

        return $is_internal_link;
    }

    public function getCSS() : string {
        return $this->css_doc->render();
    }

    public function rewriteSiteURLsToPlaceholder() : void {
        $rewritten_source = str_replace(
            [
                $this->settings['wp_site_url'],
                addcslashes( $this->settings['wp_site_url'], '/' ),
            ],
            [
                $this->placeholder_url,
                addcslashes( $this->placeholder_url, '/' ),
            ],
            $this->raw_css
        );

        $this->raw_css = $rewritten_source;
    }

    public function detectIfURLsShouldBeHarvested() : void {
        if ( defined( 'WP_CLI' ) ) {
            if ( defined( 'CRAWLING_DISCOVERED' ) ) {
                return;
            } else {
                $this->harvest_new_urls = true;
            }
        } else {
            // @codingStandardsIgnoreStart
            $this->harvest_new_urls = (
                 $_POST['ajax_action'] === 'crawl_site'
            );
            // @codingStandardsIgnoreEnd
        }
    }

    public function addDiscoveredURL( string $url ) : void {
        // trim any query strings or anchors
        $url = strtok( $url, '#' );
        $url = trim( (string) strtok( (string) $url, '?' ) );

        if ( ! $url ) {
            return;
        }

        if ( $this->harvest_new_urls ) {
            if ( ! $this->isValidURL( $url ) ) {
                return;
            }

            if ( $this->isInternalLink( $url ) ) {
                $discovered_url_without_site_url =
                    str_replace(
                        rtrim( $this->placeholder_url, '/' ),
                        '',
                        $url
                    );

                $this->discovered_urls[] = $discovered_url_without_site_url;
            }
        }
    }

    public function writeDiscoveredURLs() : void {
        // @codingStandardsIgnoreStart
        if ( isset( $_POST['ajax_action'] ) &&
            $_POST['ajax_action'] === 'crawl_again' ) {
            return;
        }
        // @codingStandardsIgnoreEnd

        if ( defined( 'WP_CLI' ) ) {
            if ( defined( 'CRAWLING_DISCOVERED' ) ) {
                return;
            }
        }

        file_put_contents(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS.txt',
            PHP_EOL .
                implode( PHP_EOL, array_unique( $this->discovered_urls ) ),
            FILE_APPEND | LOCK_EX
        );

        chmod(
            $this->settings['wp_uploads_path'] .
                '/WP-STATIC-DISCOVERED-URLS.txt',
            0664
        );
    }

    public function isValidURL( string $url ) : bool {
        // NOTE: not using native URL filter as it won't accept
        // non-ASCII URLs, which we want to support
        $url = trim( $url );

        if ( $url == '' ) {
            return false;
        }

        if ( strpos( $url, '.php' ) !== false ) {
            return false;
        }

        if ( strpos( $url, ' ' ) !== false ) {
            return false;
        }

        if ( $url[0] == '#' ) {
            return false;
        }

        return true;
    }

    public function getTargetSiteProtocol( string $url ) : string {
        $protocol = '//';

        if ( strpos( $url, 'https://' ) !== false ) {
            $protocol = 'https://';
        } elseif ( strpos( $url, 'http://' ) !== false ) {
            $protocol = 'http://';
        } else {
            $protocol = '//';
        }

        return $protocol;
    }
}

