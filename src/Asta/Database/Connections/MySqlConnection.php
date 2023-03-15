<?php
namespace Asta\Database\Connections;

use PDO;
use Asta\Database\Query\Processors\Processor;
use Asta\Database\Query\Grammars\MySqlGrammar;


/**
 *	Encapsulates the connection features and tasks
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2021-07-xx
 */
class MySqlConnection implements ConnectionInterface
{
	/**
	 *	@var array
	 */
	protected static $connectionPool = [];

	/**
	 *	@var array
	 */
	protected $options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8';",
	];

	/**
	 *	Assigns the proper grammar and processor.
	 *
	 *	@return	void
	 */
	protected function initialize()
	{
		$this->grammar = new MySqlGrammar();
		$this->processor = new Processor();
	}

}

