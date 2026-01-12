<?php

// For some configurations, extensions are symbolic linked
// This is the workaround for ../..
$dir = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));

// This switches working directory to the root directory of MediaWiki.
// This is essential for the page to work
chdir($dir);

// Start up MediaWiki
require_once 'includes/PHPVersionCheck.php';
wfEntryPointCheck('avatar.php');

require 'includes/WebStart.php';

if ( !function_exists( 'mwMakeHash' ) ) {
    function mwMakeHash( $value ) {
        $hash = hash( 'fnv132', $value );
        return substr(
            base_convert( $hash, 16, 36 ),
            0,
            5
        );
    }
}

$query = $wgRequest->getQueryValues();

$path = null;

if (isset($query['user'])) {
	$username = $query['user'];

	if (isset($query['res'])) {
		$res = \Avatar\Avatars::normalizeResolution($query['res']);
	} else {
		global $wgDefaultAvatarRes;
		$res = $wgDefaultAvatarRes;
	}

	$user = User::newFromName($username);
	if ($user) {
		$path = \Avatar\Avatars::getAvatar($user, $res);
	}
}

$response = $wgRequest->response();
// 新增
if (filter_var($path, FILTER_VALIDATE_URL)) {
    // If $path is a full URL, redirect directly
    $response->statusHeader('302');
    $response->header('Location: ' . $path);
    if (!isset($query['nocache'])) {
        $response->header('Cache-Control: public, max-age=86400');
    }
    exit;
}
// In order to maximize cache hit and due to
// fact that default avatar might be external,
// always redirect
if ($path === null) {
	// We use send custom header, in order to control cache
	$response->statusHeader('302');

	if (!isset($query['nocache'])) {
		// Cache longer time if it is not the default avatar
		// As it is unlikely to be deleted
		$response->header('Cache-Control: public, max-age=3600');
	}

	global $wgDefaultAvatar;
	$response->header('Location: ' . $wgDefaultAvatar);

	$mediawiki = new MediaWiki();
	$mediawiki->doPostOutputShutdown('fast');
	exit;
}

switch($wgAvatarServingMethod) {
case 'readfile':
	global $wgAvatarUploadDirectory;
	$response->header('Cache-Control: public, max-age=86400');
	$response->header('Content-Type: image/png');
	readfile($wgAvatarUploadDirectory . $path);
	break;
case 'accel':
	global $wgAvatarUploadPath;
	$response->header('Cache-Control: public, max-age=86400');
	$response->header('Content-Type: image/png');
	$response->header('X-Accel-Redirect: ' . $wgAvatarUploadPath . $path);
	break;
case 'sendfile':
	global $wgAvatarUploadDirectory;
	$response->header('Cache-Control: public, max-age=86400');
	$response->header('Content-Type: image/png');
	$response->header('X-SendFile: ' . $wgAvatarUploadDirectory . $path);
	break;
case 'redirection':
default:
	$ver = '';
	
	// ver will be propagated to the relocated image
	if (isset($query['v'])) {
		// error_log("111", 3, '/www/sites/expanded/index/extensions/Avatar/avatar_debug.log');
        $ver = $query['v'];
    } elseif (isset($query['ver'])) {
        $ver = $query['ver'];
    } else {
		global $wgAvatarUploadDirectory;
		$timestamp = filemtime($wgAvatarUploadDirectory . $path);
		$ver = mwMakeHash($timestamp);
    }

	if ($ver) {
        if (strpos($path, '?') !== false) {
            $path .= '&v=' . $ver;
        } else {
            $path .= '?v=' . $ver;
        }
    } else {
		if (strpos($path, '?') !== false) {
            $path .= '&v=default';
        } else {
            $path .= '?v=default';
        }
	}

	// We use send custom header, in order to control cache
	$response->statusHeader('302');

	if (!isset($query['nocache'])) {
		// Cache longer time if it is not the default avatar
		// As it is unlikely to be deleted
		$response->header('Cache-Control: public, max-age=86400');
	}

	global $wgAvatarUploadPath;
	$response->header('Location: ' . $wgAvatarUploadPath . $path);
	break;
}

$mediawiki = new MediaWiki();
$mediawiki->doPostOutputShutdown('fast');
