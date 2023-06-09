<?php

namespace StaticHTMLOutput;

use RecursiveIteratorIterator;
use RecursiveArrayIterator;
use RecursiveDirectoryIterator;

class FilesHelper {

    public static function delete_dir_with_files( string $dir ) : bool {
        if ( ! is_dir( $dir ) ) {
            return true;
        }

        $dir_files = scandir( $dir );

        if ( ! $dir_files ) {
            return true;
        }

        $files = array_diff( $dir_files, [ '.', '..' ] );

        if ( ! $files ) {
            return true;
        }

        foreach ( $files as $file ) {
            ( is_dir( "$dir/$file" ) ) ?
            self::delete_dir_with_files( "$dir/$file" ) :
            unlink( "$dir/$file" );
        }

        return rmdir( $dir );
    }

    /**
     * @return string[] list of URLs
     */
    public static function getThemeFiles( string $theme_type ) : array {
        $wp_site = new WPSite();

        $files = [];
        $template_path = '';
        $template_url = '';

        if ( $theme_type === 'parent' ) {
            $template_path = $wp_site->parent_theme_path;
            $template_url = get_template_directory_uri();
        } else {
            $template_path = $wp_site->child_theme_path;
            $template_url = get_stylesheet_directory_uri();
        }

        $directory = $template_path;

        if ( is_dir( $directory ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ( $iterator as $filename => $file_object ) {
                $path_crawlable = self::filePathLooksCrawlable( $filename );

                $detected_filename =
                    str_replace(
                        $template_path,
                        $template_url,
                        $filename
                    );

                $detected_filename =
                    str_replace(
                        get_home_url(),
                        '',
                        $detected_filename
                    );

                if ( $path_crawlable && is_string( $detected_filename ) ) {
                    array_push(
                        $files,
                        $detected_filename
                    );
                }
            }
        }

        return $files;
    }

    /**
     * @return string[] list of URLs
     */
    public static function detectVendorFiles( string $wp_site_url ) : array {
        $wp_site = new WPSite();

        $vendor_files = [];

        if ( class_exists( '\\Elementor\Api' ) ) {
            $elementor_font_dir = WP_PLUGIN_DIR .
                '/elementor/assets/lib/font-awesome';

            $elementor_urls = self::getListOfLocalFilesByUrl(
                $elementor_font_dir
            );

            $vendor_files = array_merge( $vendor_files, $elementor_urls );
        }

        if ( defined( 'WPSEO_VERSION' ) ) {
            $yoast_sitemaps = [
                '/sitemap_index.xml',
                '/post-sitemap.xml',
                '/page-sitemap.xml',
                '/category-sitemap.xml',
                '/author-sitemap.xml',
            ];

            $vendor_files = array_merge( $vendor_files, $yoast_sitemaps );
        }

        if ( is_dir( WP_PLUGIN_DIR . '/soliloquy/' ) ) {
            $soliloquy_assets = WP_PLUGIN_DIR .
                '/soliloquy/assets/css/images/';

            $soliloquy_urls = self::getListOfLocalFilesByUrl(
                $soliloquy_assets
            );

            $vendor_files = array_merge( $vendor_files, $soliloquy_urls );
        }

        if ( class_exists( 'autoptimizeMain' ) ) {
            $autoptimize_cache_dir =
                $wp_site->wp_content_path . '/cache/autoptimize';

            // get difference between home and wp-contents URL
            $prefix = str_replace(
                $wp_site->site_url,
                '/',
                $wp_site->wp_content_url
            );

            $autoptimize_urls = self::getAutoptimizeCacheFiles(
                $autoptimize_cache_dir,
                $wp_site->wp_content_path,
                $prefix
            );

            $vendor_files = array_merge( $vendor_files, $autoptimize_urls );
        }

        if ( class_exists( 'Custom_Permalinks' ) ) {
            global $wpdb;

            $query = "
                SELECT meta_value
                FROM %s
                WHERE meta_key = '%s'
                ";

            $custom_permalinks = [];

            $posts = $wpdb->get_results(
                sprintf(
                    $query,
                    $wpdb->postmeta,
                    'custom_permalink'
                )
            );

            if ( $posts ) {
                foreach ( $posts as $post ) {
                    $custom_permalinks[] = $wp_site_url . $post->meta_value;
                }

                $vendor_files = array_merge(
                    $vendor_files,
                    $custom_permalinks
                );
            }
        }

        if ( class_exists( 'molongui_authorship' ) ) {
            $molongui_path = WP_PLUGIN_DIR . '/molongui-authorship';

            $molongui_urls = self::getListOfLocalFilesByUrl(
                $molongui_path
            );

            $vendor_files = array_merge( $vendor_files, $molongui_urls );
        }

        return $vendor_files;
    }

    /**
     * Recursively scan a directory and save list of discovered files as URLs in path
     */
    public static function recursively_scan_dir(
        string $dir,
        string $siteroot,
        string $list_path
    ) : void {
        $dir = str_replace( '//', '/', $dir );
        $files = scandir( $dir );

        if ( ! $files ) {
            return;
        }

        foreach ( $files as $item ) {
            if ( $item != '.' && $item != '..' && $item != '.git' ) {
                if ( is_dir( $dir . '/' . $item ) ) {
                    self::recursively_scan_dir(
                        $dir . '/' . $item,
                        $siteroot,
                        $list_path
                    );
                } elseif ( is_file( $dir . '/' . $item ) ) {
                    // TODO: tidy up _SERVER
                    $subdir = str_replace(
                        '/wp-admin/admin-ajax.php',
                        '',
                        $_SERVER['REQUEST_URI']
                    );
                    $subdir = ltrim( $subdir, '/' );
                    $clean_dir =
                        str_replace( $siteroot . '/', '', $dir . '/' );
                    $clean_dir = str_replace( $subdir, '', $clean_dir );
                    $filename = $dir . '/' . $item . "\n";
                    $filename = str_replace( '//', '/', $filename );

                    file_put_contents(
                        $list_path,
                        $filename,
                        FILE_APPEND | LOCK_EX
                    );

                    // TODO: check into permissions we should be trying to set vs
                    // rely on server setup. Test against UI & CLI. Log error if call fails
                    chmod( $list_path, 0664 );
                }
            }
        }
    }

    /**
     *  Autoptimize puts it's cache dir one level above the uploads URL
     *  ie, domain.com/cache/ or domain.com/subdir/cache/
     *  so, we grab all the files from the its actual cache dir
     *  then strip the site path and any subdir path (no extra logic needed?)
     *
     * @return string[] list of URLs
     */
    public static function getAutoptimizeCacheFiles(
        string $cache_dir,
        string $path_to_trim,
        string $prefix
        ) : array {

        $files = [];

        $directory = $cache_dir;

        if ( is_dir( $directory ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ( $iterator as $filename => $file_object ) {
                $path_crawlable = self::filePathLooksCrawlable( $filename );

                if ( $path_crawlable ) {
                    array_push(
                        $files,
                        $prefix .
                        home_url( str_replace( $path_to_trim, '', $filename ) )
                    );
                }
            }
        }

        return $files;
    }

    /**
     * @return string[] list of URLs
     */
    public static function getListOfLocalFilesByUrl( string $url ) : array {
        $files = [];

        $directory = str_replace( home_url( '/' ), ABSPATH, $url );

        if ( is_dir( $directory ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ( $iterator as $filename => $file_object ) {
                $path_crawlable = self::filePathLooksCrawlable( $filename );

                if ( $path_crawlable ) {
                    array_push(
                        $files,
                        home_url( str_replace( ABSPATH, '', $filename ) )
                    );
                }
            }
        }

        return $files;
    }

    public static function filePathLooksCrawlable( string $file_name ) : bool {
        $path_info = pathinfo( $file_name );

        if ( ! is_file( $file_name ) ) {
            return false;
        }

        $filenames_to_ignore = [
            '.DS_Store',
            '.PHP',
            '.SQL',
            '.git',
            '.idea',
            '.ini',
            '.map',
            '.php',
            '.sql',
            '.yarn',
            'WP-STATIC',
            '__MACOSX',
            'backwpup',
            'bower.json',
            'bower_components',
            'composer.json',
            'current-export',
            'gulpfile.js',
            'latest-export',
            'node_modules',
            'package.json',
            'pb_backupbuddy',
            'previous-export',
            'wp2static-processed-site',
            'wp2static-crawled-site',
            'thumbs.db',
            'vendor',
            'wp-static-html-output', // exclude earlier version exports
        ];

        foreach ( $filenames_to_ignore as $ignorable ) {
            if ( strpos( $file_name, $ignorable ) !== false ) {
                return false;
            }
        }

        if ( $path_info['basename'][0] === '.' ) {
            return false;
        }

        if ( ! isset( $path_info['extension'] ) ) {
            return false;
        }

        $extensions_to_ignore =
            [
                'php',
                'phtml',
                'tpl',
                'less',
                'scss',
                'po',
                'mo',
                'tar.gz',
                'zip',
                'txt',
                'po',
                'pot',
                'sh',
                'sh',
                'mo',
                'md',
            ];

        if ( in_array( $path_info['extension'], $extensions_to_ignore ) ) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed[] $settings
     */
    public static function buildInitialFileList(
        bool $via_cli = false,
        string $uploads_path,
        string $uploads_url,
        array $settings
        ) : int {
        $wp_site = new WPSite();

        $base_url = untrailingslashit( home_url() );

        // TODO: detect robots.txt, etc before adding
        $url_queue = array_merge(
            [ trailingslashit( $base_url ) ],
            [ '/robots.txt' ],
            [ '/favicon.ico' ],
            [ '/sitemap.xml' ]
        );

        switch ( $settings['detection_level'] ) {
            case 'homepage':
                break;
            case 'posts_and_pages':
                $url_queue = array_merge(
                    $url_queue,
                    self::getAllWPPostURLs( $base_url )
                );

                break;

            case 'everything':
            default:
                $url_queue = array_merge(
                    $url_queue,
                    self::getThemeFiles( 'parent' ),
                    self::getThemeFiles( 'child' ),
                    self::detectVendorFiles( $wp_site->site_url ),
                    self::getListOfLocalFilesByUrl( $uploads_url ),
                    self::getAllWPPostURLs( $base_url )
                );
        }

        $url_queue = self::cleanDetectedURLs( $url_queue );

        $url_queue = apply_filters(
            'statichtmloutput_modify_initial_crawl_list',
            $url_queue
        );

        $unique_urls = array_unique( $url_queue );
        sort( $unique_urls );

        $initial_crawl_list_total = count( $unique_urls );

        $str = implode( "\n", $unique_urls );

        file_put_contents(
            $uploads_path . '/WP-STATIC-INITIAL-CRAWL-LIST.txt',
            $str
        );

        chmod( $uploads_path . '/WP-STATIC-INITIAL-CRAWL-LIST.txt', 0664 );

        file_put_contents(
            $uploads_path . '/WP-STATIC-INITIAL-CRAWL-TOTAL.txt',
            $initial_crawl_list_total
        );

        chmod( $uploads_path . '/WP-STATIC-INITIAL-CRAWL-TOTAL.txt', 0664 );

        return count( $url_queue );
    }

    /**
     * @return string[] list of URLs
     */
    public static function getAllWPPostURLs( string $wp_site_url ) : array {
        global $wpdb;

        $query = "
            SELECT ID,post_type
            FROM %s
            WHERE post_status = '%s'
            AND post_type NOT IN ('%s','%s')";

        $posts = $wpdb->get_results(
            sprintf(
                $query,
                $wpdb->posts,
                'publish',
                'revision',
                'nav_menu_item'
            )
        );

        if ( ! $posts ) {
            return [];
        }

        $post_urls = [];
        $unique_post_types = [];

        foreach ( $posts as $post ) {
            if ( ! isset( $post->post_type ) ) {
                continue;
            }

            // capture all post types
            $unique_post_types[] = $post->post_type;

            $permalink = '';

            if ( isset( $post->ID ) ) {
                switch ( $post->post_type ) {
                    case 'page':
                        $permalink = get_page_link( $post->ID );
                        break;
                    case 'post':
                        $permalink = get_permalink( $post->ID );
                        break;
                    case 'attachment':
                        $permalink = get_attachment_link( $post->ID );
                        break;
                    default:
                        $permalink = get_post_permalink( $post->ID );
                        break;
                }
            }

            if ( ! is_string( $permalink ) ) {
                continue;
            }

            if ( strpos( $permalink, '?post_type' ) !== false ) {
                continue;
            }

            $post_urls[] = $permalink;

            /*
                Get the post's URL and each sub-chunk of the path as a URL

                  ie http://domain.com/2018/01/01/my-post/ to yield:

                    http://domain.com/2018/01/01/my-post/
                    http://domain.com/2018/01/01/
                    http://domain.com/2018/01/
                    http://domain.com/2018/
            */

            $link_path = parse_url( $permalink, PHP_URL_PATH );

            // we can skip any URLs without paths, as we're always detecting '/'
            if ( ! $link_path ) {
                continue;
            }

            // rely on WP's site URL vs reconstructing from parsed
            // subdomain, ie http://domain.com/mywpinstall/
            $wp_url = $wp_site_url . '/';

            // NOTE: Windows filepath support
            $path_segments = explode( '/', $link_path );

            // remove first and last empty elements
            array_shift( $path_segments );
            array_pop( $path_segments );

            $number_of_segments = count( $path_segments );

            // build each URL
            for ( $i = 0; $i < $number_of_segments; $i++ ) {
                $full_url = $wp_url;

                for ( $x = 0; $x <= $i; $x++ ) {
                    $full_url .= $path_segments[ $x ] . '/';
                }
                $post_urls[] = $full_url;
            }
        }

        // gets all category page links
        $args = [
            'public'   => true,
        ];

        $taxonomies = get_taxonomies( $args, 'objects' );

        $category_links = [];

        foreach ( $taxonomies as $taxonomy ) {
            if ( ! isset( $taxonomy->name ) ) {
                continue;
            }

            $terms = get_terms(
                $taxonomy->name,
                [
                    'hide_empty' => true,
                ]
            );

            if ( ! is_array( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $term_link = get_term_link( $term );

                if ( ! is_string( $term_link ) ) {
                    continue;
                }

                $permalink = trim( $term_link );
                $total_posts = $term->count;

                $term_url = str_replace(
                    $wp_site_url,
                    '',
                    $permalink
                );

                $category_links[ $term_url ] = $total_posts;

                $post_urls[] = $permalink;
            }
        }

        // get all pagination links for each category
        $category_pagination_urls =
            self::getPaginationURLsForCategories( $category_links );

        // get all pagination links for each post_type
        $post_pagination_urls =
            self::getPaginationURLsForPosts(
                array_unique( $unique_post_types )
            );

        // get all comment links
        $comment_pagination_urls =
            self::getPaginationURLsForComments( $wp_site_url );

        $post_urls = array_merge(
            $post_urls,
            $post_pagination_urls,
            $category_pagination_urls,
            $comment_pagination_urls
        );

        return $post_urls;
    }

    /**
     * @param string[] $urls to clean
     * @return string[] clean URLs
     */
    public static function cleanDetectedURLs( array $urls ) : array {
        if ( ! $urls ) {
            return [];
        }

        $unique_urls = array_unique( $urls );
        $wp_site_url = get_home_url();

        $url_queue = array_filter(
            $unique_urls,
            function ( string $url ) {
                return ( strpos( $url, ' ' ) === false );
            }
        );

        $stripped_urls = str_replace(
            $wp_site_url,
            '/',
            $url_queue
        );

        $cleaned_urls = str_replace(
            '//',
            '/',
            $stripped_urls
        );

        return $cleaned_urls;
    }

    /**
     * @param string[] $post_types to get pagination URLs from
     * @return string[] list of URLs
     */
    public static function getPaginationURLsForPosts( array $post_types ) : array {
        global $wpdb, $wp_rewrite;

        $pagination_base = $wp_rewrite->pagination_base;
        $default_posts_per_page = get_option( 'posts_per_page' );

        $urls_to_include = [];

        foreach ( $post_types as $post_type ) {
            $query = "
                SELECT ID,post_type
                FROM %s
                WHERE post_status = '%s'
                AND post_type = '%s'";

            $count = $wpdb->get_results(
                sprintf(
                    $query,
                    $wpdb->posts,
                    'publish',
                    $post_type
                )
            );

            $post_type_obj = get_post_type_object( $post_type );

            if ( ! $post_type_obj || ! isset( $post_type_obj->labels->name ) ) {
                continue;
            }

            $plural_form = strtolower( $post_type_obj->labels->name );

            $count = $wpdb->num_rows;

            $total_pages = ceil( $count / $default_posts_per_page );

            for ( $page = 1; $page <= $total_pages; $page++ ) {
                $pagination_url =
                    "/{$plural_form}/{$pagination_base}/{$page}";

                $urls_to_include[] = str_replace(
                    '/posts/',
                    '/',
                    $pagination_url
                );
            }
        }

        return $urls_to_include;
    }

    /**
     * @param mixed[] $categories with total counts
     * @return string[] list of URLs
     */
    public static function getPaginationURLsForCategories( array $categories ) : array {
        if ( ! $categories ) {
            return [];
        }

        global $wp_rewrite;

        $urls_to_include = [];
        $pagination_base = $wp_rewrite->pagination_base;
        $default_posts_per_page = get_option( 'posts_per_page' );

        foreach ( $categories as $term => $total_posts ) {
            $total_pages = ceil( $total_posts / $default_posts_per_page );

            for ( $page = 1; $page <= $total_pages; $page++ ) {
                $urls_to_include[] =
                    "{$term}/{$pagination_base}/{$page}";
            }
        }

        return $urls_to_include;
    }

    /**
     * @return string[] list of URLs
     */
    public static function getPaginationURLsForComments( string $wp_site_url ) : array {
        global $wp_rewrite;

        $comments_pagination_base = $wp_rewrite->comments_pagination_base;

        $comments = get_comments();

        if ( ! is_array( $comments ) ) {
            return [];
        }

        $urls_to_include = [];

        foreach ( $comments as $comment ) {
            $comment_url = get_comment_link( $comment->comment_ID );
            $comment_url = strtok( $comment_url, '#' );

            if ( ! is_string( $comment_url ) ) {
                continue;
            }

            $urls_to_include[] = str_replace(
                $wp_site_url,
                '',
                $comment_url
            );
        }

        return array_unique( $urls_to_include );
    }
}

