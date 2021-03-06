<?php
/*
 *
 * OGP - Open Game Panel
 * Copyright (C) Copyright (C) 2008 - 2013 The OGP Development Team
 *
 * http://www.opengamepanel.org/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */
 
// Ignore any request with unwanted values at 'm' or 'p'
if( isset($_REQUEST['m']) and !preg_match("/^([a-z]|[0-9]|_|-)+$/i", $_REQUEST['m']) )
	unset($_POST['m'], $_GET['m'], $_REQUEST['m']);
if( isset($_REQUEST['p']) and ( !isset($_REQUEST['m']) or !preg_match("/^([a-z]|[0-9]|_|-)+$/i", $_REQUEST['p']) ) )
{
	if( isset($_REQUEST['m']) )
		unset($_POST['m'], $_GET['m'], $_REQUEST['m']);
	unset($_POST['p'], $_GET['p'], $_REQUEST['p']);
}

if(file_exists(__DIR__ . "/lang.php")){
	require_once(__DIR__ . "/lang.php");
}else{
	require_once("lang.php");
}


/// \return the database object when the creation was successfull.
/// \return FALSE if database type was invalid.
/// \return negative value in case of error
function createDatabaseConnection($db_type,$db_host,$db_user,$db_pass,$db_name,$table_prefix)
{
    if ( $db_type == "mysql" )
    {
		if ( function_exists('mysqli_connect') )
			require_once("includes/database_mysqli.php");
		else
			require_once("includes/database_mysql.php");
        $database = new OGPDatabaseMysql();
        $connect_value = $database->connect($db_host,$db_user,$db_pass,$db_name,$table_prefix);
        
        if ($connect_value === TRUE)
            return $database;

        // See return values from database classes.
        return $connect_value;
    }
    else
    {
        return -98;
    }
}

function get_db_error_text ($db_retval, &$error_text)
{
    if (is_a($db_retval,"OGPDatabase"))
        return FALSE;

    switch ($db_retval) {
    case -1:
        $error_text = get_lang("db_error_invalid_host");
        break;
    case -11:
        $error_text = get_lang("db_error_invalid_user_and_pass");
        break;
    case -12:
        $error_text = get_lang("db_error_invalid_database");
        break;
    case -98:
        $error_text = get_lang("db_error_invalid_db_type");
        break;
    case -99:
        $error_text = get_lang("db_error_module_missing");
        break;
    default:
        $error_text = get_lang_f("db_unknown_error",$db_retval);
        break;
    }

    return TRUE;
}

// Create a list of files or folders and store them in an array
function makefilelist($folder, $filter, $sort=true, $type="files") {
    $res = array();
    $filter = explode("|", $filter);
    $temp = opendir($folder);
    while ($file = readdir($temp)) {
        if ($type == "files" && !in_array($file, $filter)) {
            if (!is_dir($folder.$file)) $res[] = $file;
        } elseif ($type == "folders" && !in_array($file, $filter)) {
            if (is_dir($folder.$file)) $res[] = $file;
        }
    }
    closedir($temp);
    if ($sort) sort($res);
    return $res;
}

function isPortValid($port)
{
    return ( $port > 0 && $port <= 65535 );
}

function cleanFilenames($file_array)
{
    $retval = array();
    foreach($file_array as $file_name)
    {
        if($file_name === "." && $file_name === "..")
            continue;
        /// \todo @ is because of files without . in the name.extension.
        @list($value, $ext) = explode(".", $file_name);
        array_push($retval,$value);
    }
    return $retval;
}

function clean_id_string($id_string)
{
    return preg_replace("/-/","",$id_string);
}

function get_first_existing_file($paths)
{
    foreach ($paths as $path)
    {
		if(preg_match("/^http/", $path))
		{
			if(cURLEnabled()){
				// Get headers using cURL
				$file_headers = @get_headers_curl($path);
			}else{
				// Five second timeout...
				$origSocketTimeout = ini_get('default_socket_timeout');
				ini_set('default_socket_timeout', 5);
				
				// Get headers with a socket timeout value of 5 seconds...
				$file_headers = @get_headers($path);
				
				// Reset timeout to old value
				ini_set('default_socket_timeout', $origSocketTimeout);
			}
			if(trim($file_headers[0]) == 'HTTP/1.0 200 OK' || trim($file_headers[0]) == 'HTTP/1.1 200 OK') return $path;
		}
		
        if (file_exists($path)) return $path;
    }
}

function cURLEnabled(){
    return function_exists('curl_version');
}

function get_headers_curl($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_HEADER,         true);
    curl_setopt($ch, CURLOPT_NOBODY,         true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    // 5 second timeout should be reasonable...
    curl_setopt($ch, CURLOPT_TIMEOUT,        5);

    $r = curl_exec($ch);
    $r = explode("\n", $r);
    if(is_array($r)){
		$r = array_filter($r, 'strlen');
		
		// Get rid of the last index value which appears to be holding odd characters
		array_pop($r);
	}
    return $r;
}

function clean_path($path)
{
    // Replace multiple / or \ marks with one /.
    return preg_replace("/[\/|\\\\]+/","/",$path);
}

function sanitizeInputStr($strToProcess, $removeHTML = true, $trim = true, $removeQuotes = true){
	// Remove quotes from string
	if($removeQuotes){
		// For magic quotes or addslashes values
		$strToProcess = str_replace('\"', '', $strToProcess);
		$strToProcess = str_replace("\'", "", $strToProcess);
		
		// Remove any possible leftovers
		$strToProcess = str_replace('"', '', $strToProcess);
		$strToProcess = str_replace("'", "", $strToProcess);
	}
	
	// Trim string value
	if($trim){
		$strToProcess = trim($strToProcess);
	}
	
	// Remove HTML tags
	if($removeHTML){
		$strToProcess = strip_tags($strToProcess);
	}
	
	// Return the processed string
	return $strToProcess;
}

function startSession(){
	if(!isset($_SESSION)){
		session_name("opengamepanel_web");
		session_start();
	}
}

function updateGameConfigsPostInstall($clear_old = false){
	global $db;
	
	if(file_exists('modules/config_games/server_config_parser.php')){
		require_once('modules/config_games/server_config_parser.php');
	}else{
		require_once(__DIR__ . '/../modules/config_games/server_config_parser.php');
	}
	
	if(function_exists("read_server_config")){
		removeOldGameConfigs();
		
		$files = glob(SERVER_CONFIG_LOCATION."*.xml");

		if ( empty($files) )
		{
		  return false;
		}

		$db->clearGameCfgs($clear_old);
		$counter = 0;

		foreach ( $files as $config_file )
		{
			$config = read_server_config($config_file);
			if ( !$db->addGameCfg($config) )
			{
				$counter++;
				continue;
			}
		}
		
		if($counter == count($files)){
			return false;
		}
		
		return true;
	}
	
    return false;
}

function isCoreModule($module){
	$coreModules = array('modulemanager', 'server', 'settings', 'gamemanager', 'config_games', 'administration', 'user_games', 'user_admin', 'update');
	if(in_array($module, $coreModules)){
		return true;
	}
	
	return false;
}

function recursiveDelete($str) {
	if (is_file($str)) {
		return @unlink($str);
	}else if(is_dir($str)){
		// Strip the trailing slash from the directory if there is one
		$str = rtrim($str,'/');
		
		// Get the index of the last slash in the path so that we can pull just the relative folder name being scanned
		$lastSlash = strrpos($str, "/");
		
		if($lastSlash != false){
			// Get the folder name so we can ignore "." and ".." which relates to current directory and up a level
			$folder = substr($str, $lastSlash + 1);
			
			if($folder != ".." && $folder != "."){
				$scan = glob($str . '/{,.}*', GLOB_BRACE);
				if(isset($scan) && is_array($scan)){
					foreach($scan as $index=>$path) {
						recursiveDelete($path);
					}
				}
				return @rmdir($str);
			}
		}
	}
	
	return true;
}

function removeOldGameConfigs(){ // Wrote this function in-case we rename config files like we did for TS3 (https://sourceforge.net/p/hldstart/svn/3376/)
	$oldConfigsToRemove = array(
		'modules/config_games/server_configs/teamspeak3_32bit.xml', 
		'modules/config_games/server_configs/teamspeak3_64bit.xml'
	);
	
	foreach($oldConfigsToRemove as $config){
		if(file_exists($config)){
			unlink($config);
		}else{
			if(file_exists(__DIR__ . "/../" . $config)){
				unlink($config);
			}
		}
	}	 
}
?>
