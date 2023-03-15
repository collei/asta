<?php
namespace Asta\Database\Connections;

use Asta\Database\Query\Builder;
use Asta\Database\Query\Processors\Processor;
use Closure;

/**
 *	Query grammar
 *
 *	@author	Collei de Laravel, Collei Inc. <collei@collei.com.br>
 *	@since	2022-10-22
 *
 */
interface ConnectionInterface
{
	/**
	 *	Returns a connection from the pool.
	 *
	 *	@return	\Asta\Database\Connections\ConnectionInterface
	 */
	public static function getFromPool();

	/**
	 *	Returns the corresponding Grammar for this Connection
	 *
	 *	@return	\Asta\Database\Query\Grammars\GrammarInterface
	 */
	public function getGrammar();

	/**
	 *	Returns the corresponding Grammar for this Connection
	 *
	 *	@return	\Asta\Database\Processors\ProcessorInterface
	 */
	public function getProcessor();

	/**
	 *	Returns a select query builder for this Connection
	 *
	 *	@param	string|null	$table
	 *	@return	\Asta\Database\Query\Builder
	 */
	public function getBuilder(string $table = null);

	/**
	 *	Returns a Insert builder for this Connection
	 *
	 *	@return	\Asta\Database\Query\InsertBuilder
	 */
	public function getInserter(string $table);

	/**
	 *	Returns a Insert builder for this Connection
	 *
	 *	@return	\Asta\Database\Query\UpdateBuilder
	 */
	public function getUpdater(string $table);

	/**
	 *	Returns a Insert builder for this Connection
	 *
	 *	@return	\Asta\Database\Query\DeleteBuilder
	 */
	public function getRemover(string $table);

	/**
	 *	Defines a Processor for this Connection
	 *
	 *	@param	\Asta\Database\Processors\ProcessorInterface	$processor
	 *	@return	void
	 */
	public function setProcessor(Processor $processor);

	/**
	 *	Retrieves the name of the connection
	 *
	 *	@return	string
	 */
	public function getName();

	/**
	 *	Opens the connection with the parameters already set by the constructor
	 *
	 *	@return	$this
	 */
	public function open();

	/**
	 *	Closes the connection
	 *
	 *	@return	$this
	 */
	public function close();

	/**
	 *	Returns a new Builder instance
	 *
	 *	@return	\Asta\Database\Query\Builder
	 */
	public function query();

	/**
	 *	Performs select query
	 *
	 *	@param	string	$query
	 *	@param	array	$data = []
	 *	@return	mixed
	 */
	public function select(string $query, array $data = []);

	/**
	 *	Performs insertion of one row
	 *
	 *	@param	string	$query
	 *	@param	array	$row
	 *	@return	mixed
	 */
	public function insert(string $query, array $row = []);

	/**
	 *	Performs insertion of one row
	 *
	 *	@param	\Asta\Database\Query\Builder	$query
	 *	@param	string	$sql
	 *	@param	array	$values
	 *	@param	string	$sequence = null
	 *	@return	mixed
	 */
	public function insertGetId(Builder $query, string $sql, array $values, $sequence = null);

	/**
	 *	Performs update
	 *
	 *	@param	string	$query
	 *	@param	array	$data
	 *	@return	mixed
	 */
	public function update(string $query, array $data);

	/**
	 *	Performs deletion
	 *
	 *	@param	string	$query
	 *	@param	array	$data
	 *	@return	mixed
	 */
	public function delete(string $query, array $data);

	/**
	 *	Returns if there is an error of such $code
	 *
	 *	@return	bool
	 */
	public function hasError($code);

	/**
	 *	Returns if there are registered errors
	 *
	 *	@return	bool
	 */
	public function hasErrors();

	/**
	 *	Returns the last registered error
	 *
	 *	@return	array|null
	 */
	public function lastError();

	/**
	 *	Returns the index of last registered error
	 *
	 *	@return	int
	 */
	public function lastErrorIndex();

	/**
	 *	Performs a transaction
	 *
	 *	@param	\Closure	$bunch
	 *	@return	mixed
	 */
	public function transact(Closure $bunch);

	/**
	 *	Returns all registered errors
	 *
	 *	@return	array
	 */
	public function getErrors();

	/**
	 *	Defines the connection database.
	 *
	 *	@param	string	$name
	 *	@return	$this
	 *	@throws	\InvalidArgumentException
	 */
	public function setDatabase(string $name);

	/**
	 *	Returns the connection database name.
	 *
	 *	@return	string
	 */
	public function getDatabase();

	/**
	 *	Switch the database the connection is working upon.
	 *
	 *	@return	string
	 */
	public function switchDatabase(string $name);

	/**
	 *	Defines the connection database.
	 *
	 *	@param	string|null	$name
	 *	@return	$this
	 *	@throws	\InvalidArgumentException
	 */
	public function useDatabase(string $name = null);
}


