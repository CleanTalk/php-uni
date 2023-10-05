<?php

namespace Cleantalk\Updater;

use Cleantalk\Common\File;
use Cleantalk\Common\Helper;
use ZipArchive;

/**
 * CleanTalk Updater class.
 *
 * @Version       1.1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Updater {
	
	private $plugin_name;
	private $version_current;
	
	private $root_path;
	private $download_path;
	private $backup_path;
	
	public function __construct( $root_path ){
		
		$this->plugin_name     = $this->getPluginName();
		$this->version_current = $this->getCurrentVersion();
		
		$this->root_path = $root_path;
		$this->download_path = $root_path . DS . 'downloads' . DS;
		$this->backup_path = $root_path . DS . 'backup';
	}


    /**
     * Get the latest release number from the GitHub API.
     * Returns version number if success, array of error if not.
     * @return string|array
     */
    public function getLatestRelease()
    {
        $url = $this->getURLToCheckLatestReleaseVersion();
        if ( !$url ) {
            return array('error' => 'Error: Cannot create latest GitHub release URI.');
        }

        $github_response = Helper::http__request($url, array(), 'github_api');

        try {
            $github_response = json_decode($github_response, true, 512);
            if ( isset($github_response['name']) && is_string($github_response['name']) ) {
                return $github_response['name'];
            }
            return array('error' => 'Error: Cannot parse Github response.');
        } catch ( \JsonException  $e ) {
            return array('error' => 'Error: JSON exception while parsing Github repsonse: ' . $e);
        }

    }
	
	/**
	 * Assemble URL to check Universal plugins version archive
	 *
	 * @param $version
	 *
	 * @return string
	 */
	private function getURLToCheckLatestReleaseVersion(){
		
		switch( $this->plugin_name ){
			case 'uniforce':
				return 'https://api.github.com/repos/CleanTalk/php-usp/releases/latest';
			case 'uni':
                return 'https://api.github.com/repos/CleanTalk/php-uni/releases/latest';
            default:
                return false;
		}
	}
	
	private function getDownloadURL( $version ){
		
		$version = is_array( $version )
			? implode( '.', $version )
			: $version;
		
		switch( $this->plugin_name ){
			case 'uniforce':
				return 'https://github.com/CleanTalk/php-usp/releases/download/' . $version . '/UniForce-' . $version . '.zip';
				break;
			case 'uni':
                return 'https://github.com/CleanTalk/php-uni/releases/download/' . $version . '/php-uni-' . $version . '.zip';
				break;
		}
	}
	
	/**
	 * Split version to major, minor, fix parts.
	 * Set it to 0 if not found
	 *
	 * @param $version
	 *
	 * @return string
	 */
	private function versionStandardization( $version ){
		
		$version = $version === 'dev' ? '1.0.0' : $version;
		$version = explode('.', $version);
		$version = !empty($version) ? $version : array();
		
		// Version
		$version[0] = !empty($version[0]) ? (int)$version[0] : 0; // Major
		$version[1] = !empty($version[1]) ? (int)$version[1] : 0; // Minor
		$version[2] = !empty($version[2]) ? (int)$version[2] : 0; // Fix
		
		return $version;
	}
	
	/**
	 * @return string|null
	 */
	public function getCurrentVersion(){
		$version = defined( 'APBCT_VERSION' ) ? APBCT_VERSION : null;
		return $version
			? $this->versionStandardization( $version )
			: null;
	}
	
	/**
	 * @return mixed
	 */
	public function getPluginName(){
		return defined( 'APBCT_PLUGIN' ) ? APBCT_PLUGIN : null;
	}
	
	public function update( $current_version, $new_version ){
		
		$this->deleteDownloads();
		
		// Download
		$path = $this->downloadArchiveByVersion( $new_version );
		if( ! empty( $path['error'] ) )
			return $path;
		
		// Extract
		$extract_result = $this->extractArchive( $path, $new_version );
		if( ! empty( $extract_result['error'] ) )
			return $extract_result;
		
		// Backup
		if( ! $this->backup() )
			return array( 'error' => 'Fail to backup previous version.' );
		
		// Delete current
		if( ! File::delete( $this->root_path, array( 'downloads', 'backup', 'data', 'config.php' ) ) ){
			$rollback_result = $this->rollback() ? 'success' : 'failed';
			return array( 'error' => 'Fail to delete previous version. Rollback: ' . $rollback_result );
		}

		// Install
		if( ! $this->install( $new_version ) ){
			$rollback_result = $this->rollback() ? 'success' : 'failed';
			return array( 'error' => 'Fail install new version. Rollback: ' . $rollback_result );
		}

		// Update
		$update_result = $this->runUpdateActions( $current_version, $new_version );
		if( ! empty( $update_result['error'] ) ){
			$rollback_result = $this->rollback() ? 'success' : 'failed';
			return array( 'error' => $update_result['error'] . ' Rollback: ' . $rollback_result );
		}
		
		$this->deleteBackup();
		$this->deleteDownloads();
		
		return array( 'success' => true );
	}
	
	private function downloadArchiveByVersion( $version ){
		$url = $this->getDownloadURL( $version );
		return Helper::http__download_remote_file( $url, $this->download_path );
	}
	
	private function extractArchive( $path, $version ){
		
		$path_to_extract_in = $this->download_path . $version . DS;
		
		if( ! is_dir( $path_to_extract_in ) )
			mkdir( $path_to_extract_in );
		
		$zip = new ZipArchive();
		
		if( ! $zip->open( $path ) )
			return array( 'error' => 'Installation: Unable to open archive.' );
		
		if( ! $zip->extractTo( $path_to_extract_in ) )
			return array( 'error' => 'Installation: Fail to extract archive.' );
		
		$zip->close();
		
		return $path_to_extract_in;
	}
	
	/**
	 * Runs update scripts for each version
	 *
	 * @param $current_version
	 * @param $new_version
	 *
	 * @return array|bool
	 */
	private function runUpdateActions( $current_version, $new_version ){
		
		$current_version = self::versionStandardization( $current_version );
		$new_version     = self::versionStandardization( $new_version );
		
		$current_version_str = implode( '.', $current_version );
		$new_version_str     = implode( '.', $new_version );
		
		for( $ver_major = $current_version[0]; $ver_major <= $new_version[0]; $ver_major ++ ){
			for( $ver_minor = 0; $ver_minor <= 100; $ver_minor ++ ){
				for( $ver_fix = 0; $ver_fix <= 10; $ver_fix ++ ){
					
					if( version_compare( "{$ver_major}.{$ver_minor}.{$ver_fix}", $current_version_str, '<=' ) )
						continue;
					
					if( method_exists( $this, "update_to_{$ver_major}_{$ver_minor}_{$ver_fix}" ) ){
						$result = call_user_func( "update_to_{$ver_major}_{$ver_minor}_{$ver_fix}" );
						if( ! empty( $result['error'] ) ){
							return $result;
						}
					}
					
					if( version_compare( "{$ver_major}.{$ver_minor}.{$ver_fix}", $new_version_str, '>=' ) )
						break( 2 );
					
				}
			}
		}
		
		return true;
	}
	
	private function install( $new_version ){
		return File::copy(
			$this->download_path . $new_version . DS . 'cleantalk',
			$this->root_path,
			array( 'downloads', 'backup', 'data', 'config.php' )
		);
	}
	
	private function backup(){
		return File::copy( $this->root_path, $this->backup_path, array( 'downloads', 'backup', 'config.php' ) );
	}
	
	private function deleteBackup(){
		return File::delete( $this->backup_path );
	}
	
	private function deleteDownloads(){
		return File::delete( $this->download_path );
	}
	
	private function rollback(){
		if( File::copy( $this->backup_path, $this->root_path, array( 'downloads', 'backup', 'config.php' ) ) ){
			$this->deleteBackup();
			return true;
		}else
			return false;
	}

    /**
     * Update 2.5.3
     */
    function update_to_2_5_3()
    {
        global $apikey, $apbct_salt;

        File::clean__variable(CLEANTALK_CONFIG_FILE, 'apbct_salt');
        File::inject__variable(CLEANTALK_CONFIG_FILE, 'apbct_salt', apbct_checkjs_hash($apikey, $apbct_salt));
    }
}