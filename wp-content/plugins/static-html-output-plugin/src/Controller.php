<?php

namespace StaticHTMLOutput;

class Controller {
    /**
     * @var mixed[]
     */
    public $settings;
    /**
     * @var View
     */
    public $view;
    /**
     * @var Options
     */
    public $options;
    /**
     * @var Exporter
     */
    public $exporter;
    /**
     * @var string
     */
    public $current_archive;
    /**
     * @var WPSite
     */
    public $wp_site;

    const VERSION = '6.6.8';
    const OPTIONS_KEY = 'statichtmloutput-options';
    const HOOK = 'statichtmloutput';

    /**
     * @var Controller
     */
    protected static $instance = null;

    protected function __construct() {}

    public static function getInstance() : Controller {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->options = new Options(
                self::OPTIONS_KEY
            );
            self::$instance->view = new View();
        }

        return self::$instance;
    }

    public static function init( string $bootstrap_file ) : Controller {
        $instance = self::getInstance();

        register_activation_hook( $bootstrap_file, [ 'StaticHTMLOutput\Controller', 'activate' ] );

        if ( is_admin() ) {
            add_action(
                'admin_menu',
                [ 'StaticHTMLOutput\Controller', 'registerOptionsPage' ]
            );
            add_filter( 'custom_menu_order', '__return_true' );
            add_filter( 'menu_order', [ 'StaticHTMLOutput\Controller', 'set_menu_order' ] );
        }
        return $instance;
    }


    /**
     * Adjusts position of dashboard menu icons
     *
     * @param string[] $menu_order list of menu items
     * @return string[] list of menu items
     */
    public static function set_menu_order( $menu_order ) {
        $order = [];
        $file  = plugin_basename( __FILE__ );
        foreach ( $menu_order as $index => $item ) {
            if ( $item === 'index.php' ) {
                $order[] = $item;
            }
        }

        $order = [
            'index.php',
            'wp2static',
            'statichtmloutput',
        ];

        return $order;
    }


    public function setDefaultOptions() : void {
        if ( null === $this->options->getOption( 'version' ) ) {
            $this->options
            ->setOption( 'version', self::VERSION )
            ->setOption( 'static_export_settings', self::VERSION )
            // set default options
            ->setOption( 'rewriteWPPaths', '1' )
            ->setOption( 'removeConditionalHeadComments', '1' )
            ->setOption( 'removeWPMeta', '1' )
            ->setOption( 'removeWPLinks', '1' )
            ->setOption( 'removeHTMLComments', '1' )
            ->save();
        }
    }

    public function activate_for_single_site() : void {
        $this->setDefaultOptions();
    }

    public static function activate( bool $network_wide ) : void {
        $instance = self::getInstance();

        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                $instance->activate_for_single_site();
            }

            restore_current_blog();
        } else {
            $instance->activate_for_single_site();
        }
    }

    public static function registerOptionsPage() : void {
        $plugins_url = plugin_dir_url( dirname( __FILE__ ) );

        $page = add_menu_page(
            'Static HTML',
            'Static HTML',
            'manage_options',
            self::HOOK,
            [ 'StaticHTMLOutput\Controller', 'renderOptionsPage' ],
            'dashicons-arrow-right-alt'
        );

        add_action(
            'admin_print_styles-' . $page,
            [ 'StaticHTMLOutput\Controller', 'enqueueAdminStyles' ]
        );
    }

    public static function enqueueAdminStyles() : void {
        $plugins_url = plugin_dir_url( dirname( __FILE__ ) );

        wp_enqueue_style(
            self::HOOK . '-admin',
            $plugins_url . 'statichtmloutput.css?sdf=sdfd',
            [],
            self::VERSION
        );
    }

    public function finalize_deployment() : void {
        $deployer = new Deployer();
        $deployer->finalizeDeployment();

        echo 'SUCCESS';
    }

    public function generate_filelist_preview() : void {
        $this->wp_site = new WPSite();

        $target_settings = [
            'general',
            'crawling',
        ];

        if ( defined( 'WP_CLI' ) ) {
            $this->settings =
                DBSettings::get( $target_settings );
        } else {
            $this->settings =
                PostSettings::get( $target_settings );
        }

        $plugin_hook = 'statichtmloutput';

        $initial_file_list_count =
            FilesHelper::buildInitialFileList(
                true,
                $this->wp_site->wp_uploads_path,
                $this->wp_site->uploads_url,
                $this->settings
            );

        if ( ! defined( 'WP_CLI' ) ) {
            echo $initial_file_list_count;
        }
    }

    public static function renderOptionsPage() : void {
        $instance = self::getInstance();
        $instance->wp_site = new WPSite();
        $instance->current_archive = '';

        $instance->view
            ->setTemplate( 'options-page-js' )
            ->assign( 'options', $instance->options )
            ->assign( 'wp_site', $instance->wp_site )
            ->assign( 'onceAction', self::HOOK . '-options' )
            ->render();

        $instance->view
            ->setTemplate( 'options-page' )
            ->assign( 'wp_site', $instance->wp_site )
            ->assign( 'options', $instance->options )
            ->assign( 'onceAction', self::HOOK . '-options' )
            ->render();
    }

    public function userIsAllowed() : bool {
        $referred_by_admin = check_admin_referer( self::HOOK . '-options' );
        $user_can_manage_options = current_user_can( 'manage_options' );

        return $referred_by_admin && $user_can_manage_options;
    }

    public function save_options() : void {
        if ( ! $this->userIsAllowed() ) {
            exit( 'Not allowed to change plugin options.' );
        }

        $this->options->saveAllPostData();
    }

    public function prepare_for_export() : void {
        $this->exporter = new Exporter();

        $this->exporter->pre_export_cleanup();
        $this->exporter->cleanup_leftover_archives();
        $this->exporter->initialize_cache_files();

        $archive = new Archive();
        $archive->create();

        $this->logEnvironmentalInfo();

        $this->exporter->generateModifiedFileList();

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function reset_default_settings() : void {
        if ( ! delete_option( 'statichtmloutput-options' ) ) {
            WsLog::l( 'Error resetting options to defaults' );
            echo 'ERROR';
        }

        $this->options = new Options( self::OPTIONS_KEY );
        $this->setDefaultOptions();

        echo 'SUCCESS';
    }

    public function post_process_archive_dir() : void {
        $processor = new ArchiveProcessor();

        $processor->createNetlifySpecialFiles();
        // NOTE: renameWP Directories also doing same server publish
        $processor->renameArchiveDirectories();
        $processor->removeWPCruft();
        $processor->copyStaticSiteToPublicFolder();
        $processor->create_zip();

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function delete_deploy_cache() : void {
        $target_settings = [
            'wpenv',
        ];

        if ( defined( 'WP_CLI' ) ) {
            $this->settings =
                DBSettings::get( $target_settings );
        } else {
            $this->settings =
                PostSettings::get( $target_settings );
        }
        $uploads_dir = $this->settings['wp_uploads_path'];

        $cache_files = [
            '/WP2STATIC-GITLAB-PREVIOUS-HASHES.txt',
            '/WP2STATIC-GITHUB-PREVIOUS-HASHES.txt',
            '/WP2STATIC-S3-PREVIOUS-HASHES.txt',
            '/WP2STATIC-BUNNYCDN-PREVIOUS-HASHES.txt',
            '/WP2STATIC-BITBUCKET-PREVIOUS-HASHES.txt',
            '/WP2STATIC-FTP-PREVIOUS-HASHES.txt',
        ];

        foreach ( $cache_files as $cache_file ) {
            if ( is_file( $uploads_dir . $cache_file ) ) {
                unlink( $uploads_dir . $cache_file );
            }
        }

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function logEnvironmentalInfo() : void {
        $info = [
            '' . gmdate( 'Y-m-d h:i:s' ),
            'PHP VERSION ' . phpversion(),
            'OS VERSION ' . php_uname(),
            'WP VERSION ' . get_bloginfo( 'version' ),
            'WP URL ' . get_bloginfo( 'url' ),
            'WP SITEURL ' . get_option( 'siteurl' ),
            'WP HOME ' . get_option( 'home' ),
            'WP ADDRESS ' . get_bloginfo( 'wpurl' ),
            'PLUGIN VERSION ' . $this::VERSION,
            'VIA WP-CLI? ' . defined( 'WP_CLI' ),
            'STATIC EXPORT URL ' . $this->exporter->settings['baseUrl'],
            'PERMALINK STRUCTURE ' . get_option( 'permalink_structure' ),
        ];

        if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
            $info[] = 'SERVER SOFTWARE ' . $_SERVER['SERVER_SOFTWARE'];
        }

        WsLog::l( implode( PHP_EOL, $info ) );

        WsLog::l( 'Active plugins:' );

        $active_plugins = get_option( 'active_plugins' );

        foreach ( $active_plugins as $active_plugin ) {
            WsLog::l( $active_plugin );
        }

        WsLog::l( 'Plugin options:' );

        $options = $this->options->getAllOptions( false );

        foreach ( $options as $key => $value ) {
            WsLog::l( "{$value['Option name']}: {$value['Value']}" );
        }

        WsLog::l( 'Installed extensions:' );

        $extensions = get_loaded_extensions();

        foreach ( $extensions as $extension ) {
            WsLog::l( $extension );
        }
    }
}
