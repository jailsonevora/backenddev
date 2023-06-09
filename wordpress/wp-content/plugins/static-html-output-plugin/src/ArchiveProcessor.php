<?php

namespace StaticHTMLOutput;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class ArchiveProcessor extends StaticHTMLOutput {

    /**
     * @var Archive
     */
    public $archive;
    /**
     * @var string
     */
    public $target_folder;

    public function __construct() {
        $this->archive = new Archive();
        $this->archive->setToCurrentArchive();

        $this->loadSettings(
            [
                'wpenv',
                'crawling',
                'advanced',
                'processing',
                'netlify',
                'zip',
                'folder',
            ]
        );
    }

    public function renameWPDirectory( string $source, string $target ) : void {
        if ( empty( $source ) || empty( $target ) ) {
            WsLog::l(
                'Failed trying to rename: ' .
                'Source: ' . $source .
                ' to: ' . $target
            );
            die();
        }

        $original_dir = $this->archive->path . $source;
        $new_dir = $this->archive->path . $target;

        if ( is_dir( $original_dir ) ) {
            $this->recursive_copy( $original_dir, $new_dir );

            FilesHelper::delete_dir_with_files(
                $original_dir
            );
        } else {
            WsLog::l(
                'Trying to rename non-existent directory: ' .
                $original_dir
            );
        }
    }

    public function recursive_copy( string $srcdir, string $dstdir ) : void {
        $dir = opendir( $srcdir );

        if ( ! is_resource( $dir ) ) {
            return;
        }

        if ( ! is_dir( $dstdir ) ) {
            mkdir( $dstdir );
        }

        while ( $file = readdir( $dir ) ) {
            if ( $file != '.' && $file != '..' ) {
                $src = $srcdir . '/' . $file;
                $dst = $dstdir . '/' . $file;
                if ( is_dir( $src ) ) {
                    $this->recursive_copy( $src, $dst );
                } else {
                    copy( $src, $dst );
                }
            }
        }

        closedir( $dir );
    }

    public function dir_is_empty( string $dirname ) : bool {
        if ( ! is_dir( $dirname ) ) {
            return false;
        }

        $dotfiles = [ '.', '..', '/.statichtmloutput_safety' ];

        $dir_files = scandir( $dirname );

        if ( ! $dir_files ) {
            return false;
        }

        foreach ( $dir_files as $file ) {
            if ( ! in_array( $file, $dotfiles ) ) {
                 return false;
            }
        }

        return true;
    }

    public function dir_has_safety_file( string $dirname ) : bool {
        if ( ! is_dir( $dirname ) ) {
            return false;
        }

        $dir_files = scandir( $dirname );

        if ( ! $dir_files ) {
            return false;
        }

        foreach ( $dir_files as $file ) {
            if ( $file == '.statichtmloutput_safety' ) {
                 return true;
            }
        }

        return false;
    }

    public function put_safety_file( string $dirname ) : bool {
        if ( ! is_dir( $dirname ) ) {
            return false;
        }

        $safety_file = $dirname . '/.statichtmloutput_safety';
        $result = file_put_contents( $safety_file, 'statichtmloutput' );

        if ( ! $result ) {
            return false;
        }

        chmod( $safety_file, 0664 );

        return true;
    }

    public function copyStaticSiteToPublicFolder() : void {
        if ( $this->settings['selected_deployment_option'] === 'folder' ) {
            $target_folder = trim( $this->settings['targetFolder'] );
            $this->target_folder = $target_folder;

            if ( ! $target_folder ) {
                return;
            }

            // instantiate with safe defaults
            $directory_exists = true;
            $directory_empty = false;
            $dir_has_safety_file = false;

            // CHECK #1: directory exists or can be created
            $directory_exists = is_dir( $target_folder );

            if ( $directory_exists ) {
                $directory_empty = $this->dir_is_empty( $target_folder );
            } else {
                if ( wp_mkdir_p( $target_folder ) ) {
                    if ( ! $this->put_safety_file( $target_folder ) ) {
                        WsLog::l(
                            'Couldn\'t put safety file in ' .
                            'Target Directory' .
                            $target_folder
                        );

                        die();
                    }
                } else {
                    WsLog::l(
                        'Couldn\'t create Target Directory: ' .
                        $target_folder
                    );

                    die();
                }
            }

            // CHECK #2: check directory empty and add safety file
            if ( $directory_empty ) {
                if ( ! $this->put_safety_file( $target_folder ) ) {
                    WsLog::l(
                        'Couldn\'t put safety file in ' .
                        'Target Directory' .
                        $target_folder
                    );

                    die();
                }
            }

            $dir_has_safety_file =
                $this->dir_has_safety_file( $target_folder );

            if ( $directory_empty || $dir_has_safety_file ) {
                $this->recursive_copy(
                    $this->archive->path,
                    $this->target_folder
                );

                if ( ! $this->put_safety_file( $target_folder ) ) {
                    WsLog::l(
                        'Couldn\'t put safety file in ' .
                        'Target Directory' .
                        $target_folder
                    );

                    die();
                }
            } else {
                WsLog::l(
                    'Target Directory wasn\'t empty ' .
                    'or didn\'t contain safety file ' .
                    $target_folder
                );

                die();
            }
        }
    }

    public function createNetlifySpecialFiles() : void {
        if ( $this->settings['selected_deployment_option'] !== 'netlify' ) {
            return;
        }

        if ( isset( $this->settings['netlifyRedirects'] ) ) {
            $redirect_content = $this->settings['netlifyRedirects'];
            $redirect_path = $this->archive->path . '_redirects';
            file_put_contents( $redirect_path, $redirect_content );
            chmod( $redirect_path, 0664 );
        }

        if ( isset( $this->settings['netlifyHeaders'] ) ) {
            $header_content = $this->settings['netlifyHeaders'];
            $header_path = $this->archive->path . '_headers';
            file_put_contents( $header_path, $header_content );
            chmod( $header_path, 0664 );
        }
    }

    public function create_zip() : void {
        $deployer = $this->settings['selected_deployment_option'];

        if ( ! in_array( $deployer, [ 'zip', 'netlify' ] ) ) {
            return;
        }

        $archive_path = rtrim( $this->archive->path, '/' );
        $temp_zip = $archive_path . '.tmp';

        $zip_archive = new ZipArchive();

        if ( $zip_archive->open( $temp_zip, ZIPARCHIVE::CREATE ) !== true ) {
            WsLog::l( 'Could not create archive' );
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->archive->path )
        );

        foreach ( $iterator as $filename => $file_object ) {
            $base_name = basename( $filename );
            if ( $base_name != '.' && $base_name != '..' ) {
                $realpath = realpath( $filename );

                if ( ! $realpath ) {
                    continue;
                }

                if ( ! $zip_archive->addFile(
                    $realpath,
                    str_replace( $this->archive->path, '', $filename )
                )
                ) {
                    WsLog::l( 'Could not add file: ' . $filename );
                    return;
                }
            }
        }

        $zip_archive->close();

        $zip_path = $this->settings['wp_uploads_path'] . '/' .
            $this->archive->name . '.zip';

        rename( $temp_zip, $zip_path );
    }

    public function removeWPCruft() : void {
        if ( file_exists( $this->archive->path . '/xmlrpc.php' ) ) {
            unlink( $this->archive->path . '/xmlrpc.php' );
        }

        if ( file_exists( $this->archive->path . '/wp-login.php' ) ) {
            unlink( $this->archive->path . '/wp-login.php' );
        }

        FilesHelper::delete_dir_with_files(
            $this->archive->path . '/wp-json/'
        );
    }

    public function renameArchiveDirectories() : void {
        if ( ! isset( $this->settings['rename_rules'] ) ) {
            return;
        }

        $rename_rules = explode(
            "\n",
            str_replace( "\r", '', $this->settings['rename_rules'] )
        );

        foreach ( $rename_rules as $rename_rule_line ) {
            list($original_dir, $target_dir) =
                explode( ',', $rename_rule_line );

            $this->renameWPDirectory( $original_dir, $target_dir );
        }

    }
}

