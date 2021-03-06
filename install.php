#!/usr/bin/env php
<?php
/*
 * This file is part of Glial.
 *
 * (c) Aurelien LEQUOY <aurelien.lequoy@eysteme.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function display_help()
{
	echo <<<EOF
Glial Installer
------------------
Options
--help               	this help
--check              	for checking environment only
--force              	forces the installation
--application="../.."	install an existing application 
--install-dir="..."  	accepts a target installation directory

EOF;
}



/**
 * check the platform for possible issues on running glial
 */
function checkPlatform( $quiet )
{
	$errors		 = array();
	$warnings	 = array();

	$iniPath			 = php_ini_loaded_file();
	$displayIniMessage	 = false;
	if ( $iniPath )
	{
		$iniMessage = PHP_EOL . PHP_EOL . 'The php.ini used by your command-line PHP is: ' . $iniPath;
	}
	else
	{
		$iniMessage = PHP_EOL . PHP_EOL . 'A php.ini file does not exist. You will have to create one.';
	}
	$iniMessage .= PHP_EOL . 'If you can not modify the ini file, you can also run `php -d option=value` to modify ini values on the fly. You can use -d multiple times.';

	

	if ( ini_get( 'detect_unicode' ) )
	{
		$errors['unicode'] = 'On';
	}

	if ( extension_loaded( 'suhosin' ) )
	{
		$suhosin			 = ini_get( 'suhosin.executor.include.whitelist' );
		$suhosinBlacklist	 = ini_get( 'suhosin.executor.include.blacklist' );
		if ( false === stripos( $suhosin, 'phar' ) && (!$suhosinBlacklist || false !== stripos( $suhosinBlacklist, 'phar' )) )
		{
			$errors['suhosin'] = $suhosin;
		}
	}

	if ( !extension_loaded( 'Phar' ) )
	{
		$errors['phar'] = true;
	}

	if ( !ini_get( 'allow_url_fopen' ) )
	{
		$errors['allow_url_fopen'] = true;
	}

	if ( extension_loaded( 'ionCube Loader' ) && ioncube_loader_iversion() < 40009 )
	{
		$errors['ioncube'] = ioncube_loader_version();
	}

	if ( version_compare( PHP_VERSION, '5.4', '<' ) )
	{
		$errors['php'] = PHP_VERSION;
	}

	if ( version_compare( PHP_VERSION, '5.4.11', '<' ) )
	{
		$warnings['php'] = PHP_VERSION;
	}
	
	if ( !extension_loaded( 'gd' ) )
	{
		$errors['gd'] = true;
	}
	/*
	if ( !extension_loaded( 'mysqli' ) )
	{
		$errors['mysqli'] = true;
	}*/
	

	if ( !extension_loaded( 'openssl' ) )
	{
		$warnings['openssl'] = true;
	}

	if ( ini_get( 'apc.enable_cli' ) )
	{
		$warnings['apc_cli'] = true;
	}
	
	$res = shell_exec ("git --version");
	
	
	if (! preg_match("/^git version [1]\.[0-9]+\.[0-9]/i", $res, $gg))
	{
		$errors['git'] = true;
	}

	ob_start();
	phpinfo( INFO_GENERAL );
	$phpinfo = ob_get_clean();
	if ( preg_match( '{Configure Command(?: *</td><td class="v">| *=> *)(.*?)(?:</td>|$)}m', $phpinfo, $match ) )
	{
		$configure = $match[1];

		if ( false !== strpos( $configure, '--enable-sigchild' ) )
		{
			$warnings['sigchild'] = true;
		}

		if ( false !== strpos( $configure, '--with-curlwrappers' ) )
		{
			$warnings['curlwrappers'] = true;
		}
	}

	if ( !empty( $errors ) )
	{
		out( "Some settings on your machine make Glial unable to work properly.", 'error' );

		out( 'Make sure that you fix the issues listed below and run this script again:', 'error' );
		foreach ( $errors as $error => $current )
		{
			switch ( $error )
			{
				case 'gd':
					$text = PHP_EOL . "The gd extension is missing." . PHP_EOL;
					$text .= "Install it (\$ apt-get install php5-gd) or recompile php without --disable-gd";
					break;
					
				case 'git':
					$text = PHP_EOL . "The git software is missing." . PHP_EOL;
					$text .= "Install it (\$ apt-get install git)";
					break;
					
				case 'mysqli':
					$text = PHP_EOL . "The mysqli extension is missing." . PHP_EOL;
					$text .= "Install it or recompile php without --disable-mysqli";
					break;
					
				
				case 'phar':
					$text = PHP_EOL . "The phar extension is missing." . PHP_EOL;
					$text .= "Install it or recompile php without --disable-phar";
					break;

				case 'unicode':
					$text				 = PHP_EOL . "The detect_unicode setting must be disabled." . PHP_EOL;
					$text .= "Add the following to the end of your `php.ini`:" . PHP_EOL;
					$text .= "    detect_unicode = Off";
					$displayIniMessage	 = true;
					break;

				case 'suhosin':
					$text				 = PHP_EOL . "The suhosin.executor.include.whitelist setting is incorrect." . PHP_EOL;
					$text .= "Add the following to the end of your `php.ini` or suhosin.ini (Example path [for Debian]: /etc/php5/cli/conf.d/suhosin.ini):" . PHP_EOL;
					$text .= "    suhosin.executor.include.whitelist = phar " . $current;
					$displayIniMessage	 = true;
					break;

				case 'php':
					$text = PHP_EOL . "Your PHP ({$current}) is too old, you must upgrade to PHP 5.3.2 or higher.";
					break;

				case 'allow_url_fopen':
					$text				 = PHP_EOL . "The allow_url_fopen setting is incorrect." . PHP_EOL;
					$text .= "Add the following to the end of your `php.ini`:" . PHP_EOL;
					$text .= "    allow_url_fopen = On";
					$displayIniMessage	 = true;
					break;

				case 'ioncube':
					$text				 = PHP_EOL . "Your ionCube Loader extension ($current) is incompatible with Phar files." . PHP_EOL;
					$text .= "Upgrade to ionCube 4.0.9 or higher or remove this line (path may be different) from your `php.ini` to disable it:" . PHP_EOL;
					$text .= "    zend_extension = /usr/lib/php5/20090626+lfs/ioncube_loader_lin_5.3.so";
					$displayIniMessage	 = true;
					break;
					

			}
			if ( $displayIniMessage )
			{
				$text .= $iniMessage;
			}
			out( $text, 'info' );
		}

		out( '' );
		return false;
	}

	if ( !empty( $warnings ) )
	{
		out( "Some settings on your machine may cause stability issues with Glial.", 'error' );

		out( 'If you encounter issues, try to change the following:', 'error' );
		foreach ( $warnings as $warning => $current )
		{
			switch ( $warning )
			{
				case 'apc_cli':
					$text				 = PHP_EOL . "The apc.enable_cli setting is incorrect." . PHP_EOL;
					$text .= "Add the following to the end of your `php.ini`:" . PHP_EOL;
					$text .= "    apc.enable_cli = Off";
					$displayIniMessage	 = true;
					break;

				case 'sigchild':
					$text = PHP_EOL . "PHP was compiled with --enable-sigchild which can cause issues on some platforms." . PHP_EOL;
					$text .= "Recompile it without this flag if possible, see also:" . PHP_EOL;
					$text .= "    https://bugs.php.net/bug.php?id=22999";
					break;

				case 'curlwrappers':
					$text = PHP_EOL . "PHP was compiled with --with-curlwrappers which will cause issues with HTTP authentication and GitHub." . PHP_EOL;
					$text .= "Recompile it without this flag if possible";
					break;

				case 'openssl':
					$text = PHP_EOL . "The openssl extension is missing, which will reduce the security and stability of Glial." . PHP_EOL;
					$text .= "If possible you should enable it or recompile php with --with-openssl";
					break;

				case 'php':
					$text = PHP_EOL . "Your PHP ({$current}) is quite old, upgrading to PHP 5.3.4 or higher is recommended." . PHP_EOL;
					$text .= "Glial works with 5.3.2+ for most people, but there might be edge case issues.";
					break;
			}
			if ( $displayIniMessage )
			{
				$text .= $iniMessage;
			}
			out( $text, 'info' );
		}

		out( '' );
		return true;
	}

	if ( !$quiet )
	{
		out( "All settings correct for using Glial", 'success' );
	}
	return true;
}



/**
 * colorize output
 */
function out( $text, $color = null, $newLine = true )
{
	if ( DIRECTORY_SEPARATOR == '\\' )
	{
		$hasColorSupport = false !== getenv( 'ANSICON' );
	}
	else
	{
		$hasColorSupport = true;
	}

	$styles = array(
		'success'	 => "\033[0;32m%s\033[0m",
		'error'		 => "\033[31;31m%s\033[0m",
		'info'		 => "\033[33;33m%s\033[0m"
	);

	$format = '%s';

	if ( isset( $styles[$color] ) && $hasColorSupport )
	{
		$format = $styles[$color];
	}

	if ( $newLine )
	{
		$format .= PHP_EOL;
	}

	printf( $format, $text );
}



/**
 * installs composer to the current working directory
 */
function installComposer( $installDir, $quiet )
{
	$installPath = (is_dir( $installDir ) ? rtrim( $installDir, '/' ) . '/' : '') . 'composer.phar';
	$installDir	 = realpath( $installDir ) ? realpath( $installDir ) : getcwd();
	$file		 = $installDir . DIRECTORY_SEPARATOR . 'composer.phar';

	if ( is_readable( $file ) )
	{
		@unlink( $file );
	}

	$retries = 3;
	while ( $retries-- )
	{
		if ( !$quiet )
		{
			out( "Downloading...", 'info' );
		}

		$source			 = (extension_loaded( 'openssl' ) ? 'https' : 'http') . '://getcomposer.org/composer.phar';
		$errorHandler	 = new ErrorHandler();
		set_error_handler( array($errorHandler, 'handleError') );

		$fh = fopen( $file, 'w' );
		if ( !$fh )
		{
			out( 'Could not create file ' . $file . ': ' . $errorHandler->message, 'error' );
		}
		if ( !fwrite( $fh, file_get_contents( $source, false, getStreamContext() ) ) )
		{
			out( 'Download failed: ' . $errorHandler->message, 'error' );
		}
		fclose( $fh );

		restore_error_handler();
		if ( $errorHandler->message )
		{
			continue;
		}

		try
		{
			// test the phar validity
			$phar = new Phar( $file );
			// free the variable to unlock the file
			unset( $phar );
			break;
		}
		catch ( Exception $e )
		{
			if ( !$e instanceof UnexpectedValueException && !$e instanceof PharException )
			{
				throw $e;
			}
			unlink( $file );
			if ( $retries )
			{
				if ( !$quiet )
				{
					out( 'The download is corrupt, retrying...', 'error' );
				}
			}
			else
			{
				out( 'The download is corrupt (' . $e->getMessage() . '), aborting.', 'error' );
				exit( 1 );
			}
		}
	}

	if ( $errorHandler->message )
	{
		out( 'The download failed repeatedly, aborting.', 'error' );
		exit( 1 );
	}

	chmod( $file, 0755 );

	if ( !$quiet )
	{
		out( PHP_EOL . "Composer successfully installed to: " . $file, 'success', false );
		out( PHP_EOL . "Use it: php $installPath", 'info' );
	}
}


class ErrorHandler
{

	public $message = '';

	public function handleError( $code, $msg )
	{
		if ( $this->message )
		{
			$this->message .= "\n";
		}
		$this->message .= preg_replace( '{^copy\(.*?\): }', '', $msg );
	}

}


/**
 * function copied from Composer\Util\StreamContextFactory::getContext
 *
 * Any changes should be applied there as well, or backported here.
 */
function getStreamContext()
{
	$options = array('http' => array());

	// Handle system proxy
	if ( !empty( $_SERVER['HTTP_PROXY'] ) || !empty( $_SERVER['http_proxy'] ) )
	{
		// Some systems seem to rely on a lowercased version instead...
		$proxy = parse_url( !empty( $_SERVER['http_proxy'] ) ? $_SERVER['http_proxy'] : $_SERVER['HTTP_PROXY'] );
	}

	if ( !empty( $proxy ) )
	{
		$proxyURL = isset( $proxy['scheme'] ) ? $proxy['scheme'] . '://' : '';
		$proxyURL .= isset( $proxy['host'] ) ? $proxy['host'] : '';

		if ( isset( $proxy['port'] ) )
		{
			$proxyURL .= ":" . $proxy['port'];
		}
		elseif ( 'http://' == substr( $proxyURL, 0, 7 ) )
		{
			$proxyURL .= ":80";
		}
		elseif ( 'https://' == substr( $proxyURL, 0, 8 ) )
		{
			$proxyURL .= ":443";
		}

		// http(s):// is not supported in proxy
		$proxyURL = str_replace( array('http://', 'https://'), array('tcp://', 'ssl://'), $proxyURL );

		if ( 0 === strpos( $proxyURL, 'ssl:' ) && !extension_loaded( 'openssl' ) )
		{
			throw new \RuntimeException( 'You must enable the openssl extension to use a proxy over https' );
		}

		$options['http'] = array(
			'proxy'				 => $proxyURL,
			'request_fulluri'	 => true,
		);

		if ( isset( $proxy['user'] ) )
		{
			$auth = $proxy['user'];
			if ( isset( $proxy['pass'] ) )
			{
				$auth .= ':' . $proxy['pass'];
			}
			$auth = base64_encode( $auth );

			$options['http']['header'] = "Proxy-Authorization: Basic {$auth}\r\n";
		}
	}

	return stream_context_create( $options );
}


/**
 * installs Glial to the current working directory
 */

function installGlial($installDir, $Apps="new")
{

    
    $installDir  = realpath( $installDir ) ? realpath( $installDir ) : getcwd();

    
    
    	system("mkdir -p ".$installDir."/repository");

	
	$link_to_git = "git clone https://github.com/Esysteme/synapse.git";
	out($link_to_git."...","info");
	system("cd ".$installDir."/repository; ".$link_to_git);
	
	
	$link_to_git = "git clone https://github.com/Esysteme/glial.git";
	out($link_to_git."...","info");
	system("cd ".$installDir."/repository; ".$link_to_git);
	

	$link_to_git = "git clone https://github.com/Esysteme/Init-Glial.git";
	out($link_to_git."...","info");
	system("cd ".$installDir."/repository; ".$link_to_git);
	
	
	
	out("create tree directory ...","info");
	
	system("mkdir -p ".$installDir."/data/img");
	system("mkdir -p ".$installDir."/configuration");
	system("mkdir -p ".$installDir."/documentation");
	system("mkdir -p ".$installDir."/help");
	system("mkdir -p ".$installDir."/library");
	system("mkdir -p ".$installDir."/tmp");

	system("mkdir -p ".$installDir."/tmp/acl");
	system("mkdir -p ".$installDir."/tmp/crop");
	system("mkdir -p ".$installDir."/tmp/database");
	system("mkdir -p ".$installDir."/tmp/log");
	system("mkdir -p ".$installDir."/tmp/photos_in_wait");
	system("mkdir -p ".$installDir."/tmp/picture");
	system("mkdir -p ".$installDir."/tmp/translations");
	
	
	if ($Apps !== "new")
	{
		$tab = explode("/",$Apps);
		if (count($tab) !== 2)
		{
			die(out("You're application doesnt respect the format 'user/repository'","error"));
		}
		else
		{
            // case where path exist
            
            $link_to_git = "git clone https://github.com/".$Apps.".git";
			out($link_to_git."...","info");
            system("cd ".$installDir."/repository; ".$link_to_git);
            
	        system("cd ".$installDir."; ln -s ".$installDir."/repository/".$tab[1]."/application application");
		}
	}
	else
	{
        	// case of a new apps
        	system("mkdir -p ".$installDir."/application/webroot/js");
		system("mkdir -p ".$installDir."/application/webroot/css");
		system("mkdir -p ".$installDir."/application/webroot/file");
		system("mkdir -p ".$installDir."/application/webroot/video");
		system("mkdir -p ".$installDir."/application/webroot/image");
	}
	
	
	system("cd ".$installDir."; echo 'Glial 2.0' > index.php");
	
	out("create symlink","info");
	
	system("ln -s ".$installDir."/repository/synapse/system ".$installDir."/system");
	system("ln -s ".$installDir."/repository/glial/Glial ".$installDir."/library/Glial");
	
	//to update
	system("cd ".$installDir."/application/webroot/js; wget -q http://code.jquery.com/jquery-latest.min.js");
	
	system("cp -ar ".$installDir."/repository/Init-Glial/configuration/* ".$installDir."/configuration");
	system("cp -ar ".$installDir."/repository/Init-Glial/webroot/index.php ".$installDir."/application/webroot/index.php");
	
	
	system("cp -ar ".$installDir."/repository/Init-Glial/apache/root.dev.htacess ".$installDir."/.htaccess");
	system("cp -ar ".$installDir."/repository/Init-Glial/apache/webroot.dev.htacess ".$installDir."/application/webroot/.htaccess");
	
	
	system("cd ".$installDir.";cd ..; chown www-data:www-data -R *");
	
	//system("cd ".$installDir."/application/webroot/; php index.php administration init");
	

	system("find ".$installDir." -type f -exec chmod 440 {} \;;");
	system("find ".$installDir." -type d -exec chmod 550 {} \;;");
	
	system("find ".$installDir."/tmp -type f -exec chmod 660 {} \;;");
	system("find ".$installDir."/tmp -type d -exec chmod 770 {} \;;");

	//http://code.jquery.com/jquery-latest.min.js
	
	
	system("find ".$installDir."/tmp -type d -exec chmod 770 {} \;;");
	
	//test table and access right
	
	out("Glial has been installed successfully !","success");
	out("1 - To finish install update the path in ".$installDir."/configuration/webroot.config.php","info");
	out("2 - Set database informations in ".$installDir."/configuration/db.config.ini.php","info");
	out("3 - execute 'php ".$installDir."/application/webroot/index.php administration admin_table' (set table cache)","info");
	out("4 - execute 'php ".$installDir."/application/webroot/index.php administration admin_init' (set the rights)","info");
	out("Now you can access to you project by the url !","info");
}

/**
 * processes the installer
 */
function process( $argv )
{
	$check		 = in_array( '--check', $argv );
	$help		 = in_array( '--help', $argv );
	$force		 = in_array( '--force', $argv );
	$quiet		 = in_array( '--quiet', $argv );
	$installDir	 = false;

	foreach ( $argv as $key => $val )
	{
		if ( 0 === strpos( $val, '--install-dir' ) )
		{
			if ( 13 === strlen( $val ) && isset( $argv[$key + 1] ) )
			{
				$installDir = trim( $argv[$key + 1] );
			}
			else
			{
				$installDir = trim( substr( $val, 14 ) );
			}
		}
		
		
		if ( 0 === strpos( $val, '--application' ) )
		{
			if ( 13 === strlen( $val ) && isset( $argv[$key + 1] ) )
			{
				$Apps = trim( $argv[$key + 1] );
			}
			else
			{
				$Apps = trim( substr( $val, 14 ) );
			}
        }
        else
        {
            $Apps = "new";
        }
		
	}

	if ( $help )
	{
		display_help();
		exit( 0 );
	}

	$ok = checkPlatform( $quiet );

	if ( false !== $installDir && !is_dir( $installDir ) )
	{
		out( "The defined install dir ({$installDir}) does not exist.", 'info' );
		$ok = false;
	}

	if ( $check )
	{
		exit( $ok ? 0 : 1 );
	}

	if ( $ok || $force )
	{
		installComposer( $installDir, $quiet );
		installGlial($installDir, $Apps);
		exit( 0 );
	}

	exit( 1 );
}

process( $argv );


