<?php
namespace Asta\Database\Query;

class InsertBuilder
{
	protected $connection;
	protected $table;
	protected $data = [];
	protected $select;

	public function __construct(string $table, ConnectionInterface $connection)
	{
		$this->table = $table;
		$this->connection = $connection;
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
	 * Returns the Builder's processor
	 *
	 * @return Processor
	 */
	public function getProcessor()
	{
		return $this->connection->getProcessor();
	}

	public function field(string $field, $value)
	{
		$this->data[$field] = $value;
		//
		return $this;
	}

	public function fields(array $fields)
	{
		$this->data = [];
		//
		foreach ($fields as $key => $value) {
			$this->data[$field] = $value;
		}
		//
		return $this;
	}

	protected function hasSelect()
	{
		return isset($this->select);
	}

	protected function getSelect($columns = ['*'])
	{
		if ($this->select) {
			return $this->select;
		}
		//
		return $this->select = (new Builder($this->getConnection()))->select($columns);
	}

	public function select($columns = ['*'])
	{
		$this->getSelect($columns);
		//
		return $this;
	}

	public function distinct()
	{
		$this->getSelect()->distinct();
		//
		return $this;
	}

	public function selectSub($query, $as)
	{
		$this->getSelect()->selectSub($query, $as);
		//
		return $this;
	}

	public function from($table, string $as = null)
	{
		$this->getSelect()->from($table, $as);
		//
		return $this;
	}

	public function fromSub(Closure $table, string $as)
	{
		$this->getSelect()->fromSub($table, $as);
		//
		return $this;
	}

	public function join(
		$table, $first, $operator = null, $second = null, $type = 'inner', $where = false
	) {
		$this->getSelect()->join($table, $first, $operator, $second, $type, $where);
		//
		return $this;
	}

	public function leftJoin(
		$table, $first, $operator = null, $second = null, $where = false
	) {
		return $this->join(
			$table, $first, $operator, $second, 'left', $where
		);
	}

	public function rightJoin(
		$table, $first, $operator = null, $second = null, $where = false
	) {
		return $this->join(
			$table, $first, $operator, $second, 'right', $where
		);
	}

	public function crossJoin(
		$table, $first, $operator = null, $second = null, $where = false
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

	public function where(
		$column, $operator = null, $value = null, $boolean = 'and'
	) {
		$this->getSelect()->where($column, $operator, $value, $boolean);
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
		$this->getSelect()->limit($count);
		//
		return $this;
	}

	public function take(int $count)
	{
		return $this->limit($count);
	}

	public function offset(int $count)
	{
		$this->getSelect()->offset($count);
		//
		return $this;
	}

	public function skip(int $count)
	{
		return $this->offset($count);
	}

	public function toSql()
	{
		$values = $this->hasSelect()
			? $this->getSelect()->getSql()
			: $this->getGrammar()->compileInsertValues($this->data);
		//
		$insert = $this->getGrammar()->compileInsertInto($this->table, array_keys($this->data));
		//
		return $insert . ' ' . $values;
	}

}

