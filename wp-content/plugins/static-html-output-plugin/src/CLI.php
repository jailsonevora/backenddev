<?php

namespace StaticHTMLOutput;

use WP_CLI;

/**
 * Generate a static copy of your website & publish remotely
 */
class CLI {
    /**
     * Display system information and health check
     */
    public function diagnostics() : void {
        WP_CLI::line(
            PHP_EOL . 'StaticHTMLOutput' . PHP_EOL
        );

        $environmental_info = [
            [
                'key' => 'PLUGIN VERSION',
                'value' => Controller::VERSION,
            ],
            [
                'key' => 'PHP_VERSION',
                'value' => phpversion(),
            ],
            [
                'key' => 'PHP MAX EXECUTION TIME',
                'value' => ini_get( 'max_execution_time' ),
            ],
            [
                'key' => 'OS VERSION',
                'value' => php_uname(),
            ],
            [
                'key' => 'WP VERSION',
                'value' => get_bloginfo( 'version' ),
            ],
            [
                'key' => 'WP URL',
                'value' => get_bloginfo( 'url' ),
            ],
            [
                'key' => 'WP SITEURL',
                'value' => get_option( 'siteurl' ),
            ],
            [
                'key' => 'WP HOME',
                'value' => get_option( 'home' ),
            ],
            [
                'key' => 'WP ADDRESS',
                'value' => get_bloginfo( 'wpurl' ),
            ],
        ];

        WP_CLI\Utils\format_items(
            'table',
            $environmental_info,
            [ 'key', 'value' ]
        );

        $active_plugins = get_option( 'active_plugins' );

        WP_CLI::line( PHP_EOL . 'Active plugins:' . PHP_EOL );

        foreach ( $active_plugins as $active_plugin ) {
            WP_CLI::line( $active_plugin );
        }

        WP_CLI::line( PHP_EOL );

        WP_CLI::line(
            'There are a total of ' . count( $active_plugins ) .
            ' active plugins on this site.' . PHP_EOL
        );

    }

    public function microtime_diff( string $start, string $end = null ) : float {
        if ( ! $end ) {
            $end = microtime();
        }

        list( $start_usec, $start_sec ) = explode( ' ', $start );
        list( $end_usec, $end_sec ) = explode( ' ', $end );

        $diff_sec = intval( $end_sec ) - intval( $start_sec );
        $diff_usec = floatval( $end_usec ) - floatval( $start_usec );

        return floatval( $diff_sec ) + $diff_usec;
    }

    /**
     * Generate a static copy of your WordPress site.
     */
    public function generate() : void {
        $start_time = microtime();

        $plugin = Controller::getInstance();
        $plugin->generate_filelist_preview();
        $plugin->prepare_for_export();

        $site_crawler = new SiteCrawler();

        $site_crawler->crawl_site();
        $site_crawler->crawl_discovered_links();
        $plugin->post_process_archive_dir();

        $end_time = microtime();

        $duration = $this->microtime_diff( $start_time, $end_time );

        WP_CLI::success(
            "Generated static site archive in $duration seconds"
        );
    }

    /**
     * Deploy the generated static site.
     * ## OPTIONS
     *
     * [--test]
     * : Validate the connection settings without deploying
     *
     * [--selected_deployment_option]
     * : Override the deployment option
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function deploy( array $args, array $assoc_args ) : void {
        $test = false;

        if ( ! empty( $assoc_args['test'] ) ) {
            $test = true;
        }

        if ( ! empty( $assoc_args['selected_deployment_option'] ) ) {
            switch ( $assoc_args['selected_deployment_option'] ) {
                case 'zip':
                    break;
            }
        }

        WP_CLI::log( 'Deploying static site' );

        $deployer = new Deployer();

        $deploy_result = $deployer->deploy( $test );

        WP_CLI::line( $deploy_result );
    }

    /**
     * Read / write plugin options
     *
     * ## OPTIONS
     *
     * <list> [--reveal-sensitive-values]
     *
     * Get all option names and values (explicitly reveal sensitive values)
     *
     * <get> <option-name>
     *
     * Get or set a specific option via name
     *
     * <set> <option-name> <value>
     *
     * Set a specific option via name
     *
     *
     * ## EXAMPLES
     *
     * List all options
     *
     *     wp statichtmloutput options list
     *
     * List all options (revealing sensitive values)
     *
     *     wp statichtmloutput options list --reveal_sensitive_values
     *
     * Get option
     *
     *     wp statichtmloutput options get selected_deployment_option
     *
     * Set option
     *
     *     wp statichtmloutput options set baseUrl 'https://mystaticsite.com'
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function options( $args, $assoc_args ) : void {
        $action = isset( $args[0] ) ? $args[0] : null;
        $option_name = isset( $args[1] ) ? $args[1] : null;
        $value = isset( $args[2] ) ? $args[2] : null;
        $reveal_sensitive_values = false;

        if ( empty( $action ) ) {
            WP_CLI::error( 'Missing required argument: <get|set|list>' );
        }

        $plugin = Controller::getInstance();

        if ( $action === 'get' ) {
            if ( empty( $option_name ) ) {
                WP_CLI::error( 'Missing required argument: <option-name>' );
                return;
            }

            if ( ! $plugin->options->optionExists( $option_name ) ) {
                WP_CLI::error( 'Invalid option name' );
            } else {
                $option_value = $plugin->options->getOption( $option_name );

                WP_CLI::line( $option_value );
            }
        }

        if ( $action === 'set' ) {
            if ( empty( $option_name ) ) {
                WP_CLI::error( 'Missing required argument: <option-name>' );
                return;
            }

            if ( empty( $value ) ) {
                WP_CLI::error( 'Missing required argument: <value>' );
                return;
            }

            if ( ! $plugin->options->optionExists( $option_name ) ) {
                WP_CLI::error( 'Invalid option name' );
            } else {
                $plugin->options->setOption( $option_name, $value );
                $plugin->options->save();

                $result = $plugin->options->getOption( $option_name );

                if ( $result !== $value ) {
                    WP_CLI::error( 'Option not able to be updated' );
                }
            }
        }

        if ( $action === 'list' ) {
            if ( isset( $assoc_args['reveal-sensitive-values'] ) ) {
                $reveal_sensitive_values = true;
            }

            $options =
                $plugin->options->getAllOptions( $reveal_sensitive_values );

            WP_CLI\Utils\format_items(
                'table',
                $options,
                [ 'Option name', 'Value' ]
            );
        }
    }
}

/*
TODO:

WP_CLI\Utils\launch_editor_for_input() – Launch system’s $EDITOR f
r the user to edit some text.

use that for inputting things like additional URLs, Netlify _redirects, etc

TODO: use WP error for things like permalinks. Run on every command?
no, just diagnostics

*/
