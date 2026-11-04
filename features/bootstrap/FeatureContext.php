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

$root_alias = '/opt';
if ( 'Darwin' === php_uname( 's' ) ) {
	$sys_name   = posix_getpwuid(posix_geteuid())['name'];
	if ( ! empty( $sys_name ) ) {
		$root_alias = '/Users/' . $sys_name;
	}
}
if ( ! defined( 'EE_ROOT_DIR' ) ) {
	define( 'EE_ROOT_DIR', $root_alias . '/easyengine' );
}

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
$runner->init_config();
/* End. Loading required files to enable EE::launch() in tests. */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;

use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

define( 'EE_SITE_ROOT', EE_ROOT_DIR . '/sites' );

class FeatureContext implements Context
{
	/**
	 * Whether this run built ee.phar/ee-old.phar, so the cleanup only removes its own files.
	 *
	 * @var bool
	 */
	private static $phar_built = false;

	public $command;
	public $commands;
	public $webroot_path;
	public $ee_path;

	/**
	 * Only sites whose name starts with this prefix are ever deleted by this suite.
	 */
	const SITE_PREFIX = 'bh-admin-tools-command-';

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
		if(file_exists('ee-old.phar')) {
			// Below exec call is required as currenly `ee cli update` is ran with root
			// which updates ee.phar with root privileges.
			exec("sudo rm ee.phar");
			copy('ee-old.phar','ee.phar');
			self::$phar_built = true;
			return 0;
		}
		exec("php -dphar.readonly=0 utils/make-phar.php ee.phar", $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception("Unable to generate phar" . $return_status);
		}

		// Cache generaed phar as it is expensive to generate one
		copy('ee.phar','ee-old.phar');
		self::$phar_built = true;
	}

	/**
	 * @Given :command is installed
	 */
	public function isInstalled($command)
	{
		exec("type " . $command, $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception($command . " is not installed! Exit code is:" . $return_status);
		}
	}

	/**
	 * Anchored (the upstream pattern matched any step containing a double-quoted string).
	 *
	 * @When /^I run '([^']*)'$/
	 */
	public function iRun($command)
	{
		$this->commands[] = EE::launch($command, false, true);
	}

	/**
	 * @Then return value should be 0
	 */
	public function returnValueShouldBe0()
	{
		if ( 0 !== $this->commands[0]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then return value of command :index should be 0
	 */
	public function returnValueOfCommandShouldBe0($index)
	{
		if ( 0 !== $this->commands[ $index - 1 ]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then After delay of :time seconds
	 */
	public function afterDelayOfSeconds( $time )
	{
		sleep( $time );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return exactly$/
	 */
	public function stdoutShouldReturnExactly($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return exactly$/
	 */
	public function stdoutOfCommandShouldReturnExactly($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return something like$/
	 */
	public function stdoutShouldReturnSomethingLike($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}


	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return something like$/
	 */
	public function stdoutOfCommandShouldReturnSomethingLike($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then The site :site should be :type multisite
	 */
	public function theSiteShouldBeMultisite( $site, $type )
	{
		$result = EE::launch( "cd " . EE_SITE_ROOT . "/$site && docker-compose exec --user='www-data' php sh -c 'wp config get SUBDOMAIN_INSTALL'", false, true );

		if( $result->stderr ) {
			throw new Exception("Found error while executing command: $result->stderr");
		}

		if($type === 'subdomain' && trim($result->stdout) !== '1') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be 1. Got: $result->stdout");
		}
		else if($type === 'subdir' && trim($result->stdout) !== '') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be empty. Got: $result->stdout");
		}
		else if($type !== 'subdomain' && $type !== 'subdir') {
			throw new Exception("Found unknown site type: $type");
		}
	}

	/**
	 * @Then The :site db entry should be removed
	 */
	public function theDbEntryShouldBeRemoved($site)
	{
		$out = shell_exec("sudo bin/ee site list");
		if (strpos($out, $site) !== false) {
			throw new Exception("$site db entry not been removed!");
		}

	}

	/**
	 * @Then The :site webroot should be removed
	 */
	public function theWebrootShouldBeRemoved($site)
	{
		if ( file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been removed!");
		}
	}

	/**
	 * @Then Following containers of site :site should be removed:
	 */
	public function followingContainersOfSiteShouldBeRemoved($site, TableNode $table)
	{
		$containers = $table->getHash();
		$site_name = implode(explode('.', $site));

		foreach ($containers as $container) {

			$sevice = $container['container'];
			$container_name = $site_name . '_' . $sevice . '_1';

			exec("docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return);
			if (!$return) {
				throw new Exception("$container_name has not been removed!");
			}
		}
	}

	/**
	 * @Then The site :site should have webroot
	 */
	public function theSiteShouldHaveWebroot($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been created!");
		}
	}

	/**
	 * @Then The site :site should have WordPress
	 */
	public function theSiteShouldHaveWordpress($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site . '/app/wp-config.php' ) ) {
			throw new Exception("WordPress data not found!");
		}
	}

	/**
	 * @Then Request on :site should contain following headers:
	 */
	public function requestOnShouldContainFollowingHeaders($site, TableNode $table)
	{
		$url = 'http://' . $site;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with header :header should contain following headers:
	 */
	public function requestOnWithHeaderShouldContainFollowingHeaders($host, $header, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [ $header ]);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with resolve option :resolve should contain following headers:
	 */
	public function requestOnWithResolveOptionShouldContainFollowingHeaders($host, $resolve, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RESOLVE, [ $resolve ]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);
		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then There should be :expected_running_containers containers with labels
	 */
	public function thereShouldBeContainersWithLabel($expected_running_containers, PyStringNode $pyStringNode)
	{
		$labels = $pyStringNode->getStrings();
		$label_string = implode(' -f label=',$labels);

		$result = EE::launch( "docker ps -qf label=$label_string | wc -l", false, true );
		$running_containers = (int) trim($result->stdout);

		// Upstream compared a string to an int with === and threw on a match.
		if ( (int) $expected_running_containers !== $running_containers ) {
			throw new Exception("Expected $expected_running_containers running containers. Found: $running_containers");
		}
	}

	/* ===================================================================================
	 * Shared steps (generic, meant to be reused by other package suites).
	 * =================================================================================== */

	/** @var array|null Last HTTP response: code, headers, body, url. */
	private $response;

	/** @var string IP that request hostnames resolve to; defaults to the host's public IP. */
	private $request_ip;

	/** @var array|null Basic auth [user, pass] for requests. */
	private $request_auth;

	/** @var array Paths moved aside by a step, restored after the scenario: original => backup. */
	private $moved_paths = [];

	/** @var array|null Snapshot of the global http auth state (DB rows and proxy files). */
	private $auth_snapshot;

	/** @var string|null Last response body kept by `I remember the response body`. */
	private $remembered_body;

	/**
	 * The auth snapshot is also kept on disk so a run killed mid-scenario (fatal error, Ctrl-C) can be restored by the next run.
	 */
	const AUTH_SNAPSHOT_FILE = '/var/tmp/behat-admin-tools-command-auth-snapshot.json';

	const ASIDE_SUFFIX = '.behat-aside';

	private static function db() {
		$pdo = new PDO( 'sqlite:' . EE_ROOT_DIR . '/db/ee.sqlite' );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		return $pdo;
	}

	private static function db_rows( $sql, array $params = [] ) {
		$stmt = self::db()->prepare( $sql );
		$stmt->execute( $params );

		return $stmt->fetchAll( PDO::FETCH_ASSOC );
	}

	private static function site_row( $site ) {
		$rows = self::db_rows( 'SELECT * FROM sites WHERE site_url = ?', [ $site ] );

		return empty( $rows ) ? null : $rows[0];
	}

	private static function proxy_container() {
		return defined( 'EE_PROXY_TYPE' ) ? EE_PROXY_TYPE : 'services_global-nginx-proxy_1';
	}

	private static function public_ip() {
		$ip = getenv( 'BEHAT_PUBLIC_IP' );
		if ( empty( $ip ) ) {
			$ip = trim( (string) shell_exec( "hostname -I | awk '{print $1}'" ) );
		}
		if ( empty( $ip ) ) {
			throw new Exception( 'Could not determine the public IP of this host (set BEHAT_PUBLIC_IP).' );
		}

		return $ip;
	}

	/**
	 * Replaces `{frontend_subnet}` with the global frontend network's subnet (the default whitelist); it is 10.1.0.0/16 unless the host already routes that range.
	 */
	private static function expand( $text ) {
		if ( false === strpos( $text, '{frontend_subnet}' ) ) {
			return $text;
		}
		$rows = self::db_rows( "SELECT value FROM options WHERE key = 'frontend_subnet_ip'" );
		if ( empty( $rows ) ) {
			throw new Exception( 'Option frontend_subnet_ip is not set.' );
		}

		return str_replace( '{frontend_subnet}', $rows[0]['value'], $text );
	}

	private function last_command() {
		if ( empty( $this->commands ) ) {
			throw new Exception( 'No command has been run in this scenario.' );
		}

		return end( $this->commands );
	}

	private static function clean_output( $output ) {
		return str_replace( [ "\r\n", "\033[1;31m", "\033[0m", "\033[32m", "\033[33m", "\033[34m" ], [ "\n", '', '', '', '', '' ], (string) $output );
	}

	private static function describe( $run ) {
		return sprintf( "Command: %s\nExit code: %s\nSTDOUT:\n%s\nSTDERR:\n%s", $run->command, $run->return_code, $run->stdout, $run->stderr );
	}

	private static function run_or_fail( $command ) {
		$run = EE::launch( $command, false, true );
		if ( 0 !== $run->return_code ) {
			throw new Exception( "Setup command failed.\n" . self::describe( $run ) );
		}

		return $run;
	}

	/**
	 * Regenerates the proxy config (the template checks htpasswd/acl files with `exists`) and reloads it.
	 */
	private static function reload_global_proxy() {
		$proxy = self::proxy_container();
		self::run_or_fail( sprintf( 'docker exec %s sh -c "/app/docker-entrypoint.sh /usr/local/bin/docker-gen /app/nginx.tmpl /etc/nginx/conf.d/default.conf"', $proxy ) );
		self::run_or_fail( sprintf( 'docker exec %s sh -c "nginx -t && nginx -s reload"', $proxy ) );
	}

	/**
	 * Returns the id of the single running container of a site's compose service.
	 */
	private function site_container( $service, $site ) {
		$run = EE::launch( sprintf( 'docker ps -q --filter %s --filter %s', escapeshellarg( 'label=io.easyengine.site=' . $site ), escapeshellarg( 'label=com.docker.compose.service=' . $service ) ), false, true );
		$ids = array_values( array_filter( explode( "\n", trim( $run->stdout ) ) ) );
		if ( 1 !== count( $ids ) ) {
			$all = EE::launch( sprintf( 'docker ps -a --filter %s --format "{{.Names}} {{.Status}}"', escapeshellarg( 'label=io.easyengine.site=' . $site ) ), false, true );
			throw new Exception( sprintf( "Expected exactly one running '%s' container for %s, found %d.\nContainers of the site:\n%s", $service, $site, count( $ids ), $all->stdout ) );
		}

		return $ids[0];
	}

	private function container_inspect( $service, $site ) {
		$id  = $this->site_container( $service, $site );
		$run = EE::launch( 'docker inspect ' . escapeshellarg( $id ), false, true );
		$data = json_decode( $run->stdout, true );
		if ( empty( $data[0] ) ) {
			throw new Exception( "docker inspect failed.\n" . self::describe( $run ) );
		}

		return $data[0];
	}

	private function container_mount( $service, $site, $destination ) {
		foreach ( $this->container_inspect( $service, $site )['Mounts'] as $mount ) {
			if ( rtrim( $mount['Destination'], '/' ) === rtrim( $destination, '/' ) ) {
				return $mount;
			}
		}

		return null;
	}

	private function container_mount_list( $service, $site ) {
		return implode( "\n", array_map( function ( $m ) {
			return $m['Source'] . ' -> ' . $m['Destination'] . ( $m['RW'] ? ' (rw)' : ' (ro)' );
		}, $this->container_inspect( $service, $site )['Mounts'] ) );
	}

	private function assert_valid_column( $column ) {
		if ( ! preg_match( '/^[a-z_]+$/', $column ) ) {
			throw new Exception( "Invalid column name: $column" );
		}
	}

	/**
	 * @When /^I run '([^']*)' in the directory '([^']*)'$/
	 */
	public function iRunInTheDirectory( $command, $dir ) {
		// A leading `bin/ee` is relative to the core root, which is not the cwd here.
		$command = preg_replace( '#^bin/ee\b#', escapeshellarg( $this->ee_path . '/bin/ee' ), $command );
		$this->commands[] = EE::launch( 'cd ' . escapeshellarg( $dir ) . ' && ' . $command, false, true );
	}

	/**
	 * @Then the last command should succeed
	 */
	public function theLastCommandShouldSucceed() {
		$run = $this->last_command();
		if ( 0 !== $run->return_code ) {
			throw new Exception( "Expected exit code 0.\n" . self::describe( $run ) );
		}
	}

	/**
	 * @Then the last command should fail
	 */
	public function theLastCommandShouldFail() {
		$run = $this->last_command();
		if ( 0 === $run->return_code ) {
			throw new Exception( "Expected a non-zero exit code.\n" . self::describe( $run ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) of the last command should contain:$/
	 */
	public function outputOfTheLastCommandShouldContain( $stream, PyStringNode $expected ) {
		$run    = $this->last_command();
		$output = self::clean_output( 'STDOUT' === $stream ? $run->stdout : $run->stderr );
		if ( false === strpos( $output, $expected->getRaw() ) ) {
			throw new Exception( "$stream does not contain:\n" . $expected->getRaw() . "\n\n" . self::describe( $run ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) of the last command should not contain:$/
	 */
	public function outputOfTheLastCommandShouldNotContain( $stream, PyStringNode $unexpected ) {
		$run    = $this->last_command();
		$output = self::clean_output( 'STDOUT' === $stream ? $run->stdout : $run->stderr );
		if ( false !== strpos( $output, $unexpected->getRaw() ) ) {
			throw new Exception( "$stream unexpectedly contains:\n" . $unexpected->getRaw() . "\n\n" . self::describe( $run ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) of the last command should be empty$/
	 */
	public function outputOfTheLastCommandShouldBeEmpty( $stream ) {
		$run = $this->last_command();
		if ( '' !== trim( self::clean_output( 'STDOUT' === $stream ? $run->stdout : $run->stderr ) ) ) {
			throw new Exception( "$stream is not empty.\n" . self::describe( $run ) );
		}
	}

	/**
	 * @Then STDOUT of the last command should be valid json
	 */
	public function stdoutOfTheLastCommandShouldBeValidJson() {
		$run = $this->last_command();
		json_decode( $run->stdout );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new Exception( 'STDOUT is not valid JSON: ' . json_last_error_msg() . "\n" . self::describe( $run ) );
		}
	}

	/**
	 * @Then STDOUT of the last command should be a JSON list with an entry where :key is :value
	 */
	public function stdoutShouldBeAJsonListWithAnEntry( $key, $value ) {
		$run  = $this->last_command();
		$list = json_decode( $run->stdout, true );
		if ( is_array( $list ) ) {
			foreach ( $list as $entry ) {
				if ( is_array( $entry ) && isset( $entry[ $key ] ) && (string) $entry[ $key ] === $value ) {
					return;
				}
			}
		}
		throw new Exception( "No JSON entry with $key = $value.\n" . self::describe( $run ) );
	}

	/**
	 * Creates the site only when it does not exist yet, so scenarios can share it within a feature.
	 *
	 * @Given the site :site exists, created with :flags
	 */
	public function theSiteExistsCreatedWith( $site, $flags ) {
		if ( 0 !== strpos( $site, self::SITE_PREFIX ) ) {
			throw new Exception( sprintf( 'Test sites must start with %s so the cleanup can find them: %s', self::SITE_PREFIX, $site ) );
		}
		if ( self::site_row( $site ) ) {
			return;
		}
		self::run_or_fail( sprintf( 'bin/ee site create %s %s', escapeshellarg( $site ), $flags ) );
		if ( ! self::site_row( $site ) ) {
			throw new Exception( "Site $site was not found in the database after creation." );
		}
	}

	/**
	 * @Given the site :site is disabled
	 */
	public function theSiteIsDisabled( $site ) {
		$row = self::site_row( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}
		if ( (int) $row['site_enabled'] ) {
			self::run_or_fail( sprintf( 'bin/ee site disable %s', escapeshellarg( $site ) ) );
		}
	}

	/**
	 * @Given the site :site is enabled
	 */
	public function theSiteIsEnabled( $site ) {
		$row = self::site_row( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}
		if ( ! (int) $row['site_enabled'] ) {
			self::run_or_fail( sprintf( 'bin/ee site enable %s', escapeshellarg( $site ) ) );
		}
	}

	/**
	 * @Then the site :site should exist
	 */
	public function theSiteShouldExist( $site ) {
		if ( ! self::site_row( $site ) ) {
			throw new Exception( "Site $site does not exist in the database." );
		}
	}

	/**
	 * @Then the site :site should not exist
	 */
	public function theSiteShouldNotExist( $site ) {
		if ( self::site_row( $site ) ) {
			throw new Exception( "Site $site still exists in the database." );
		}
	}

	/**
	 * @Then the site :site should have :column set to :value in the database
	 */
	public function theSiteShouldHaveColumnSetTo( $site, $column, $value ) {
		$this->assert_valid_column( $column );
		$row = self::site_row( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist in the database." );
		}
		if ( ! array_key_exists( $column, $row ) ) {
			throw new Exception( "Column $column does not exist in the sites table." );
		}
		if ( (string) $row[ $column ] !== (string) $value ) {
			throw new Exception( sprintf( "Expected %s.%s to be '%s', got '%s'.", $site, $column, $value, $row[ $column ] ) );
		}
	}

	/**
	 * @Then the http auth for :scope should exist in the database
	 */
	public function theHttpAuthForShouldExistInTheDatabase( $scope ) {
		if ( empty( self::db_rows( 'SELECT id FROM auth_users WHERE site_url = ?', [ $scope ] ) ) ) {
			$all = self::db_rows( 'SELECT site_url, username FROM auth_users' );
			throw new Exception( "No auth_users row for $scope. Rows: " . json_encode( $all ) );
		}
	}

	/**
	 * @Then the http auth for :scope should not exist in the database
	 */
	public function theHttpAuthForShouldNotExistInTheDatabase( $scope ) {
		$rows = self::db_rows( 'SELECT site_url, username FROM auth_users WHERE site_url = ?', [ $scope ] );
		if ( ! empty( $rows ) ) {
			throw new Exception( "Unexpected auth_users rows for $scope: " . json_encode( $rows ) );
		}
	}

	/**
	 * @Then the http auth for :scope should have the user :user in the database
	 */
	public function theHttpAuthForShouldHaveTheUser( $scope, $user ) {
		if ( empty( self::db_rows( 'SELECT id FROM auth_users WHERE site_url = ? AND username = ?', [ $scope, $user ] ) ) ) {
			$all = self::db_rows( 'SELECT site_url, username FROM auth_users' );
			throw new Exception( "No auth_users row for $scope with user $user. Rows: " . json_encode( $all ) );
		}
	}

	/**
	 * Either auth that guards /ee-admin/: `default_admin_tools`, or a global `default` one (which suppresses it).
	 *
	 * @Then the global admin-tools auth should exist
	 */
	public function theGlobalAdminToolsAuthShouldExist() {
		$rows = self::db_rows( "SELECT site_url FROM auth_users WHERE site_url IN ('default', 'default_admin_tools')" );
		if ( empty( $rows ) ) {
			throw new Exception( 'Neither a default_admin_tools nor a default auth exists in auth_users.' );
		}
		$dir = EE_ROOT_DIR . '/services/nginx-proxy/htpasswd';
		if ( ! is_file( "$dir/default_admin_tools" ) && ! is_file( "$dir/default" ) ) {
			throw new Exception( "Neither $dir/default_admin_tools nor $dir/default exists." );
		}
	}

	/**
	 * @Then the whitelist for :scope should contain :ip in the database
	 */
	public function theWhitelistForShouldContain( $scope, $ip ) {
		$ip = self::expand( $ip );
		if ( empty( self::db_rows( 'SELECT id FROM auth_ips WHERE site_url = ? AND ip = ?', [ $scope, $ip ] ) ) ) {
			$all = self::db_rows( 'SELECT site_url, ip FROM auth_ips' );
			throw new Exception( "IP $ip is not whitelisted for $scope. Rows: " . json_encode( $all ) );
		}
	}

	/**
	 * @Then the file :path should exist
	 */
	public function theFileShouldExist( $path ) {
		if ( ! is_file( $path ) ) {
			throw new Exception( "File $path does not exist." );
		}
	}

	/**
	 * @Then the file :path should not exist
	 */
	public function theFileShouldNotExist( $path ) {
		if ( file_exists( $path ) ) {
			throw new Exception( "File $path exists." );
		}
	}

	/**
	 * @Then the directory :path should exist
	 */
	public function theDirectoryShouldExist( $path ) {
		if ( ! is_dir( $path ) ) {
			throw new Exception( "Directory $path does not exist." );
		}
	}

	/**
	 * @Then the file :path should contain :text
	 */
	public function theFileShouldContain( $path, $text ) {
		$text = self::expand( $text );
		$this->theFileShouldExist( $path );
		$content = file_get_contents( $path );
		if ( false === strpos( $content, $text ) ) {
			throw new Exception( "File $path does not contain: $text\nContent:\n" . substr( $content, 0, 4000 ) );
		}
	}

	/**
	 * @Then the file :path should not contain :text
	 */
	public function theFileShouldNotContain( $path, $text ) {
		$text = self::expand( $text );
		$this->theFileShouldExist( $path );
		if ( false !== strpos( file_get_contents( $path ), $text ) ) {
			throw new Exception( "File $path unexpectedly contains: $text" );
		}
	}

	/**
	 * @Then the file :path should match :regex
	 */
	public function theFileShouldMatch( $path, $regex ) {
		$this->theFileShouldExist( $path );
		$content = file_get_contents( $path );
		if ( ! preg_match( $regex, $content ) ) {
			throw new Exception( "File $path does not match $regex\nContent:\n" . substr( $content, 0, 4000 ) );
		}
	}

	/**
	 * @Then the :service container of site :site should be running
	 */
	public function theContainerOfSiteShouldBeRunning( $service, $site ) {
		$this->site_container( $service, $site );
	}

	/**
	 * @Then the :service container of site :site should have a read-only mount at :destination
	 */
	public function theContainerShouldHaveAReadOnlyMountAt( $service, $site, $destination ) {
		$mount = $this->container_mount( $service, $site, $destination );
		if ( ! $mount ) {
			throw new Exception( "No mount at $destination in the $service container of $site. Mounts:\n" . $this->container_mount_list( $service, $site ) );
		}
		if ( $mount['RW'] ) {
			throw new Exception( "The mount at $destination in the $service container of $site is writable." );
		}
	}

	/**
	 * @Then the :service container of site :site should have a mount from :source at :destination
	 */
	public function theContainerShouldHaveAMountFromAt( $service, $site, $source, $destination ) {
		$mount = $this->container_mount( $service, $site, $destination );
		if ( ! $mount || rtrim( $mount['Source'], '/' ) !== rtrim( $source, '/' ) ) {
			throw new Exception( "No mount from $source at $destination in the $service container of $site. Mounts:\n" . $this->container_mount_list( $service, $site ) );
		}
	}

	/**
	 * @Then the :service container of site :site should not have a mount at :destination
	 */
	public function theContainerShouldNotHaveAMountAt( $service, $site, $destination ) {
		if ( $this->container_mount( $service, $site, $destination ) ) {
			throw new Exception( "Unexpected mount at $destination in the $service container of $site. Mounts:\n" . $this->container_mount_list( $service, $site ) );
		}
	}

	/**
	 * @Then the :service container of site :site should have the environment variable :name set to :value
	 */
	public function theContainerShouldHaveTheEnvironmentVariable( $service, $site, $name, $value ) {
		$env = $this->container_inspect( $service, $site )['Config']['Env'];
		if ( ! in_array( $name . '=' . $value, $env, true ) ) {
			throw new Exception( "$name=$value not set in the $service container of $site. Env:\n" . implode( "\n", $env ) );
		}
	}

	/**
	 * @Then the :service container of site :site should have the label :label containing :text
	 */
	public function theContainerShouldHaveTheLabelContaining( $service, $site, $label, $text ) {
		$labels = $this->container_inspect( $service, $site )['Config']['Labels'];
		if ( ! isset( $labels[ $label ] ) || false === strpos( $labels[ $label ], $text ) ) {
			throw new Exception( "Label $label of the $service container of $site does not contain $text. Value: " . ( isset( $labels[ $label ] ) ? $labels[ $label ] : '(missing)' ) );
		}
	}

	/**
	 * @Given I send requests via the public IP
	 */
	public function iSendRequestsViaThePublicIp() {
		$this->request_ip = self::public_ip();
	}

	/**
	 * Requests from 127.0.0.1 reach the proxy from the frontend network's gateway, which the global whitelist lets through.
	 *
	 * @Given I send requests via 127.0.0.1
	 */
	public function iSendRequestsViaLocalhost() {
		$this->request_ip = '127.0.0.1';
	}

	/**
	 * @Given I use basic auth with user :user and password :pass
	 */
	public function iUseBasicAuthWithUserAndPassword( $user, $pass ) {
		$this->request_auth = [ $user, $pass ];
	}

	/**
	 * Admin-tools auth when present, else the first global auth, as the proxy prefers them.
	 *
	 * @Given I use the global admin-tools credentials
	 */
	public function iUseTheGlobalAdminToolsCredentials() {
		foreach ( [ 'default_admin_tools', 'default' ] as $scope ) {
			$rows = self::db_rows( 'SELECT username, password FROM auth_users WHERE site_url = ? ORDER BY id', [ $scope ] );
			if ( ! empty( $rows ) ) {
				$this->request_auth = [ $rows[0]['username'], $rows[0]['password'] ];

				return;
			}
		}
		throw new Exception( 'Neither a default_admin_tools nor a default auth exists in auth_users.' );
	}

	/**
	 * @Given I use no basic auth
	 */
	public function iUseNoBasicAuth() {
		$this->request_auth = null;
	}

	private function http_request( $url, $host_header = null ) {
		$parts = parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			throw new Exception( "Invalid URL: $url" );
		}
		$ip = $this->request_ip ?: self::public_ip();
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RESOLVE, [ $parts['host'] . ':80:' . $ip, $parts['host'] . ':443:' . $ip ] );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
		if ( null !== $host_header ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Host: ' . $host_header ] );
		}
		if ( $this->request_auth ) {
			curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
			curl_setopt( $ch, CURLOPT_USERPWD, $this->request_auth[0] . ':' . $this->request_auth[1] );
		}
		$raw         = curl_exec( $ch );
		$error       = curl_error( $ch );
		$code        = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		curl_close( $ch );

		$this->response = [
			'url'     => null === $host_header ? $url : "$url (Host: $host_header)",
			'via'     => $ip,
			'code'    => $code,
			'error'   => $error,
			'headers' => false === $raw ? '' : substr( $raw, 0, $header_size ),
			'body'    => false === $raw ? '' : (string) substr( $raw, $header_size ),
		];
	}

	private function describe_response() {
		$r = $this->response;
		if ( ! $r ) {
			return 'No request has been made in this scenario.';
		}

		return sprintf( "GET %s via %s -> %d%s\nHeaders:\n%s\nBody (first 2000 bytes):\n%s", $r['url'], $r['via'], $r['code'], $r['error'] ? " (curl error: {$r['error']})" : '', trim( $r['headers'] ), substr( $r['body'], 0, 2000 ) );
	}

	/**
	 * @When I request :url
	 */
	public function iRequest( $url ) {
		$this->http_request( $url );
	}

	/**
	 * Connects to the URL's host but sends another Host header, e.g. one that curl rejects inside a URL.
	 *
	 * @When I request :url with the Host header :host
	 */
	public function iRequestWithTheHostHeader( $url, $host ) {
		$this->http_request( $url, $host );
	}

	/**
	 * Polls, since the proxy picks up recreated containers asynchronously.
	 *
	 * @When I request :url until it returns :code within :seconds seconds
	 */
	public function iRequestUntilItReturnsWithinSeconds( $url, $code, $seconds ) {
		$deadline = time() + (int) $seconds;
		do {
			$this->http_request( $url );
			// A truncated transfer keeps its status code; retry it like a transport error.
			if ( (int) $code === $this->response['code'] && '' === $this->response['error'] ) {
				return;
			}
			sleep( 2 );
		} while ( time() < $deadline );
		throw new Exception( "Did not get HTTP $code within $seconds seconds.\n" . $this->describe_response() );
	}

	/**
	 * Polls until the response carries the header text, e.g. the admin-tools realm, which the proxy only serves
	 * once it has regenerated the /ee-admin/ location; until then the site's own auth answers with the same code.
	 *
	 * @When I request :url until it returns :code with a :name header containing :text within :seconds seconds
	 */
	public function iRequestUntilItReturnsWithHeaderWithinSeconds( $url, $code, $name, $text, $seconds ) {
		$deadline = time() + (int) $seconds;
		do {
			$this->http_request( $url );
			if ( (int) $code === $this->response['code'] && $this->response_header_contains( $name, $text ) ) {
				return;
			}
			sleep( 2 );
		} while ( time() < $deadline );
		throw new Exception( "Did not get HTTP $code with a $name header containing '$text' within $seconds seconds.\n" . $this->describe_response() );
	}

	private function response_header_contains( $name, $text ) {
		if ( $this->response ) {
			foreach ( explode( "\n", $this->response['headers'] ) as $line ) {
				$pair = explode( ':', $line, 2 );
				if ( 2 === count( $pair ) && 0 === strcasecmp( trim( $pair[0] ), $name ) && false !== strpos( $pair[1], $text ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @Then the response code should be :code
	 */
	public function theResponseCodeShouldBe( $code ) {
		if ( ! $this->response || (int) $code !== $this->response['code'] ) {
			throw new Exception( "Expected HTTP $code.\n" . $this->describe_response() );
		}
	}

	/**
	 * @Then the response code should not be :code
	 */
	public function theResponseCodeShouldNotBe( $code ) {
		// A transport failure (code 0) is not "some other code".
		if ( ! $this->response || 0 === $this->response['code'] || '' !== $this->response['error'] || (int) $code === $this->response['code'] ) {
			throw new Exception( "Expected any HTTP code but $code.\n" . $this->describe_response() );
		}
	}

	/**
	 * @Then the response body should contain :text
	 */
	public function theResponseBodyShouldContain( $text ) {
		if ( ! $this->response || false === strpos( $this->response['body'], $text ) ) {
			throw new Exception( "Response body does not contain: $text\n" . $this->describe_response() );
		}
	}

	/**
	 * @Then the response body should not contain :text
	 */
	public function theResponseBodyShouldNotContain( $text ) {
		// An empty body from a failed request proves nothing.
		if ( ! $this->response || 0 === $this->response['code'] || '' !== $this->response['error'] || false !== strpos( $this->response['body'], $text ) ) {
			throw new Exception( "Response body unexpectedly contains: $text\n" . $this->describe_response() );
		}
	}

	/**
	 * @Then the response body should match :regex
	 */
	public function theResponseBodyShouldMatch( $regex ) {
		if ( ! $this->response || ! preg_match( $regex, $this->response['body'] ) ) {
			throw new Exception( "Response body does not match: $regex\n" . $this->describe_response() );
		}
	}

	/**
	 * @When I remember the response body
	 */
	public function iRememberTheResponseBody() {
		// An empty body from a failed transfer would make any later body "differ".
		if ( ! $this->response || 0 === $this->response['code'] || '' !== $this->response['error'] ) {
			throw new Exception( "No complete HTTP response to remember.\n" . $this->describe_response() );
		}
		$this->remembered_body = $this->response['body'];
	}

	/**
	 * @Then the response body should differ from the remembered one
	 */
	public function theResponseBodyShouldDifferFromTheRememberedOne() {
		if ( null === $this->remembered_body ) {
			throw new Exception( 'No response body has been remembered in this scenario.' );
		}
		if ( ! $this->response || 0 === $this->response['code'] || '' !== $this->response['error'] ) {
			throw new Exception( "No complete HTTP response to compare.\n" . $this->describe_response() );
		}
		if ( $this->response['body'] === $this->remembered_body ) {
			throw new Exception( "Response body is identical to the remembered one (served from a cache?).\n" . $this->describe_response() );
		}
	}

	/**
	 * @Then the response header :name should contain :text
	 */
	public function theResponseHeaderShouldContain( $name, $text ) {
		if ( ! $this->response_header_contains( $name, $text ) ) {
			throw new Exception( "No $name header containing: $text\n" . $this->describe_response() );
		}
	}

	/**
	 * Passes when the header is absent too.
	 *
	 * @Then the response header :name should not contain :text
	 */
	public function theResponseHeaderShouldNotContain( $name, $text ) {
		// Headers of a failed or truncated transfer prove nothing.
		if ( ! $this->response || 0 === $this->response['code'] || '' !== $this->response['error'] ) {
			throw new Exception( "No complete HTTP response to check.\n" . $this->describe_response() );
		}
		foreach ( explode( "\n", $this->response['headers'] ) as $line ) {
			$pair = explode( ':', $line, 2 );
			if ( 2 === count( $pair ) && 0 === strcasecmp( trim( $pair[0] ), $name ) && false !== strpos( $pair[1], $text ) ) {
				throw new Exception( "$name header unexpectedly contains: $text\n" . $this->describe_response() );
			}
		}
	}

	/**
	 * Moves a file or directory away; it is put back after the scenario.
	 *
	 * @Given the path :path is moved aside
	 */
	public function thePathIsMovedAside( $path ) {
		if ( ! file_exists( $path ) ) {
			throw new Exception( "Cannot move $path aside: it does not exist." );
		}
		// A fixed name, so a backup left by a killed run is found instead of being buried under a new one.
		$backup = $path . self::ASIDE_SUFFIX;
		if ( file_exists( $backup ) ) {
			throw new Exception( "$backup already exists (left by an interrupted run?). Restore it to $path by hand first." );
		}
		if ( ! rename( $path, $backup ) ) {
			throw new Exception( "Could not move $path to $backup." );
		}
		$this->moved_paths[ $path ] = $backup;
	}

	/**
	 * Overwrites a file inside the core checkout under test; the original is moved aside and put back after the scenario.
	 *
	 * @Given the file :path in the ee checkout contains :content
	 */
	public function theFileInTheEeCheckoutContains( $path, $content ) {
		$full = $this->ee_path . '/' . ltrim( $path, '/' );
		if ( ! is_file( $full ) ) {
			throw new Exception( "File $full does not exist." );
		}
		if ( ! isset( $this->moved_paths[ $full ] ) ) {
			$this->thePathIsMovedAside( $full );
		}
		if ( false === file_put_contents( $full, $content ) ) {
			throw new Exception( "Could not write $full." );
		}
	}

	private static function auth_files() {
		$files = [];
		foreach ( [ 'htpasswd/*', 'vhost.d/*_acl' ] as $glob ) {
			foreach ( glob( EE_ROOT_DIR . '/services/nginx-proxy/' . $glob ) ?: [] as $file ) {
				if ( is_file( $file ) ) {
					$files[ $file ] = [ base64_encode( file_get_contents( $file ) ), fileperms( $file ) & 0777 ];
				}
			}
		}

		return $files;
	}

	/**
	 * Snapshots global auth rows, the global whitelist and all htpasswd/acl files (site files embed the global users); restored after the scenario.
	 *
	 * @Given I snapshot the global http auth state
	 */
	public function iSnapshotTheGlobalHttpAuthState() {
		if ( null !== $this->auth_snapshot ) {
			return;
		}
		// A snapshot left by a killed run holds the real original state: put it back before taking a new one.
		$errors = self::restore_stale_auth_snapshot();
		if ( $errors ) {
			throw new Exception( implode( "\n", $errors ) );
		}
		$snapshot = [
			'users' => self::db_rows( "SELECT * FROM auth_users WHERE site_url IN ('default', 'default_admin_tools')" ),
			'ips'   => self::db_rows( "SELECT * FROM auth_ips WHERE site_url = 'default'" ),
			'files' => self::auth_files(),
		];
		$json = json_encode( $snapshot );
		// Holds plain-text auth passwords: create it private and never write through a planted symlink.
		$umask = umask( 0077 );
		$saved = false !== $json && ! is_link( self::AUTH_SNAPSHOT_FILE ) && false !== @file_put_contents( self::AUTH_SNAPSHOT_FILE, $json ) && @chmod( self::AUTH_SNAPSHOT_FILE, 0600 );
		umask( $umask );
		if ( ! $saved ) {
			throw new Exception( 'Could not save the global http auth snapshot to ' . self::AUTH_SNAPSHOT_FILE . '; refusing to change the global auth.' );
		}
		$this->auth_snapshot = $snapshot;
	}

	/**
	 * @BeforeSuite
	 */
	public static function restoreStaleAuthSnapshot( BeforeSuiteScope $scope ) {
		$errors = self::restore_stale_auth_snapshot();
		if ( $errors ) {
			throw new Exception( implode( "\n", $errors ) );
		}
	}

	private static function restore_stale_auth_snapshot() {
		if ( ! is_file( self::AUTH_SNAPSHOT_FILE ) && ! is_link( self::AUTH_SNAPSHOT_FILE ) ) {
			return [];
		}
		// /var/tmp is shared: only trust a snapshot this user wrote, and only for the proxy's auth files.
		$uid = function_exists( 'posix_geteuid' ) ? posix_geteuid() : 0;
		clearstatcache();
		if ( is_link( self::AUTH_SNAPSHOT_FILE ) || fileowner( self::AUTH_SNAPSHOT_FILE ) !== $uid || ( fileperms( self::AUTH_SNAPSHOT_FILE ) & 0077 ) ) {
			return [ 'Untrusted global http auth snapshot ' . self::AUTH_SNAPSHOT_FILE . ' (symlink, foreign owner or not 0600); check and delete it by hand.' ];
		}
		$snapshot = json_decode( (string) file_get_contents( self::AUTH_SNAPSHOT_FILE ), true );
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['users'], $snapshot['ips'], $snapshot['files'] ) || ! is_array( $snapshot['files'] ) ) {
			return [ 'Unreadable global http auth snapshot ' . self::AUTH_SNAPSHOT_FILE . ' left by an interrupted run; restore or delete it by hand.' ];
		}
		$allowed = '#^' . preg_quote( EE_ROOT_DIR . '/services/nginx-proxy/', '#' ) . '(htpasswd/[^/]+|vhost\.d/[^/]+_acl)$#';
		foreach ( array_keys( $snapshot['files'] ) as $file ) {
			if ( ! preg_match( $allowed, $file ) ) {
				return [ 'Global http auth snapshot ' . self::AUTH_SNAPSHOT_FILE . " names an unexpected file $file; check and delete it by hand." ];
			}
		}
		fwrite( STDERR, 'Restoring the global http auth state left by an interrupted run from ' . self::AUTH_SNAPSHOT_FILE . "\n" );

		return self::restore_auth_snapshot( $snapshot );
	}

	/**
	 * Restores the DB rows, the files and the proxy independently, so one failure does not skip the rest.
	 * The snapshot file is deleted only once everything succeeded.
	 *
	 * @return string[] Errors.
	 */
	private static function restore_auth_snapshot( array $snapshot ) {
		$errors = [];

		try {
			$pdo = self::db();
			$pdo->beginTransaction();
			try {
				$pdo->exec( "DELETE FROM auth_users WHERE site_url IN ('default', 'default_admin_tools')" );
				$pdo->exec( "DELETE FROM auth_ips WHERE site_url = 'default'" );
				foreach ( [ 'auth_users' => $snapshot['users'], 'auth_ips' => $snapshot['ips'] ] as $table => $rows ) {
					foreach ( $rows as $row ) {
						self::insert_row( $pdo, $table, $row );
					}
				}
				$pdo->commit();
			} catch ( Throwable $e ) {
				$pdo->rollBack();
				throw $e;
			}
		} catch ( Throwable $e ) {
			$errors[] = 'Could not restore the global auth DB rows: ' . $e->getMessage();
		}

		$base = EE_ROOT_DIR . '/services/nginx-proxy/';
		foreach ( [ 'htpasswd/default', 'htpasswd/default_admin_tools', 'vhost.d/default_acl' ] as $global_file ) {
			if ( ! isset( $snapshot['files'][ $base . $global_file ] ) && file_exists( $base . $global_file ) && ! @unlink( $base . $global_file ) ) {
				$errors[] = "Could not remove $base$global_file.";
			}
		}
		foreach ( $snapshot['files'] as $file => $data ) {
			if ( false === @file_put_contents( $file, base64_decode( $data[0] ) ) || ! @chmod( $file, $data[1] ) ) {
				$errors[] = "Could not restore $file.";
			}
		}

		try {
			self::reload_global_proxy();
		} catch ( Throwable $e ) {
			$errors[] = 'Could not reload the proxy: ' . $e->getMessage();
		}

		if ( ! $errors && is_file( self::AUTH_SNAPSHOT_FILE ) && ! @unlink( self::AUTH_SNAPSHOT_FILE ) ) {
			$errors[] = 'Could not delete ' . self::AUTH_SNAPSHOT_FILE . '.';
		}

		return $errors;
	}

	private static function insert_row( PDO $pdo, $table, array $row ) {
		$insert = function ( array $row ) use ( $pdo, $table ) {
			$cols = array_keys( $row );
			$pdo->prepare( sprintf( 'INSERT INTO %s (%s) VALUES (%s)', $table, implode( ',', $cols ), implode( ',', array_fill( 0, count( $cols ), '?' ) ) ) )->execute( array_values( $row ) );
		};
		try {
			$insert( $row );
		} catch ( PDOException $e ) {
			// A row created during the scenario reused the id; keep the data under a new id.
			unset( $row['id'] );
			$insert( $row );
		}
	}

	private function restore_global_http_auth_state() {
		if ( null === $this->auth_snapshot ) {
			return [];
		}
		$errors              = self::restore_auth_snapshot( $this->auth_snapshot );
		$this->auth_snapshot = null;

		return $errors;
	}

	/**
	 * @Given no global http auth or whitelist exists
	 */
	public function noGlobalHttpAuthOrWhitelistExists() {
		$this->iSnapshotTheGlobalHttpAuthState();
		$pdo = self::db();
		$pdo->exec( "DELETE FROM auth_users WHERE site_url IN ('default', 'default_admin_tools')" );
		$pdo->exec( "DELETE FROM auth_ips WHERE site_url = 'default'" );
		foreach ( [ 'htpasswd/default', 'htpasswd/default_admin_tools', 'vhost.d/default_acl' ] as $file ) {
			@unlink( EE_ROOT_DIR . '/services/nginx-proxy/' . $file );
		}
		self::reload_global_proxy();
	}

	/* ===================================================================================
	 * admin-tools-command specific steps.
	 * =================================================================================== */

	const EE_ADMIN_PATH = '/var/www/htdocs/ee-admin';

	/**
	 * Whether nginx and php have the ee-admin mount; null for a container that is not running.
	 */
	private function admin_mounts( $site ) {
		$state = [];
		foreach ( [ 'nginx', 'php' ] as $service ) {
			try {
				$state[] = (bool) $this->container_mount( $service, $site, self::EE_ADMIN_PATH );
			} catch ( Exception $e ) {
				$state[] = null;
			}
		}

		return $state;
	}

	/**
	 * Converges on enabled; `--force` also repairs a site whose flag is set but whose containers lost the mount.
	 *
	 * @Given admin-tools are enabled on :site
	 */
	public function adminToolsAreEnabledOn( $site ) {
		$row = self::site_row( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}
		$this->theSiteIsEnabled( $site );
		if ( (int) $row['admin_tools'] && $this->admin_mounts( $site ) === [ true, true ] ) {
			return;
		}
		self::run_or_fail( sprintf( 'bin/ee admin-tools enable %s --force', escapeshellarg( $site ) ) );
	}

	/**
	 * @Given admin-tools are disabled on :site
	 */
	public function adminToolsAreDisabledOn( $site ) {
		$row = self::site_row( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}
		$this->theSiteIsEnabled( $site );
		if ( ! (int) $row['admin_tools'] && $this->admin_mounts( $site ) === [ false, false ] ) {
			return;
		}
		self::run_or_fail( sprintf( 'bin/ee admin-tools disable %s --force', escapeshellarg( $site ) ) );
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupScenario(AfterScenarioScope $scope)
	{
		// Every restore runs even if an earlier one fails (Behat turns warnings in hooks into exceptions).
		try {
			$errors = $this->restore_global_http_auth_state();
		} catch ( Throwable $e ) {
			$errors = [ 'Could not restore the global http auth state: ' . $e->getMessage() ];
		}

		foreach ( array_reverse( $this->moved_paths, true ) as $path => $backup ) {
			try {
				if ( ! file_exists( $backup ) ) {
					$errors[] = "Backup $backup of $path is missing; left $path untouched.";
					continue;
				}
				if ( file_exists( $path ) ) {
					exec( 'rm -rf ' . escapeshellarg( $path ), $out, $code );
				}
				if ( ! rename( $backup, $path ) ) {
					$errors[] = "Could not restore $path from $backup.";
				}
			} catch ( Throwable $e ) {
				$errors[] = "Could not restore $path from $backup: " . $e->getMessage();
			}
		}
		$this->moved_paths = [];

		$this->commands        = [];
		$this->response        = null;
		$this->request_ip      = null;
		$this->request_auth    = null;
		$this->remembered_body = null;
		chdir($this->ee_path);

		if ( $errors ) {
			throw new Exception( implode( "\n", $errors ) );
		}
	}

	/**
	 * Deletes only this suite's sites (upstream deleted every site on the server).
	 *
	 * @AfterFeature
	 */
	public static function cleanup(AfterFeatureScope $scope)
	{
		$result = EE::launch( 'bin/ee site list --format=text', false, true );
		// An empty install exits 1 with "No sites found!"; any other failure must not look like an empty list.
		$failures = [];
		if ( 0 !== $result->return_code && false === strpos( $result->stderr, 'No sites found!' ) ) {
			$failures[] = "Unable to list sites:\n" . $result->stderr;
		} else {
			foreach ( array_filter( array_map( 'trim', explode( "\n", $result->stdout ) ) ) as $site ) {
				if ( 0 === strpos( $site, self::SITE_PREFIX ) ) {
					$delete = EE::launch( 'bin/ee site delete ' . escapeshellarg( $site ) . ' --yes', false, true );
					if ( 0 !== $delete->return_code ) {
						$failures[] = "Unable to delete $site:\n" . $delete->stderr;
					}
				}
			}
		}

		if ( self::$phar_built ) {
			foreach ( [ 'ee.phar', 'ee-old.phar' ] as $phar ) {
				if ( file_exists( $phar ) ) {
					unlink( $phar );
				}
			}
			self::$phar_built = false;
		}

		if ( $failures ) {
			throw new Exception( implode( "\n", $failures ) );
		}
	}
}
