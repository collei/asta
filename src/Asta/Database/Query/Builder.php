<?php
namespace Asta\Database\Query;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;
use Asta\Database\Processors\Processor;
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

	protected function invalidOperator($operator)
	{
		return (! is_string($operator))
			|| (! in_array(strtolower($operator), $this->operators, true)); 
	}

	protected function isBitwiseOperator($operator)
	{
		return in_array(strtolower($operator), $this->operators, true);
	}

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
		foreach ($columns as $as => $column) {
			if (is_string($as)) {
				$this->columns[] = "{$column} as {$as}";
			} else {
				$this->columns[] = $column;
			}
		}
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

	protected function isQueryable($query)
	{
		return ($query instanceof Closure)
			|| ($query instanceof self);
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

	public function joinSub(Builder $query, $as, Closure $first, $operator = null, $second = null, $type = 'inner', $where = false)
	{
		$join = JoinClause::make($this, $type, [$as => $query]);
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

	public function where($column, $operator = null, $value = null, $boolean = 'and')
	{
		if (is_array($column)) {
			foreach ($column as $name => $value) {
				$this->wheres[] = ['basic', $name, '=', $value, $boolean];
			}
			return $this;
		}
		//
		if ($column instanceof Closure && is_null($operator)) {
			$callback = $column;

			$callback($query = $this->forSubQuery()); 

			$this->wheres[] = ['nested', $query->toSql(), null, null, $boolean];
		} else {
			if (is_null($value)) {
				[$operator, $value] = ['=', $operator];
			}

			$this->wheres[] = ['basic', $column, $operator, $value, $boolean];
		}
		//
		return $this;
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
		$count = count($this->wheres);
		//
		foreach ($this->wheres as $item) {
			--$count;
			$type = $item[0];
			//
			if ($type=='basic') {
				$whereChain[] = "({$item[1]} {$item[2]} {$item[3]})";
			} elseif ($type=='nested') {
				$whereChain[] = '(' . $item[1]->toSql() . ')';
			}
			//
			if ($count > 0) {
				$whereChain[] = $item[4];
			}
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



