<?php
namespace Asta\Database\Query;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;
use Closure;

class InsertBuilder extends Builder
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
	 * @var array
	 */
	protected $data = [];

	/**
	 * @var array
	 */
	protected $bindings = [];

	/**
	 * @var \Asta\Database\Query\Builder
	 */
	protected $select;

	/**
	 * Creates a new InsertBuilder instance.
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
	 * Returns the Builder's processor
	 *
	 * @return \Asta\Database\Query\Processors\Processor
	 */
	public function getProcessor()
	{
		return $this->connection->getProcessor();
	}

	/**
	 * Defines the value of the given field.
	 *
	 * @param	string	$field
	 * @param	mixed	$value
	 * @return	$this
	 */
	public function field(string $field, $value)
	{
		$this->data[$field] = $value;
		//
		$this->addBinding($value);
		//
		return $this;
	}

	/**
	 * Reset all fields with an associative array.
	 *
	 * @param	array	$fields
	 * @return	$this
	 */
	public function fields(array $fields)
	{
		$this->data = [];
		//
		$this->removeAllBindings();
		//
		foreach ($fields as $field => $value) {
			$this->field($field, $value);
		}
		//
		return $this;
	}

	/**
	 * Adds a value binding
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function addBinding($value, string $type = 'where')
	{
		$next = $this->generateBinder();
		//
		$this->bindings[$next] = $value;
		//
		return $next;
	}

	/**
	 * Removes all bindings.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function removeAllBindings()
	{
		$this->bindings = [];
		//
		return $this;
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
		return $this->select = (new Builder($this->getConnection()))->select($columns);
	}

	/**
	 * Defines the selected column listing.
	 *
	 * @param	string|array	$columns
	 * @return	$this
	 */
	public function select($columns = ['*'])
	{
		$this->getSelect($columns);
		//
		return $this;
	}

	/**
	 * Force the underlying select to return unique columns.
	 *
	 * @return	$this
	 */
	public function distinct()
	{
		$this->getSelect()->distinct();
		//
		return $this;
	}

	/**
	 * Adds a subquery as a select column to the underlying Select.
	 *
	 * @param	\Closure	$query
	 * @param	string		$as
	 * @return	$this
	 */
	public function selectSub(Closure $query, $as)
	{
		$this->getSelect()->selectSub($query, $as);
		//
		return $this;
	}

	/**
	 * Adds the from clause.
	 *
	 * @param	string|\Closure|\Asta\Database\Query\Builder	$table
	 * @param	string|null	$as
	 * @return	$this
	 */
	public function from($table, string $as = null)
	{
		$this->getSelect()->from($table, $as);
		//
		return $this;
	}

	/**
	 * Adds a subquery as a table in the from clause.
	 *
	 * @param	\Closure	$table
	 * @param	string		$as
	 * @return	$this
	 */
	public function fromSub(Closure $table, string $as)
	{
		$this->getSelect()->fromSub($table, $as);
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
	 * Adds a ORDER BY clause.
	 *
	 * @param	string	$field
	 * @param	bool	$asc = true
	 * @return	$this
	 */
	public function orderBy(string $field, bool $asc = true)
	{
		$this->getSelect()->orderBy($field, $asc);
		//
		return $this;
	}

	/**
	 * Adds a LIMIT option to a ORDER BY clause.
	 *
	 * @param	int	$count
	 * @return	$this
	 */
	public function limit(int $count)
	{
		$this->getSelect()->limit($count);
		//
		return $this;
	}

	/**
	 * Adds a OFFSET option to a ORDER BY clause.
	 *
	 * @param	int	$count
	 * @return	$this
	 */
	public function offset(int $count)
	{
		$this->getSelect()->offset($count);
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
			return $this->getGrammar()->compileInsertSelect(
				$this->table,
				array_keys($this->data),
				$this->getSelect()->getColumns(),
				$this->getSelect()->getTable(),
				$this->getSelect()->getJoins(),
				$this->getSelect()->getDistinct()
			);
		}
		//
		return $this->getGrammar()->compileInsertValues(
			$this->table, array_keys($this->data), array_keys($this->values())
		);
	}

	/**
	 * Execute the query and returns the results.
	 *
	 * @return	$this
	 */
	public function execute()
	{
		return $this->getConnection()->insert($this->toSql(), $this->values());
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

