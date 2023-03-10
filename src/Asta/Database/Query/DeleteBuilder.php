<?php
namespace Asta\Database\Query;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;
use Closure;

class DeleteBuilder extends Builder
{
	/**
	 * Creates a new DeleteBuilder instance.
	 *
	 * @param	\Asta\Database\Connections\ConnectionInterface	$connection
	 * @param	string	$table = null
	 * @return	void
	 */
	public function __construct(ConnectionInterface $connection, string $table = null)
	{
		parent::__construct($connection);
		//
		if ($table) {
			$this->from = $table;
		}
	}

	/**
	 * Adds the from clause.
	 *
	 * @param	string	$table
	 * @param	string|null	$as
	 * @return	$this
	 */
	public function from($table, string $as = null)
	{
		$this->from = $table;
		//
		return $this;
	}

	/**
	 * Compiles the builder into a SQL query with named placeholders.
	 *
	 * @return	string
	 */
	protected function toSql()
	{
		$sql = $this->getGrammar()->compileDelete($this->table, $this->joins);
		//
		$whereChain = $this->wheresToChain($this->wheres);
		//
		$sql .= $this->getGrammar()->compileWhereChain($whereChain);
		//
		return $sql;
	}

	/**
	 * Execute the delete query.
	 *
	 * @return	int|false
	 */
	public function execute()
	{
		return $this->getConnection()->delete($this->toSql(), $this->values());
	}

}

