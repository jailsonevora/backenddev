<?php

namespace StaticHTMLOutput;

class TXTProcessor extends StaticHTMLOutput {

    /**
     * @var string
     */
    public $txt_document;
    /**
     * @var string
     */
    public $destination_protocol;
    /**
     * @var string
     */
    public $destination_protocol_relative_url;
    /**
     * @var string
     */
    public $placeholder_url;
    /**
     * @var mixed[]
     */
    public $settings;
    /**
     * @var string
     */
    public $txt_doc;

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

    public function processTXT( string $txt_document, string $page_url ) : bool {
        if ( $txt_document == '' ) {
            return false;
        }

        $this->txt_doc = $txt_document;

        $this->destination_protocol =
            $this->getTargetSiteProtocol( $this->settings['baseUrl'] );

        $this->placeholder_url =
            $this->destination_protocol . 'PLACEHOLDER.wpsho/';

        $this->rewriteSiteURLsToPlaceholder();

        return true;
    }

    public function getTXT() : string {
        $processed_txt = $this->txt_doc;

        $processed_txt = $this->detectEscapedSiteURLs( $processed_txt );
        $processed_txt = $this->detectUnchangedURLs( $processed_txt );

        return $processed_txt;
    }

    public function detectEscapedSiteURLs( string $processed_txt ) : string {
        // NOTE: this does return the expected http:\/\/172.18.0.3
        // but your error log may escape again and
        // show http:\\/\\/172.18.0.3
        $escaped_site_url = addcslashes( $this->placeholder_url, '/' );

        if ( strpos( $processed_txt, $escaped_site_url ) !== false ) {
            return $this->rewriteEscapedURLs( $processed_txt );
        }

        return $processed_txt;
    }

    public function detectUnchangedURLs( string $processed_txt ) : string {
        $site_url = $this->placeholder_url;

        if ( strpos( $processed_txt, $site_url ) !== false ) {
            return $this->rewriteUnchangedURLs( $processed_txt );
        }

        return $processed_txt;
    }

    public function rewriteUnchangedURLs( string $processed_txt ) : string {
        if ( ! isset( $this->settings['rewrite_rules'] ) ) {
            $this->settings['rewrite_rules'] = '';
        }

        // add base URL to rewrite_rules
        $this->settings['rewrite_rules'] .=
            PHP_EOL .
                $this->placeholder_url . ',' .
                $this->settings['baseUrl'];

        // add protocol relative URL to rewrite_rules
        $this->settings['rewrite_rules'] .=
            PHP_EOL .
                $this->getProtocolRelativeURL( $this->placeholder_url ) . ',' .
                $this->getProtocolRelativeURL( $this->settings['baseUrl'] );

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->settings['rewrite_rules'] )
        );

        foreach ( $rewrite_rules as $rewrite_rule_line ) {
            if ( $rewrite_rule_line ) {
                list($from, $to) = explode( ',', $rewrite_rule_line );

                $rewrite_from[] = $from;
                $rewrite_to[] = $to;
            }
        }

        $rewritten_source = str_replace(
            $rewrite_from,
            $rewrite_to,
            $processed_txt
        );

        return $rewritten_source;
    }

    public function rewriteEscapedURLs( string $processed_txt ) : string {
        // NOTE: fix input TXT, which can have \ slashes modified to %5C
        $processed_txt = str_replace(
            '%5C/',
            '\\/',
            $processed_txt
        );

        /*
        This function will be a bit more costly. To cover bases like:

         data-images="[&quot;https:\/\/mysite.example.com\/wp...
        from the onepress(?) theme, for example

        */
        $site_url = addcslashes( $this->placeholder_url, '/' );
        $destination_url = addcslashes( $this->settings['baseUrl'], '/' );

        if ( ! isset( $this->settings['rewrite_rules'] ) ) {
            $this->settings['rewrite_rules'] = '';
        }

        // add base URL to rewrite_rules
        $this->settings['rewrite_rules'] .=
            PHP_EOL .
                $site_url . ',' .
                $destination_url;

        $rewrite_from = [];
        $rewrite_to = [];

        $rewrite_rules = explode(
            "\n",
            str_replace( "\r", '', $this->settings['rewrite_rules'] )
        );

        foreach ( $rewrite_rules as $rewrite_rule_line ) {
            if ( $rewrite_rule_line ) {
                list($from, $to) = explode( ',', $rewrite_rule_line );

                $rewrite_from[] = addcslashes( $from, '/' );
                $rewrite_to[] = addcslashes( $to, '/' );
            }
        }

        $rewritten_source = str_replace(
            $rewrite_from,
            $rewrite_to,
            $processed_txt
        );

        return $rewritten_source;
    }

    public function rewriteSiteURLsToPlaceholder() : void {
        $patterns = [
            $this->settings['wp_site_url'],
            $this->getProtocolRelativeURL(
                $this->settings['wp_site_url']
            ),
            $this->getProtocolRelativeURL(
                rtrim( $this->settings['wp_site_url'], '/' )
            ),
            $this->getProtocolRelativeURL(
                $this->settings['wp_site_url'] . '//'
            ),
            $this->getProtocolRelativeURL(
                addcslashes( $this->settings['wp_site_url'], '/' )
            ),
        ];

        $replacements = [
            $this->placeholder_url,
            $this->getProtocolRelativeURL(
                $this->placeholder_url
            ),
            $this->getProtocolRelativeURL(
                $this->placeholder_url
            ),
            $this->getProtocolRelativeURL(
                $this->placeholder_url . '/'
            ),
            $this->getProtocolRelativeURL(
                addcslashes( $this->placeholder_url, '/' )
            ),
        ];

        // catch any http links on an https WP site
        if ( $this->destination_protocol === 'https' ) {
            $patterns[] = str_replace(
                'http:',
                'https:',
                $this->settings['wp_site_url']
            );

            $replacements[] = $this->placeholder_url;
        }

        $rewritten_source = str_replace(
            $patterns,
            $replacements,
            $this->txt_doc
        );

        $this->txt_doc = $rewritten_source;
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

    public function getProtocolRelativeURL( string $url ) : string {
        $this->destination_protocol_relative_url = str_replace(
            [
                'https:',
                'http:',
            ],
            [
                '',
                '',
            ],
            $url
        );

        return $this->destination_protocol_relative_url;
    }
}

