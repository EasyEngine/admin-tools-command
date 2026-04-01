<?php
/**
 * Behat tests.
 *
 * @package ee-cli
 */

/* Start: Loading required files to enable EE::launch() in tests. */
define( 'EE_ROOT', __DIR__ . '/../..' );

include_once( EE_ROOT . '/php/class-ee.php' );
include_once( EE_ROOT . '/php/EE/Runner.php' );
include_once( EE_ROOT . '/php/utils.php' );

define( 'EE', true );
define( 'EE_VERSION', trim( file_get_contents( EE_ROOT . '/VERSION' ) ) );
define( 'EE_ROOT_DIR', '/opt/easyengine' );

require_once EE_ROOT . '/php/bootstrap.php';

if ( ! class_exists( 'EE\Runner' ) ) {
	require_once EE_ROOT . '/php/EE/Runner.php';
}

if ( ! class_exists( 'EE\Configurator' ) ) {
	require_once EE_ROOT . '/php/EE/Configurator.php';
}

$logger_dir = EE_ROOT . '/php/EE/Loggers';
$iterator   = new \DirectoryIterator( $logger_dir );

// Make sure the base class is declared first.
include_once "$logger_dir/Base.php";

foreach ( $iterator as $filename ) {
	if ( '.php' !== pathinfo( $filename, PATHINFO_EXTENSION ) ) {
		continue;
	}

	include_once "$logger_dir/$filename";
}
$runner = \EE::get_runner();
$runner->init_logger();
/* End. Loading required files to enable EE::launch() in tests. */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;

use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

define( 'EE_SITE_ROOT', EE_ROOT_DIR . '/sites' );

class FeatureContext implements Context
{
	public $command;
	public $webroot_path;
	public $ee_path;

	/**
	 * Initializes context.
	 */
	public function __construct()
	{
		$this->commands = [];
		$this->ee_path = getcwd();
	}

	/**
	 * @Given ee phar is generated
	 */
	public function eePharIsPresent()
	{
		// Checks if phar already exists, replaces it
		if( file_exists( 'ee-old.phar' ) ) {
			// Below exec call is required as currenly `ee cli update` is ran with root
			// which updates ee.phar with root privileges.
			exec( "sudo rm ee.phar" );
			copy( 'ee-old.phar','ee.phar' );
			return 0;
		}
		exec( "php -dphar.readonly=0 utils/make-phar.php ee.phar", $output, $return_status );
		if ( 0 !== $return_status ) {
			throw new Exception( "Unable to generate phar" . $return_status );
		}

		// Cache generaed phar as it is expensive to generate one
		copy( 'ee.phar','ee-old.phar' );
	}

	/**
	 * @Given :command is installed
	 */
	public function isInstalled( $command )
	{
		exec( "type " . $command, $output, $return_status );
		if ( 0 !== $return_status ) {
			throw new Exception( $command . " is not installed! Exit code is:" . $return_status );
		}
	}

	/**
	 * @When /I run '(.*)'|"(.*)"/
	 */
	public function iRun( $command )
	{
		$this->commands[] = EE::launch($command, false, true);
	}

	/**
	 * @When I try :command
	 */
	public function iTry( $command )
	{
		$this->commands[] = EE::launch($command, false, true);
	}

	/**
	 * @Then After delay of :time seconds
	 */
	public function afterDelayOfSeconds( $time )
	{
		sleep( $time );
	}

	/**
	 * @Then /(STDOUT|STDERR) should return exactly/
	 */
	public function stdoutShouldReturnExactly( $output_stream, PyStringNode $expected_output )
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /(STDOUT|STDERR) should return something like/
	 */
	public function stdoutShouldReturnSomethingLike( $output_stream, PyStringNode $expected_output )
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$expected_out = isset( $expected_output->getStrings()[0] ) ? $expected_output->getStrings()[0] : '';
		if ( strpos( $command_output, $expected_out ) === false ) {
			throw new Exception( "Actual output is:\n" . $command_output );
		}
	}

	/**
	 * @Then The ee should have admin-tools directory in root
	 */
	public function theEEShouldHaveToolsDir()
	{
		if ( ! file_exists( EE_ROOT_DIR . '/admin-tools' ) ) {
			throw new Exception( "The admin-tools directory has not been created!" );
		}
	}

	/**
	 * @Then The admin-tools should have index file
	 */
	public function theAdminToolsShouldHaveIndexFile()
	{
		if ( ! file_exists( EE_ROOT_DIR . '/admin-tools/index.php' ) ) {
			throw new Exception( "Admin Tools data not found!" );
		}
	}

	/**
	 * @Then The site :site should have index file
	 */
	public function theSiteShouldHaveIndexFile( $site )
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site . "/app/htdocs/index.php" ) ) {
			throw new Exception( "PHP site data not found!" );
		}
	}

	/**
	 * @Then The site :site should have WordPress
	 */
	public function theSiteShouldHaveWordpress($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site . "/app/wp-config.php" ) ) {
			throw new Exception("WordPress data not found!");
		}
	}

	/**
	 * @Then Request on :site should contain following headers:
	 */
	public function requestOnShouldContainFollowingHeaders( $site, TableNode $table )
	{
		$url = 'http://' . $site;

		$ch  = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_VERBOSE, true );

		$headers = curl_exec( $ch );

		curl_close($ch);

		$rows = $table->getHash();

		foreach ( $rows as $row ) {
			if ( strpos( $headers, $row['header'] ) === false ) {
				throw new Exception( "Unable to find " . $row['header'] . "\nActual output is : " . $headers );
			}
		}
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupScenario( AfterScenarioScope $scope )
	{
		$this->commands = [];
		chdir( $this->ee_path );
	}

	/**
	 * @AfterFeature
	 */
	public static function cleanup( AfterFeatureScope $scope )
	{
		$test_sites = [
			'php.test',
			'wp.test'
		];

		$result          = EE::launch( 'sudo bin/ee site list --format=text', false,  true );
		$running_sites   = explode( "\n", $result->stdout );
		$sites_to_delete = array_intersect( $test_sites, $running_sites );

		foreach ( $sites_to_delete as $site ) {
			exec( "sudo bin/ee site delete $site --yes" );
		}

		if( file_exists( 'ee.phar' ) ) {
			unlink( 'ee.phar' );
		}

		if( file_exists( 'ee-old.phar' ) ) {
			unlink( 'ee-old.phar' );
		}
	}

}
