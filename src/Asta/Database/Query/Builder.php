<?php
namespace Asta\Database\Query;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;
use Asta\Database\Processors\Processor;
use Asta\Database\Traits\CastsValues;
use Asta\Database\Query\Grammars\Grammar;
use Asta\Database\Query\Clauses\JoinClause;

use Closure;

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

	private static $bindingCounter = 0;

	protected $columns;
	protected $from;
	protected $joins;
	protected $wheres = [];
	protected $groups;
	protected $havings;
	protected $orders;
	protected $limit = null;
	protected $offset = null;

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

	private $operators = [
		'=', '<', '<=', '>', '>=', '<>', '!=', '<=>',
		'like', 'like binary', 'not like', 'ilike',
		'&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
		'rlike', 'not rlike', 'regexp', 'not regexp',
		'~', '~*', '!~', '!~*', 'similar to', 'not similar to',
		'not ilike', '~~*', '!~~*'
	];

	private $bitwiseOperators = [
		'&', '|', '^', '<<', '>>', '&~',
	];

	private $connection;
	private $grammar;
	private $processor;

	protected function getConnection()
	{
		return $this->connection;
	}

	protected function getGrammar()
	{
		return $this->grammar;
	}

	protected function getProcessor()
	{
		return $this->processor;
	}

	protected function addColumns(array $columns)
	{
		foreach ($columns as $as => $column) {
			if (is_string($as)) {
				$this->columns[] = "{$column} as {$as}";
			} else {
				$this->columns[] = $column;
			}
		}
	}

	protected function addBinding($value, string $type = 'where')
	{
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException("Invalid binding type: {$type}.");
		}
		//
		$next = ':n' . (++self::$bindingCounter) . ':';
		//
		$this->bindings[$type][$next] = $value;
		//
		return $next;
	}

	protected function importBindingsFromSubquery(self $query, $type = 'where')
	{
		foreach ($query->getBindings('where') as $binder => $bound) {
			$this->bindings['where'][$binder] = $bound;
		}
	}

	protected function retrieveBound($binder, string $type = null)
	{
		if (!is_null($type)) {
			if (!array_key_exists($type, $this->bindings)) {
				throw new InvalidArgumentException("Invalid binding type: {$type}.");
			}
			//
			if (!array_key_exists($binder, $this->bindings[$type])) {
				throw new InvalidArgumentException("Binding not found: {$binder}.");
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
		throw new InvalidArgumentException("This binding does not exist: {$binder}.");
	}

	protected function getBindings(string $type = 'where')
	{
		if (!array_key_exists($type, $this->bindings)) {
			throw new InvalidArgumentException("Invalid binding type: {$type}.");
		}
		//
		return $binds = $this->bindings[$type];
	}

	protected function invalidOperator($operator)
	{
		return (! is_string($operator))
			|| (! in_array(strtolower($operator), $this->operators, true)); 
	}

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
		if (is_string($value)) {
			return '\'' . str_replace('\'', '', $value) . '\'';
		} elseif ($value instanceof DateTime) {
			return '\'' . $value->format('Y-m-d H:i:s.u') . '\'';
		} elseif (is_float($value)) {
			return number_format($value, 8, '.', '');
		} elseif (is_int($value)) {
			return (string)($value);
		} elseif (is_bool($value)) {
			return $value ? '1' : '0';
		} elseif (is_null($value)) {
			return 'NULL';
		}
	}

	public const REGEX_FIELDSPEC_SELECT = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)*(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))(\s+as\s+(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))?$';
	public const REGEX_FIELDSPEC_WHERE = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)+)?(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)$';

	protected function prepareValue($value)
	{
		if ($value instanceof Expression) {
			return (string) $value->getValue();
		}
		//
		if (preg_match('#^(\:n\d+\:)$#i', $value, $found)) {
			$value = $this->retrieveBound($found[1]);
		}
		//
		if (preg_match(('#' . self::REGEX_FIELDSPEC_WHERE . '#i'), $value)) {
			return $value;
		}
		//
		return $this->castValueToSqlLiteral($value);
	}

	/**
	 **/

	public function __construct(
		ConnectionInterface $connection, Grammar $grammar, Processor $processor
	) {
		$this->connection = $connection;
		$this->grammar = $grammar;
		$this->processor = $processor;
	}

	public static function new()
	{
		return new static(new Connection, new Grammar, new Processor);
	}

	public function newQuery()
	{
		return new static($this->connection, $this->grammar, $this->processor);
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

	public function selectSub($query, $as)
	{
		$callback = $query;
		$callback($query = $this->forSubQuery());
		$query = $query->toSql();
		$this->columns[] = "($query) as {$as}";
		//
		return $this;
	}

	public function from($table, string $as = null)
	{
		if ($this->isQueryable($table)) {
			return $this->fromSub($table, $as);
		}
		//
		$this->from = $as ? "{$table} as {$as}" : $table;
		//
		return $this;
	}

	public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
	{
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

	public function leftJoin($table, $first, $operator = null, $second = null, $where = false)
	{
		return $this->join($table, $first, $operator, $second, 'left', $where);
	}

	public function rightJoin($table, $first, $operator = null, $second = null, $where = false)
	{
		return $this->join($table, $first, $operator, $second, 'right', $where);
	}

	public function crossJoin($table, $first, $operator = null, $second = null, $where = false)
	{
		return $this->join($table, $first, $operator, $second, 'cross', $where);
	}

	public function joinSub(Builder $query, $as, Closure $first, $operator = null, $second = null, $type = 'inner', $where = false)
	{
		return $this->join([$as => $query], $first, $operator, $second, $type, $where);
	}

	public function leftJoinSub(Builder $query, $as, Closure $first, $operator = null, $second = null, $where = false)
	{
		return $this->joinSub($query, $as, $first, $operator, $second, 'left', $where);
	}

	public function rightJoinSub(Builder $query, $as, Closure $first, $operator = null, $second = null, $where = false)
	{
		return $this->joinSub($query, $as, $first, $operator, $second, 'right', $where);
	}

	public function crossJoinSub(Builder $query, $as, Closure $first, $operator = null, $second = null, $where = false)
	{
		return $this->joinSub($query, $as, $first, $operator, $second, 'cross', $where);
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

	public function where($column, $operator = null, $value = null, $boolean = 'and')
	{
		$boolean = (strtolower($boolean) === 'and') ? 'and' : 'or';

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

	public function andWhere($column, $operator = null, $value = null)
	{
		return $this->where($column, $operator, $value, 'and');
	}

	public function orWhere($column, $operator = null, $value = null)
	{
		return $this->where($column, $operator, $value, 'or');
	}

	public function toSql()
	{
		$sql = '';
		$headed = false;
		//
		if (isset($this->columns) && isset($this->from)) {
			$headed = true;
			$columns = [];
			//
			foreach ($this->columns as $column) {
				if ($column instanceof Closure) {
					$callback = $column;

					$callback($query = $this->forSubQuery());

					$column = $query->toSql();
				};
				//
				$columns[] = $column;
			}
			//
			$sql = ' SELECT ' . implode(', ', $this->columns)
				. ' FROM ' . $this->from
				. ' ' . implode(' ', $this->joins ?? []);
		}
		//
		$whereChain = [];
		$total = $count = count($this->wheres);
		//
		foreach ($this->wheres as $item) {
			if ($count < $total) {
				$whereChain[] = $item[4];
			}
			//
			$type = $item[0];
			//
			if ($type=='basic') {
				$val = $this->prepareValue($item[3]);
				$whereChain[] = "({$item[1]} {$item[2]} {$val})";
			} elseif ($type=='nested') {
				$whereChain[] = '(' . $item[1]->toSql() . ')';
			}
			//
			--$count;
		}
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
			$sql .= (
				($as)
					? " {$type} JOIN ({$table}) AS {$as} ON "
					: " {$type} JOIN {$table} ON "
			) . implode(' ', $whereChain);
		} else {
			$sql .= ' WHERE ' . implode(' ', $whereChain);
		}
		//

		//
		return $sql;
	}


}



