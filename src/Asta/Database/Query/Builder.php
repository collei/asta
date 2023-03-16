<?php
namespace Asta\Database\Query;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;
use Asta\Database\Processors\ProcessorInterface;
use Asta\Database\Query\Grammars\GrammarInterface;
use Asta\Database\Traits\CastsValues;
use Asta\Database\Query\Clauses\JoinClause;

use Closure;
use Stringable;
use InvalidArgumentException;

/**
 *	The query builder, fully naïve mode (never checks for real existence
 *	of any DB object for query generation).
 *
 *	@author	Collei de Laravel, Collei Inc. <collei@collei.com.br>
 *	@since	2022-10-22
 *
 */
class Builder
{
	use CastsValues;

	/**
	 * @static @var int
	 */
	private static $bindingCounter = 0;

	/**
	 * @static @var int
	 */
	private static $aliasingCounter = 0;

	/**
	 * @var array
	 */
	protected $columns;

	/**
	 * @var string
	 */
	protected $from;

	/**
	 * @var array
	 */
	protected $joins;

	/**
	 * @var array
	 */
	protected $wheres = [];

	/**
	 * @var array
	 */
	protected $groups;

	/**
	 * @var array
	 */
	protected $havings;

	/**
	 * @var array
	 */
	protected $orders;

	/**
	 * @var int|null
	 */
	protected $limit = null;

	/**
	 * @var int|null
	 */
	protected $offset = null;

	/**
	 * @var bool
	 */
	protected $distinct = false;

	/**
	 * @var array
	 */
	protected $bindings = [
		'select' => [],
		'from' => [],
		'join' => [],
		'where' => [],
		'groupBy' => [],
		'having' => [],
		'orderBy' => [],
		'union' => [],
		'unionOrder' => [],
		'update' => [],
	];

	/**
	 * @var array
	 */
	protected $operators = [
		'=', '<', '<=', '>', '>=', '<>', '!=', '<=>',
		'like', 'like binary', 'not like', 'ilike',
		'&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
		'rlike', 'not rlike', 'regexp', 'not regexp',
		'~', '~*', '!~', '!~*', 'similar to', 'not similar to',
		'not ilike', '~~*', '!~~*'
	];

	/**
	 * @var array
	 */
	protected $bitwiseOperators = [
		'&', '|', '^', '<<', '>>', '&~',
	];

	/**
	 * @var \Asta\Database\Connectiona\ConnectionInterface
	 */
	protected $connection;

	/**
	 * Creates a new Builder instance.
	 *
	 * @param	\Asta\Database\Connections\ConnectionInterface	$connection
	 * @return	void
	 */
	public function __construct(ConnectionInterface $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Creates a new Builder instance with a connection obtained from the pool.
	 *
	 * @return	static
	 */
	public static function new()
	{
		return new static(Connection::getFromPool());
	}

	/**
	 * Creates a new Builder instance with the same connection as this one.
	 *
	 * @return	static
	 */
	public function newQuery()
	{
		return new static($this->connection);
	}

	/**
	 * Creates a new Builder instance for a sub query.
	 *
	 * @return	static
	 */
	public function forSubQuery()
	{
		return $this->newQuery();
	}

	/**
	 * Returns the Builder's connection
	 *
	 * @return ConnectionInterface
	 */
	public function getConnection()
	{
		return $this->connection;
	}

	/**
	 * Returns the Builder's grammar
	 *
	 * @return Grammar
	 */
	public function getGrammar()
	{
		return $this->connection->getGrammar();
	}

	/**
	 * Adds columns to the select clause
	 *
	 * @param array $columns
	 * @return void
	 */
	protected function addColumns(array $columns)
	{
		foreach ($columns as $as => $column) {
			$this->columns[] = is_string($as)
				? $this->compileAliasing($column, $as)
				: $column;
		}
	}

	/**
	 * Retrieves the current select columns.
	 *
	 * @return array
	 */
	protected function getColumns()
	{
		return $this->columns;
	}

	/**
	 * Retrieves the current select table (from).
	 *
	 * @return string
	 */
	protected function getTable()
	{
		return $this->from;
	}

	/**
	 * Retrieves the current select joins.
	 *
	 * @return array
	 */
	protected function getJoins()
	{
		return $this->joins;
	}

	/**
	 * Retrieves the current state of $distinct.
	 *
	 * @return bool
	 */
	public function getDistinct()
	{
		return $this->distinct;
	}

	/**
	 * Generate a counting alias for select field aliasing.
	 *
	 * @return string
	 */
	protected function generateAlias()
	{
		return ('A' . (++self::$aliasingCounter));
	}

	/**
	 * Returns a sequencial Binding identifier.
	 *
	 * @return string
	 */
	protected final function generateBinder()
	{
		return ':n' . (++self::$bindingCounter) . 'n';
	}

	/**
	 * Adds a value binding
	 *
	 * @param mixed $value
	 * @param string $type = 'where'
	 * @return string
	 */
	protected function addBinding($value, string $type = 'where')
	{
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException(
				"Invalid binding type: {$type}."
			);
		}
		//
		$next = $this->generateBinder();
		//
		$this->bindings[$type][$next] = $value;
		//
		return $next;
	}

	/**
	 * Removes the given binding.
	 *
	 * @param mixed $binding
	 * @param string $type = 'where'
	 * @return string
	 */
	protected function removeBinding(string $binding, string $type = 'where')
	{
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException(
				"Invalid binding type: {$type}."
			);
		}
		//
		if (array_key_exists($binding, $this->bindings[$type])) {
			unset($this->bindings[$type][$binding]);
		}
		//
		return $binding;
	}

	/**
	 * Merges bindings from outside.
	 *
	 * @param array $otherBindings
	 * @param string $type = 'where'
	 * @return void
	 */
	protected function mergeBindings(array $otherBindings, string $type = 'where')
	{
		$this->bindings[$type] = array_merge($this->bindings[$type], $otherBindings);
	}

	/**
	 * Gets bindings from another Builder
	 *
	 * @param self $query
	 * @param mixed $type = 'where'
	 * @return void
	 */
	protected function importBindingsFromSubquery(Builder $query, $type = null)
	{
		if (is_null($type)) {
			foreach ($this->bindings as $type => $mine) {
				$this->mergeBindings($query->getBindings($type), $type);
			}
			//
			return;
		}
		//
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException(
				"Invalid binding type: {$type}."
			);
		}
		//
		$this->mergeBindings($query->getBindings($type), $type);
	}

	/**
	 * Gets value from a given binding item
	 *
	 * @param mixed $binder
	 * @param string $type = 'where'
	 * @return mixed
	 */
	protected function retrieveBound($binder, string $type = null)
	{
		if (!is_null($type)) {
			if (!array_key_exists($type, $this->bindings)) {
				throw new InvalidArgumentException(
					"Invalid binding type: {$type}."
				);
			}
			//
			if (!array_key_exists($binder, $this->bindings[$type])) {
				throw new InvalidArgumentException(
					"Binding not found: {$binder}."
				);
			}
			//
			return $this->bindings[$type][$binder];
		} else {
			foreach ($this->bindings as $sub) {
				foreach ($sub as $keeper => $kept) {
					if ($binder === $keeper) {
						return $kept;
					}
				}
			}
		}
		//
		throw new InvalidArgumentException(
			"This binding does not exist: {$binder}."
		);
	}

	/**
	 * Returns the giving binding set
	 *
	 * @param string $type = 'where'
	 * @return array
	 */
	protected function getBindings(string $type = 'where')
	{
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException(
				"Invalid binding type: {$type}."
			);
		}
		//
		return $binds = $this->bindings[$type];
	}

	/**
	 * Performs metabinding, allowing the user specify ? placeholders
	 * for the sqlRaw clauses.
	 *
	 * @param	string	$rawSql
	 * @param	array	$metabindings = []
	 * @param	string	$type = 'where'
	 * @return	string
	 */
	protected function performQuestionMetaBinding(
		string $rawSql, array $metabindings = [], string $type = 'where'
	) {
		$namedBindings = [];
		//
		foreach ($metabindings as $value) {
			$binder = $this->addBinding($value, $type);
			//
			$namedBindings[$binder] = $value;
		}
		//
		if ($hasEscaped = (false !== ($pos = strpos($rawSql, '??')))) {
			$rawSql = str_replace('??', '::QUESTION_PACEHOLDER::', $rawSql);
		}
		//
		foreach ($namedBindings as $name => $value) {
			$pos = strpos($rawSql, '?');
			$rawSql = substr_replace($rawSql, $name, $pos, 1);
		}
		//
		return $hasEscaped
					? str_replace('::QUESTION_PACEHOLDER::', '??', $rawSql)
					: $rawSql;
	}

	/**
	 * Adds a nested where.
	 *
	 * @param	\Asta\Database\Query\Builder $query
	 * @param	string	$boolean = 'and'
	 * @return	array
	 */
	public function addNestedWhereQuery($query, $boolean = 'and')
	{
		if (count($query->wheres)) {
			$this->wheres[] = ['nested', $query, null, null, $boolean];
			//
			$this->mergeBindings($query->getBindings('where'), 'where');
		}
		//
		return $this;
	}

	/**
	 * Returns an array of bound values in the current query.
	 *
	 * @return array
	 */
	public function values()
	{
		$boundValues = [];
		//
		foreach ($this->bindings as $type => $bindings) {
			$boundValues = array_merge($boundValues, $bindings);
		}
		//
		return $boundValues;
	}

	/**
	 * Prepare the operator and value for a where clause.
	 *
	 * @param	mixed	$value
	 * @param	string	$operator
	 * @param	bool	$useDefault = false
	 * @return	array
	 *
	 * @throws	\InvalidArgumentException
	 */
	public function prepareValueAndOperator($value, $operator, $useDefault = false)
	{
		if ($useDefault) {
			return [$operator, '='];
		} elseif ($this->invalidOperatorAndValue($operator, $value)) {
			throw new InvalidArgumentException('Illegal operator and value combination.');
		}
		//
		return [$value, $operator];
	}

	/**
	 * Determine if the given operator and value combination is legal.
	 *
	 * Prevents using NULL values with invalid operators.
	 *
	 * @param	string	$operator
	 * @param	mixed	$value
	 * @return	bool
	 */
	protected function invalidOperatorAndValue($operator, $value)
	{
		return is_null($value)
			&& in_array($operator, $this->operators)
			&& !in_array($operator, ['=','<>','!=']);
	}

	/**
	 * Tells if $operator is not a valid operator
	 *
	 * @param mixed $operator
	 * @return bool
	 */
	protected function invalidOperator($operator)
	{
		return (! is_string($operator))
			|| (! in_array(strtolower($operator), $this->operators, true)); 
	}

	/**
	 * Tells if $operator is a bitwise operator
	 *
	 * @param mixed $operator
	 * @return bool
	 */
	protected function isBitwiseOperator($operator)
	{
		return in_array(strtolower($operator), $this->operators, true);
	}

	/**
	 * Tells if $column is a valid SQL column for to be on where clauses
	 * in place of a value literal.
	 *
	 * @param string $column
	 * @return bool
	 */
	protected function isValidSqlColumn(string $column)
	{
		return $this->getConnection()->getGrammar()->isValidColumnName($column);
	}

	/**
	 * Tells if $query is either (a Builder instance or a Closure) or not.
	 *
	 * @param string $query
	 * @return bool
	 */
	protected function isQueryable($query)
	{
		return ($query instanceof Closure) || ($query instanceof Builder);
	}

	/**
	 * Executes value casting.
	 *
	 * @param	string	$value
	 * @param	mixed	$alternative = null
	 * @return	mixed
	 */
	protected function castValueToSqlLiteral($value, $alternative = null)
	{
		$value = $this->castValue($value, $alternative);
		//
		if (is_string($value) || $value instanceof Stringable) {
			return $this->getGrammar()->valueToSqlString($value);
		} elseif ($value instanceof DateTime) {
			return $this->getGrammar()->valueToSqlDateTime($value);
		} elseif (is_float($value)) {
			return $this->getGrammar()->valueToSqlFloat($value);
		} elseif (is_int($value)) {
			return $this->getGrammar()->valueToSqlInt($value);
		} elseif (is_bool($value)) {
			return $this->getGrammar()->valueToSqlBoolean($value);
		} elseif (is_null($value)) {
			return 'NULL';
		}
	}

	/**
	 * Executes value preparation for the query bindings, if needed.
	 *
	 * @param	mixed	$value
	 * @return	string
	 */
	protected function prepareValue($value)
	{
		if (is_array($value)) {
			$passed = [];
			//
			foreach ($value as $subvalue) {
				$passed[] = $this->prepareValue($subvalue);
			}
			//
			// Let's leave to the grammar compiler
			// the task of surrounding it with parenthesis
			return implode(',', $value);
		}
		//
		if ($value instanceof Expression) {
			return (string) $value->getValue();
		}
		//
		if ($value instanceof Builder) {
			$this->importBindingsFromSubquery($value);
			//
			return $this->getGrammar()->wrapItInParenthesis($value->toSql());
		}
		//
		if (preg_match('#^\:n\d+n$#i', $value)) {
			return $value;
		}
		//
		return $this->castValueToSqlLiteral($value);
	}

	/**
	 * Adds select columns.
	 *
	 * @param	array|string	...$columns
	 * @return	$this
	 */
	public function select($columns = ['*'])
	{
		$this->columns = [];
		$this->bindings['select'] = [];
		//
		$columns = is_array($columns) ? $columns : func_get_args();
		//
		$this->addColumns($columns);
		//
		return $this;
	}

	/**
	 * Force the query to return unique columns.
	 *
	 * @return	$this
	 */
	public function distinct()
	{
		$this->distinct = true;
		//
		return $this;
	}

	/**
	 * Adds a subquery as a select column.
	 *
	 * @param	\Closure	$query
	 * @param	string		$as
	 * @return	$this
	 */
	public function selectSub(Closure $query, $as)
	{
		$callback = $query;
		//
		$callback($query = $this->forSubQuery());
		//
		$this->importBindingsFromSubquery($query);
		//
		$query = $query->toSql();
		//
		$this->columns[] = $this->getGrammar()->compileAliasing(
			$this->getGrammar()->wrapItInParenthesis($query), $as
		);
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
		if ($this->isQueryable($table)) {
			return $this->fromSub($table, $as);
		}
		//
		$this->from = $as
			? $this->getGrammar()->compileAliasing($table, $as)
			: $table;
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
		$table($query = $this->forSubQuery());
		//
		$this->importBindingsFromSubquery($query);
		//
		$this->from = $this->getGrammar()->compileAliasing(
			$this->getGrammar()->wrapItInParenthesis($query->toSql()), $as
		);
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
		if (is_array($table) && current($table) instanceof Builder) {
			$as = key($table);
			//
			$this->importBindingsFromSubquery($table[$as]);
		}
		//
		$join = JoinClause::make($this, $type, $table);
		//
		if (0 !== strcasecmp($type, 'cross')) {
			if ($first instanceof Closure) {
				$first($join);
				//
				$this->importBindingsFromSubquery($join, 'where');
			} else {
				$method = $where ? 'where' : 'on';
				$join->$method($first, $operator, $second);
			}
		}
		//
		$this->joins[] = $join->toSql();
		//
		return $this;
	}

	/**
	 * Adds a left join clause.
	 *
	 * @param	mixed	$table
	 * @param	mixed	$first
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @param	bool	$where = false
	 * @return	$this
	 */
	public function leftJoin(
		$table, $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->join(
			$table, $first, $operator, $second, 'left', $where
		);
	}

	/**
	 * Adds a right join clause.
	 *
	 * @param	mixed	$table
	 * @param	mixed	$first
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @param	bool	$where = false
	 * @return	$this
	 */
	public function rightJoin(
		$table, $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->join(
			$table, $first, $operator, $second, 'right', $where
		);
	}

	/**
	 * Adds a cross join clause.
	 *
	 * @param	mixed	$table
	 * @return	$this
	 */
	public function crossJoin($table)
	{
		return $this->join(
			$table, null, null, null, 'cross', false
		);
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
		Builder $query, string $as, Closure $first,
		$operator = null, $second = null, $type = 'inner', $where = false
	) {
		return $this->join(
			[$as => $query], $first, $operator, $second, $type, $where
		);
	}

	/**
	 * Adds a left join clause.
	 *
	 * @param	\Astya\Database\Query\Builder	$query
	 * @param	string		$as
	 * @param	\Closure	$first
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @param	bool	$where = false
	 * @return	$this
	 */
	public function leftJoinSub(
		Builder $query, $as, Closure $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->joinSub(
			$query, $as, $first, $operator, $second, 'left', $where
		);
	}

	/**
	 * Adds a right join clause.
	 *
	 * @param	\Astya\Database\Query\Builder	$query
	 * @param	string		$as
	 * @param	\Closure	$first
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @param	bool	$where = false
	 * @return	$this
	 */
	public function rightJoinSub(
		Builder $query, $as, Closure $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->joinSub(
			$query, $as, $first, $operator, $second, 'right', $where
		);
	}

	/**
	 * Adds a cross join clause.
	 *
	 * @param	\Astya\Database\Query\Builder	$query
	 * @param	string		$as
	 * @return	$this
	 */
	public function crossJoinSub(Builder $query, $as)
	{
		return $this->joinSub(
			$query, $as, null, null, null, 'cross', false
		);
	}

	/**
	 * Adds a basic, non-nested, where clause.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @param	string	$boolean = 'and'
	 * @param	string	$clause = 'where'
	 * @return	void
	 */
	protected function addBasicWhere(
		$column, $operator = null, $value = null, $boolean = 'and', $clause = null
	) {
		if (is_array($value)) {
			switch (strtoupper(trim($operator))) {
				case 'IN':
				case 'NOT IN':
					$values = [];
					foreach ($value as $piece) {
						$values[] = $this->addBinding($piece, 'where');
					}
					$value = $values;
					break;
				case 'BETWEEN':
				case 'NOT BETWEEN':
					$value = [
						$this->addBinding($value[0], 'where'),
						$this->addBinding($value[1], 'where'),
					];
			}
		} elseif ($value instanceof Builder) {
			$this->importBindingsFromSubquery($value);
			//
			$value = new Expression($value->toSql());
		} elseif (!is_object($value)) {
			$value = $this->addBinding($value, 'where');
		}
		//
		if ('having' === $clause) {
			$this->havings[] = ['basic', $column, $operator, $value, $boolean];
		} else {
			$this->wheres[] = ['basic', $column, $operator, $value, $boolean];
		}
	}

	/**
	 * Adds a basic, raw where clause.
	 *
	 * @param	mixed	$sql
	 * @param	array	$bindings = []
	 * @param	string	$boolean = 'and'
	 * @param	string	$clause = 'where'
	 * @return	void
	 */
	protected function addRawWhere(
		$sql, array $bindings = [], $boolean = 'and', string $clause = null
	) {
		if ($sql instanceof Expression) {
			$sql = $sql->getValue();
		}
		//
		$clause = ('having' === $clause) ? 'having' : 'where';
		//
		$metaSql = $this->performQuestionMetaBinding($sql, $bindings, $clause);
		//
		if ('having' === $clause) {
			$this->havings[] = ['raw', $metaSql, null, null, $boolean];
		} else {
			$this->wheres[] = ['raw', $metaSql, null, null, $boolean];
		}
	}

	/**
	 * Adds a nested where clause.
	 *
	 * @param	\Asta\Database\Query\Builder	$query
	 * @param	string	$boolean
	 * @return	void
	 */
	protected function addNestedWhere(Builder $query, string $boolean)
	{
		$this->wheres[] = ['nested', $query->toSql(), null, null, $boolean];
		//
		$this->importBindingsFromSubquery($query, 'where');
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
		$column, $operator = null, $value = null, $boolean = 'and'
	) {
		$boolean = (strtolower($boolean) === 'and') ? 'and' : 'or';
		//
		if (is_array($column)) {
			foreach ($column as $name => $value) {
				if (is_int($name) && is_array($value) && count($value) >= 3) {
					$this->addBasicWhere($value[0], $value[1], $value[2], $boolean);
				} else {
					$this->addBasicWhere($name, '=', $value, $boolean);
				}
			}
			//
			return $this;
		}
		//
		if ($column instanceof Closure && is_null($operator)) {
			$callback = $column;

			$callback($query = $this->forSubQuery()); 

			$this->addNestedWhere($query, $boolean);
		} else {
			[$value, $operator] = $this->prepareValueAndOperator(
				$value, $operator, is_null($value)
			);
			//
			$this->addBasicWhere($column, $operator, $value, $boolean);
		}
		//
		return $this;
	}

	/**
	 * Adds a 'and where' clause to the current query.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @return	$this
	 */
	public function andWhere($column, $operator = null, $value = null)
	{
		return $this->where($column, $operator, $value, 'and');
	}

	/**
	 * Adds a 'or where' clause to the current query.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @return	$this
	 */
	public function orWhere($column, $operator = null, $value = null)
	{
		return $this->where($column, $operator, $value, 'or');
	}

	/**
	 * Adds a where to compare two columns.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @param	string	$boolean = 'and'
	 * @return	$this
	 */
	public function whereColumn($column, $operator = null, $value = null, $boolean = 'and')
	{
		if (is_null($value)) {
			list($operator, $value) = array('=', $operator);
		}
		//
		if (! $this->isValidSqlColumn($value)) {
			throw new InvalidArgumentException(
				sprintf('Invalid SQL column: %s.', $value)
			);
		}
		//
		return $this->where($column, $operator, new Expression($value), $boolean);
	}

	/**
	 * Adds a 'or where' clause to compare two columns.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @return	$this
	 */
	public function orWhereColumn($column, $operator = null, $value = null)
	{
		return $this->whereColumn($column, $operator, $value, 'or');
	}

	/**
	 * Adds a where IN expression.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$values
	 * @return	$this
	 */
	public function whereIn($column, $values)
	{
		return $this->where($column, 'in', $values, 'and');
	}

	/**
	 * Adds a 'or where' IN expression.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$values
	 * @return	$this
	 */
	public function orWhereIn($column, $values)
	{
		return $this->where($column, 'in', $values, 'or');
	}

	/**
	 * Adds a where NOT IN expression.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$values
	 * @return	$this
	 */
	public function whereNotIn($column, $values)
	{
		return $this->where($column, 'not in', $values, 'and');
	}

	/**
	 * Adds a 'or where' NOT IN expression.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$values
	 * @return	$this
	 */
	public function orWhereNotIn($column, $values)
	{
		return $this->where($column, 'not in', $values, 'or');
	}

	/**
	 * Adds a where IS NULL expression.
	 *
	 * @param	mixed	$column
	 * @return	$this
	 */
	public function whereNull($column)
	{
		return $this->where($column, 'is', 'NULL');
	}

	/**
	 * Adds a 'or where' IS NULL expression.
	 *
	 * @param	mixed	$column
	 * @return	$this
	 */
	public function orWhereNull($column)
	{
		return $this->where($column, 'is', 'NULL', 'OR');
	}

	/**
	 * Adds a where IS NOT NULL expression.
	 *
	 * @param	mixed	$column
	 * @return	$this
	 */
	public function whereNotNull($column)
	{
		return $this->where($column, 'is not', 'NULL');
	}

	/**
	 * Adds a 'or where' IS NOT NULL expression.
	 *
	 * @param	mixed	$column
	 * @return	$this
	 */
	public function orWhereNotNull($column)
	{
		return $this->where($column, 'is not', 'NULL', 'OR');
	}

	/**
	 * Adds a where BETWEEN expression.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function whereBetween($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		return $this->where($column, 'between', $between, 'and');
	}

	/**
	 * Adds a 'or where' BETWEEN expression.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function orWhereBetween($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		return $this->where($column, 'between', $between, 'or');
	}

	/**
	 * Adds a where NOT BETWEEN expression.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function whereNotBetween($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		return $this->where($column, 'not between', $between, 'and');
	}

	/**
	 * Adds a 'or where' NOT BETWEEN expression.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function orWhereNotBetween($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		return $this->where($column, 'not between', $between, 'or');
	}

	/**
	 * Adds a where BETWEEN expression against table columns.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function whereBetweenColumns($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		if (! $this->isValidSqlColumn($between[0]) || ! $this->isValidSqlColumn($between[1])) {
			throw new InvalidArgumentException('Both values must be valid SQL column names !');
		}
		//
		$between = new Expression($between[0] . ' AND ' . $between[1]);
		//
		return $this->where($column, 'between', $between, 'and');
	}

	/**
	 * Adds a 'or where' BETWEEN expression against table columns.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function orWhereBetweenColumns($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		if (! $this->isValidSqlColumn($between[0]) || ! $this->isValidSqlColumn($between[1])) {
			throw new InvalidArgumentException('Both values must be valid SQL column names !');
		}
		//
		$between = new Expression($between[0] . ' AND ' . $between[1]);
		//
		return $this->where($column, 'between', $between, 'or');
	}

	/**
	 * Adds a where NOT BETWEEN expression against table columns.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function whereNotBetweenColumns($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		if (! $this->isValidSqlColumn($between[0]) || ! $this->isValidSqlColumn($between[1])) {
			throw new InvalidArgumentException('Both values must be valid SQL column names !');
		}
		//
		$between = new Expression($between[0] . ' AND ' . $between[1]);
		//
		return $this->where($column, 'not between', $between, 'and');
	}

	/**
	 * Adds a 'or where' NOT BETWEEN expression against table columns.
	 *
	 * @param	mixed	$column
	 * @param	array	$between
	 * @return	$this
	 */
	public function orWhereNotBetweenColumns($column, array $between)
	{
		if (count($between) < 2) {
			throw new InvalidArgumentException('Array must have two values !');
		}
		//
		if (! $this->isValidSqlColumn($between[0]) || ! $this->isValidSqlColumn($between[1])) {
			throw new InvalidArgumentException('Both values must be valid SQL column names !');
		}
		//
		$between = new Expression($between[0] . ' AND ' . $between[1]);
		//
		return $this->where($column, 'not between', $between, 'or');
	}

	/**
	 * Add a group by clause to the query.
	 *
	 * @param	array|string	...$groups
	 * @return	$this
	 */
	public function groupBy(...$groups)
	{
		foreach ($groups as $group) {
			$this->groups = array_merge(
				($this->groups ?? []), Arr::wrap($group)
			);
		}
		//
		return $this;
	}

	/**
	 * Add a raw group by clause to the query.
	 *
	 * @param	string	$sql
	 * @param	array	$bindings = []
	 * @return	$this
	 */
	public function groupByRaw($sql, array $bindings = [])
	{
		$metaSql = $this->performQuestionMetaBinding($sql, $bindings, 'groupBy');
		//
		$this->groups[] = new Expression($metaSql);
		//
		return $this;
	}

	/**
	 * Adds a expression to the having clause of the current query.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @param	string	$boolean = 'and'
	 * @return	$this
	 */
	public function having(
		$column, $operator = null, $value = null, $boolean = 'and'
	) {
		$boolean = (strtolower($boolean) === 'and') ? 'and' : 'or';
		//
		if (is_array($column)) {
			foreach ($column as $name => $value) {
				if (is_int($name) && is_array($value) && count($value) >= 3) {
					$this->addBasicWhere($value[0], $value[1], $value[2], $boolean, 'having');
				} else {
					$this->addBasicWhere($name, '=', $value, $boolean, 'having');
				}
			}
			//
			return $this;
		}
		//
		[$value, $operator] = $this->prepareValueAndOperator(
			$value, $operator, is_null($value)
		);
		//
		if ($this->invalidOperator($operator)) {
			[$value, $operator] = [$operator, '='];
		}
		//
		$this->addBasicWhere($column, $operator, $value, $boolean, 'having');
		//
		return $this;
	}

	/**
	 * Adds a 'or having' clause to the current query.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @return	$this
	 */
	public function orHaving($column, $operator = null, $value = null)
	{
		[$value, $operator] = $this->prepareValueAndOperator(
			$value, $operator, is_null($value)
		);
		//
		return $this->having($column, $operator, $value, 'or');
	}

	/**
	 * Adds a raw 'having' clause to the current query.
	 *
	 * @param	string	$sql
	 * @param	array	$bindings = []
	 * @param	string	$boolean = 'and'
	 * @return	$this
	 */
	public function havingRaw($sql, array $bindings = [], $boolean = 'and')
	{
		$this->addRawWhere($sql, $bindings, $boolean, 'having');
		//
		return $this;
	}

	/**
	 * Adds a raw 'or having' clause to the current query.
	 *
	 * @param	string	$sql
	 * @param	array	$bindings = []
	 * @return	$this
	 */
	public function orHavingRaw($sql, array $bindings = [])
	{
		return $this->havingRaw($sql, $bindings, 'or');
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
		$this->orders[$field] = $asc ? 'ASC' : 'DESC';
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
		$this->limit = $count;
		//
		return $this;
	}

	/**
	 * Alias of limit().
	 * @see limit()
	 *
	 * @param	int	$count
	 * @return	$this
	 */
	public function take(int $count)
	{
		return $this->limit($count);
	}

	/**
	 * Adds a OFFSET option to a ORDER BY clause.
	 *
	 * @param	int	$count
	 * @return	$this
	 */
	public function offset(int $count)
	{
		$this->offset = $count;
		//
		return $this;
	}

	/**
	 * Alias of offset().
	 * @see offset()
	 *
	 * @param	int	$count
	 * @return	$this
	 */
	public function skip(int $count)
	{
		return $this->offset($count);
	}

	/**
	 * Compiles all the where expressions.
	 *
	 * @param	array	$wheres
	 * @return	array
	 */
	protected function wheresToChain(array $wheres = null)
	{
		$chain = [];
		$wheres = $wheres ?? [];
		$total = $count = count($wheres);
		//
		foreach ($wheres as $item) {
			if ($count < $total) {
				$chain[] = $item[4];
			}
			//
			$type = $item[0];
			//
			if ($type=='basic') {
				$item[3] = $this->prepareValue($item[3]);
				//
				switch (strtoupper($item[2])) {
					case 'BETWEEN':
						$chain[] = $this->getGrammar()->compileBetweenExpression(
							$item[1], $item[3], false
						);
						break;
					case 'NOT BETWEEN':
						$chain[] = $this->getGrammar()->compileBetweenExpression(
							$item[1], $item[3], true
						);
						break;
					case 'IN':
						$chain[] = $this->getGrammar()->compileInExpression(
							$item[1], $item[3], false
						);
						break;
					case 'NOT IN':
						$chain[] = $this->getGrammar()->compileInExpression(
							$item[1], $item[3], true
						);
						break;
					default:
						$chain[] = $this->getGrammar()->compileExpression(
							$item[1], $item[2], $item[3]
						);
				}
				//
			} elseif ($type=='raw') {
				$chain[] = $item[1];
				//
			} elseif ($type=='nested') {
				$chain[] = $this->getGrammar()->compileExists(
					$item[1]->toSql()
				);
				//
				$this->importBindingsFromSubquery($item[1], 'where');
			}
			//
			--$count;
		}
		//
		return $chain;
	}

	/**
	 * Inserts new records in the database.
	 *
	 * @param	array	$values
	 * @return	bool
	 */
	public function insert(array $values)
	{
		if (empty($values)) {
			return true;
		}
		//
		if (! is_array(reset($values))) {
			$values = [$values];
		} else {
			foreach ($values as $key => $value) {
				ksort($value);
				//
				$values[$key] = $value;
			}
		}
		//
		$inserter = $this->getConnection()->getInserter($this->from);
		//
		$results = true;
		//
		foreach ($values as $k => $record) {
			$result = (clone $inserter)->fields($record)->execute();
			//
			$results = $results && ($result > 0 || $result === true);
		}
		//
		return $results;
	}

	/**
	 * Updates records in the database.
	 *
	 * @param	array	$values
	 * @return	string
	 */
	public function update(array $values)
	{
		$updater = $this->getConnection()->getUpdater($this->from);
		//
		foreach ($values as $field => $value) {
			$updater->set($field, $value);
		}
		//
		return $updater->execute();
	}

	/**
	 * Compiles the builder into a SQL query with named placeholders.
	 *
	 * @return	string
	 */
	protected function toSql()
	{
		if ($this instanceof JoinClause) {
			$type = strtoupper($this->type);
			$as = null;
			$table = $this->table;
			//
			if (is_array($table)) {
				$as = array_key_first($table);
				$table = $table[$as]->toSql();
			}
			//
			return $this->getGrammar()->compileJoin(
				$type, $table, $as, $this->wheresToChain($this->wheres)
			);
		}
		//
		$table = $this->from;
		//
		if (isset($this->columns)) {
			$columns = [];
			//
			foreach ($this->columns as $as => $column) {
				if ($column instanceof Closure) {
					$callback = $column;

					$callback($query = $this->forSubQuery());

					$column = $this->getGrammar()->compileAliasing(
						$query->toSql(), (is_int($as) ? $this->generateAlias() : $as)
					);
				} elseif ($column instanceof Expression) {
					$column = $column->getValue();
				}
				//
				$columns[] = $column;
			}
		} else {
			$columns = ['*'];
		}
		//
		$joins = $this->joins ?? [];
		//
		$wheres = $this->wheresToChain($this->wheres);
		//
		$groups = $this->groups ?? [];
		//
		$havings = $this->wheresToChain($this->havings ?? []);
		//
		$orders = $this->orders ?? [];
		//
		$limit = $this->limit ?? null;
		//
		$offset = $this->offset ?? null;
		//
		$sql = $this->getGrammar()->compileSelectStatement(
			$table, $columns, $joins, $wheres, $groups,
			$havings, $orders, $limit, $offset
		);
		//
		return $sql;
	}

	/**
	 * Execute the query and returns the results.
	 *
	 * @return	$this
	 */
	public function execute()
	{
		return $this->getConnection()->select($this->toSql(), $this->values());
	}

	/**
	 * Execute the query and returns the results.
	 *
	 * @return	$this
	 */
	public function __toString()
	{
		return $this->toSql();
	}

	/**
	 * Returns the parameter as a raw expression.
	 *
	 * @param	string	$expression
	 * @return	\Asta\Database\Query\Expression
	 */
	public static function raw(string $expression)
	{
		return new Expression($expression);
	}

}



