<?php
namespace Asta\Database\Query;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;
use Closure;

class DeleteBuilder extends Builder
{
	/**
	 * @var \Asta\Database\Connectiona\ConnectionInterface
	 */
	protected $connection;

	/**
	 * @var mixed
	 */
	protected $table;

	/**
	 * @var \Asta\Database\Query\Builder
	 */
	protected $select;

	/**
	 * Creates a new DeleteBuilder instance.
	 *
	 * @param	string	$table
	 * @param	\Asta\Database\Connections\ConnectionInterface	$connection
	 * @return	void
	 */
	public function __construct(string $table, ConnectionInterface $connection)
	{
		$this->table = $table;
		$this->connection = $connection;
	}

	/**
	 * Returns the Builder's connection
	 *
	 * @return \Asta\Database\Connectiona\ConnectionInterface
	 */
	public function getConnection()
	{
		return $this->connection;
	}

	/**
	 * Returns the Builder's grammar
	 *
	 * @return \Asta\Database\Query\Grammars\Grammar
	 */
	public function getGrammar()
	{
		return $this->connection->getGrammar();
	}

	/**
	 * Tells if it has an underlying, active Select clause.
	 *
	 * @return	bool
	 */
	protected function hasSelect()
	{
		return isset($this->select);
	}

	/**
	 * Returns the underlying Select clause.
	 *
	 * @param	string|array	$columns
	 * @return	\Asta\Database\Query\Builder
	 */
	protected function getSelect($columns = ['*'])
	{
		if ($this->select) {
			return $this->select;
		}
		//
		return $this->select = (new Builder($this->getConnection()))->select(['*']);
	}

	/**
	 * Adds the from clause.
	 *
	 * @param	string	$table
	 * @return	$this
	 */
	public function from(string $table)
	{
		$this->table = $table;
		//
		return $this;
	}

	/**
	 * Adds a join clause.
	 *
	 * @param	mixed	$table
	 * @param	mixed	$first
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @param	string	$type = 'inner'
	 * @param	bool	$where = false
	 * @return	$this
	 */
	public function join(
		$table,
		$first,
		$operator = null,
		$second = null,
		$type = 'inner',
		$where = false
	) {
		$this->getSelect()->join($table, $first, $operator, $second, $type, $where);
		//
		return $this;
	}

	/**
	 * Adds a join clause.
	 *
	 * @param	\Astya\Database\Query\Builder	$query
	 * @param	string		$as
	 * @param	\Closure	$first
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @param	string	$type = 'inner'
	 * @param	bool	$where = false
	 * @return	$this
	 */
	public function joinSub(
		Builder $query,
		string $as,
		Closure $first,
		$operator = null,
		$second = null,
		$type = 'inner',
		$where = false
	) {
		$this->getSelect()->joinSub($query, $as, $first, $operator, $second, $type, $where);
		//
		return $this;
	}

	/**
	 * Adds a expression to the where clause of the current query.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @param	string	$boolean = 'and'
	 * @return	$this
	 */
	public function where(
		$column,
		$operator = null,
		$value = null,
		$boolean = 'and'
	) {
		$this->getSelect()->where($column, $operator, $value, $boolean);
		//
		return $this;
	}

	/**
	 * Compiles the builder into a SQL query with named placeholders.
	 *
	 * @return	string
	 */
	public function toSql()
	{
		if ($this->hasSelect()) {
			return $this->getGrammar()->compileDelete(
				$this->table,
				array_keys($this->data),
				$this->getSelect()->getColumns(),
				$this->getSelect()->getTable(),
				$this->getSelect()->getJoins(),
				$this->getSelect()->getDistinct()
			);
		}
		//
		return $this->getGrammar()->compileDeleteAll($this->table);
	}

	/**
	 * Execute the query and returns the results.
	 *
	 * @return	$this
	 */
	public function execute()
	{
		return $this->getConnection()->insert(
			$this->toSql(), $this->values()
		);
	}

	/**
	 * Returns an array of bound values in the current query.
	 *
	 * @return array
	 */
	public function values()
	{
		if ($this->hasSelect()) {
			return $this->getSelect()->values();
		}
		//
		return $this->bindings;
	}


}

