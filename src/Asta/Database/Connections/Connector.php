<?php
namespace Asta\Database\Connections;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\MySqliConnection;
use Asta\Database\Connections\MsSqlServerConnection;
/**
 *	Embodies connection tasks
 *
 *	@author	alarido <alarido.su@gmail.com>
 *	@since	2021-07-xx
 */
class Connector
{
	protected const DRIVERS = [
		'MySql' => ['mysql', 'mysqli', 'mariadb'],
		'SqlServer' => ['mssql', 'sqlsrv', 'sqlsvr', 'sqlserver'],
	];

	/**
	 *	@var string
	 */
	private $driver = '';

	/**
	 *	@var string
	 */
	private $dsn = '';

	/**
	 *	@var string
	 */
	private $host = '';

	/**
	 *	@var string
	 */
	private $user = '';

	/**
	 *	@var string
	 */
	private $pass = '';

	/**
	 *	@var string
	 */
	private $charset = '';

	/**
	 *	Returns the database user
	 *
	 *	@return	string
	 */
	protected function getUser()
	{
		return $this->user;
	}

	/**
	 *	Returns the database password
	 *
	 *	@return	string
	 */
	protected function getPass()
	{
		return $this->pass;
	}

	/**
	 *	Returns the database host
	 *
	 *	@return	string
	 */
	protected function getHost()
	{
		return $this->host;
	}

	/**
	 *	Returns the database connection string
	 *
	 *	@return	string
	 */
	protected function getDSN()
	{
		return $this->dsn;
	}

	/**
	 *	Connects and returns the created connection
	 *
	 *	@return	\Asta\Database\Connections\ConnectionInterface
	 */
	public function connect()
	{
		return Connector::make(
			$this->driver,
			$this->dsn,
			$this->user,
			$this->pass, ''
		);
	}

	/**
	 *	Sets the connection driver
	 *
	 *	@param	string	$driver
	 *	@return	$this
	 */
	public function driver(string $driver)
	{
		$this->driver = $driver;
		return $this;
	}

	/**
	 *	Sets the connection string
	 *
	 *	@param	string	$dsn
	 *	@return	$this
	 */
	public function dsn(string $dsn)
	{
		$this->dsn = $dsn;
		return $this;
	}
	
	/**
	 *	Sets the database host
	 *
	 *	@param	string	$host
	 *	@return	$this
	 */
	public function host(string $host)
	{
		$this->host = $host;
		return $this;
	}
	
	/**
	 *	Sets the database user
	 *
	 *	@param	string	$user
	 *	@return	$this
	 */
	public function user(string $user)
	{
		$this->user = $user;
		return $this;
	}
	
	/**
	 *	Sets the database user password
	 *
	 *	@param	string	$pass
	 *	@return	$this
	 */
	public function pass(string $pass)
	{
		$this->pass = $pass;
		return $this;
	}
	
	/**
	 *	Sets the connection charset
	 *
	 *	@param	string	$charset
	 *	@return	$this
	 */
	public function charset(string $charset)
	{
		$this->charset = $charset;
		return $this;
	}
	
	/**
	 *	Sets database connection options
	 *
	 *	@param	string	$option
	 *	@param	mixed	$value
	 *	@return	$this
	 */
	public function option(string $option, $value)
	{
		$this->options[$option] = $value;
		return $this;
	}
	
	/**
	 *	Sets several connection options at once
	 *
	 *	@param	array	$options	an associative array of values indexed by their names
	 *	@return	$this
	 */
	public function options(array $options)
	{
		foreach ($options as $n => $v)
		{
			$this->options[$n] = $v;
		}
		return $this;
	}

	/**
	 *	Creates a Connection for the specified driver
	 *
	 *	@static
	 *	@param	string	$driver
	 *	@param	string	$dsn
	 *	@param	string	$username
	 *	@param	string	$password
	 *	@param	string	$db
	 *	@return	\Asta\Database\Connections\ConnectionInterface
	 */
	public static function make(string $driver, string $dsn, string $username, string $password, string $db = '')
	{
		$driver = strtolower($driver);
		//
		if (in_array($driver, self::DRIVERS['MySql'])) {
			return new MySqlConnection($dsn, $db, $username, $password);
		}
		//
		if (in_array($driver, self::DRIVERS['SqlServer'])) {
			return new MsSqlServerConnection($dsn, $db, $username, $password);
		}
		//
		return new Connection($dsn, $db, $username, $password);
	}
	
}


