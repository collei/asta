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
	 * @var array
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
	 * @var Connection
	 */
	protected $connection;

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
	 * Returns the Builder's processor
	 *
	 * @return Processor
	 */
	public function getProcessor()
	{
		return $this->connection->getProcessor();
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
			if (is_string($as)) {
				$this->columns[] = $this->compileAliasing($column, $as);
			} else {
				$this->columns[] = $column;
			}
		}
	}

	protected function generateAlias()
	{
		return ('A' . (++self::$aliasingCounter));
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
		$next = ':n' . (++self::$bindingCounter) . ':';
		//
		$this->bindings[$type][$next] = $value;
		//
		return $next;
	}

	/**
	 * Gets bindings from another Builder
	 *
	 * @param self $query
	 * @param mixed $type = 'where'
	 * @return void
	 */
	protected function importBindingsFromSubquery(self $query, $type = 'where')
	{
		$type = $type ?? 'where';
		//
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException(
				"Invalid binding type: {$type}."
			);
		}
		//
		foreach ($query->getBindings($type) as $binder => $bound) {
			$this->bindings[$type][$binder] = $bound;
		}
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
	 **/

	protected function isQueryable($query)
	{
		return ($query instanceof Closure)
			|| ($query instanceof self);
	}

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

	protected function prepareValue($value)
	{
		if (is_array($value)) {
			$passed = [];
			foreach ($value as $subvalue) {
				$passed[] = $this->prepareValue($subvalue);
			}
			return $this->getGrammar()->wrapItInParenthesis(implode(',', $passed));
		}
		//
		if ($value instanceof Expression) {
			return (string) $value->getValue();
		}
		//
		if ($value instanceof Builder) {
			return $this->getGrammar()->wrapItInParenthesis($value->toSql());
		}
		//
		if (preg_match('#^(\:n\d+\:)$#i', $value, $found)) {
			return $this->retrieveBound($found[1]);
		}
		//
		if (preg_match($this->getGrammar()->getRegexFieldspecWhere(), $value)) {
			return $value;
		}
		//
		return $this->castValueToSqlLiteral($value);
	}

	/**
	 **/

	public function __construct(ConnectionInterface $connection)
	{
		$this->connection = $connection;
	}

	public static function new()
	{
		return new static(Connection::getFromPool());
	}

	public function newQuery()
	{
		return new static($this->connection);
	}

	public function forSubQuery()
	{
		return $this->newQuery();
	}

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

	public function distinct()
	{
		$this->distinct = true;
		//
		return $this;
	}

	public function selectSub($query, $as)
	{
		$callback = $query;
		$callback($query = $this->forSubQuery());
		$query = $query->toSql();
		$this->columns[] = $this->getGrammar()->compileAliasing(
			$this->getGrammar()->wrapItInParenthesis($query), $as
		);
		//
		return $this;
	}

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

	public function fromSub(Closure $table, string $as)
	{
		$table($query = $this->forSubQuery());
		//
		$this->from = $this->getGrammar()->compileAliasing(
			$this->getGrammar()->wrapItInParenthesis($query->toSql()), $as
		);
		//
		return $this;
	}

	public function join(
		$table, $first,
		$operator = null, $second = null, $type = 'inner', $where = false
	) {
		$join = JoinClause::make($this, $type, $table);
		//
		if ($first instanceof Closure) {
			$first($join);
		} else {
			$method = $where ? 'where' : 'on';
			$join->$method($first, $operator, $second);
		}
		//
		$this->joins[] = $join->toSql();
		//
		return $this;
	}

	public function leftJoin(
		$table, $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->join(
			$table, $first, $operator, $second, 'left', $where
		);
	}

	public function rightJoin(
		$table, $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->join(
			$table, $first, $operator, $second, 'right', $where
		);
	}

	public function crossJoin(
		$table, $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->join(
			$table, $first, $operator, $second, 'cross', $where
		);
	}

	public function joinSub(
		Builder $query, $as, Closure $first,
		$operator = null, $second = null, $type = 'inner', $where = false
	) {
		return $this->join(
			[$as => $query], $first, $operator, $second, $type, $where
		);
	}

	public function leftJoinSub(
		Builder $query, $as, Closure $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->joinSub(
			$query, $as, $first, $operator, $second, 'left', $where
		);
	}

	public function rightJoinSub(
		Builder $query, $as, Closure $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->joinSub(
			$query, $as, $first, $operator, $second, 'right', $where
		);
	}

	public function crossJoinSub(
		Builder $query, $as, Closure $first,
		$operator = null, $second = null, $where = false
	) {
		return $this->joinSub(
			$query, $as, $first, $operator, $second, 'cross', $where
		);
	}

	protected function addBasicWhere(
		$column, $operator = null, $value = null, $boolean = 'and'
	) {
		$this->wheres[] = [
			'basic',
			$column,
			$operator,
			$this->addBinding($value, 'where'),
			$boolean
		];
	}

	protected function addNestedWhere(
		self $query, string $boolean
	) {
		$this->wheres[] = ['nested', $query->toSql(), null, null, $boolean];
		//
		$this->importBindingsFromSubquery($query, 'where');
	}

	public function where(
		$column, $operator = null, $value = null, $boolean = 'and'
	) {
		$boolean = (strtolower($boolean) === 'and') ? 'and' : 'or';
		//
		if (is_array($column)) {
			foreach ($column as $name => $value) {
				$this->addBasicWhere($name, '=', $value, $boolean);
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
			if (is_null($value)) {
				[$operator, $value] = ['=', $operator];
			}
			//
			$this->addBasicWhere($column, $operator, $value, $boolean);
		}
		//
		return $this;
	}

	public function whereNull($column)
	{
		return $this->where($column, 'is', 'null');
	}

	public function andWhereNull($column)
	{
		return $this->where($column, 'is', 'null', 'and');
	}

	public function orWhereNull($column)
	{
		return $this->where($column, 'is', 'null', 'or');
	}

	public function andWhere($column, $operator = null, $value = null)
	{
		return $this->where($column, $operator, $value, 'and');
	}

	public function orWhere($column, $operator = null, $value = null)
	{
		return $this->where($column, $operator, $value, 'or');
	}

	public function whereIn($column, $values)
	{
		return $this->where($column, 'IN', $values, 'and');
	}

	public function orWhereIn($column, $values)
	{
		return $this->where($column, 'IN', $values, 'or');
	}

	public function limit(int $count)
	{
		$this->limit = $count;
		//
		return $this;
	}

	public function take(int $count)
	{
		return $this->limit($count);
	}

	public function offset(int $count)
	{
		$this->offset = $count;
		//
		return $this;
	}

	public function skip(int $count)
	{
		return $this->offset($count);
	}

	protected function wheresToChain(array $wheres)
	{
		$chain = [];
		$total = $count = count($this->wheres);
		//
		foreach ($this->wheres as $item) {
			if ($count < $total) {
				$chain[] = $item[4];
			}
			//
			$type = $item[0];
			//
			if ($type=='basic') {
				$item[3] = $this->prepareValue(
					$this->prepareValue($item[3])
				);
				//
				$chain[] = $this->getGrammar()->compileExpression(
					$item[1], $item[2], $item[3]
				);
			} elseif ($type=='nested') {
				$chain[] = $this->getGrammar()->compileExists(
					$item[1]->toSql()
				);
			}
			//
			--$count;
		}
		//
		return $chain;
	}

	protected function toSql()
	{
		$sql = '';
		$headed = false;
		//
		if (!isset($this->columns)) {
			$this->columns = ['*'];
		}
		//
		if (isset($this->from)) {
			$headed = true;
			$columns = [];
			//
			foreach ($this->columns as $as => $column) {
				if ($column instanceof Closure) {
					$callback = $column;

					$callback($query = $this->forSubQuery());

					$column = $this->getGrammar()->compileAliasing(
						$query->toSql(),
						(is_int($as) ? $this->generateAlias() : $as)
					);
				} elseif ($column instanceof Expression) {
					$column = $column->getValue();
				}
				//
				$columns[] = $column;
			}
			//
			$sql = $this->getGrammar()->compileSelect(
				$this->columns,
				$this->from,
				($this->joins ?? []),
				$this->distinct
			);
		}
		//
		$whereChain = $this->wheresToChain($this->wheres);
		//
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
			$sql .= $this->getGrammar()->compileJoin($type, $table, $as, $whereChain);
		} else {
			$sql .= $this->getGrammar()->compileWhereChain($whereChain);
		}
		//
		if ($this->offset) {
			$sql .= $this->getGrammar()->compileOffsetClause($this->limit);
		}
		//
		if ($this->limit) {
			$sql .= $this->getGrammar()->compileLimitClause($this->limit);
		}
		//
		return $sql;
	}

	public function execute()
	{
		return $this->getConnection()->select($this->toSql());
	}

	public function __toString()
	{
		return $this->toSql();
	}

	public static function raw(string $expression)
	{
		return new Expression($expression);
	}

}



