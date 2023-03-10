<?php
namespace Asta\Database\Query;

use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;
use Closure;

class UpdateBuilder extends Builder
{
	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var array
	 */
	protected $values = [];

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
			$this->table = $table;
		}
	}

	/**
	 * Adds the update target.
	 *
	 * @param	string	$table
	 * @return	$this
	 */
	public function table(string $table)
	{
		$this->table = $table;
		//
		return $this;
	}

	/**
	 * Sets one or more values.
	 *
	 * @param	string|array	$field
	 * @param	mixed	$value
	 * @return	$this
	 */
	public function set($field, $value = null)
	{
		if (is_array($field)) {
			foreach ($field as $name => $value) {
				$this->set($name, $value);
			}
			//
			return $this;
		}
		//
		if (array_key_exists($field, $this->values)) {
			$binding = $this->values[$field];
			//
			$this->removeBinding($binding);
		}
		//
		if ($value instanceof Expression) {
			$this->values[$field] = $value->getValue();
			//
			return $this;
		}
		//
		$this->values[$field] = $this->addBinding($value, 'update');
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
		$sql = is_array($this->joins)
			? $this->getGrammar()->compileUpdateJoin(
				$this->table,
				$this->values,
				$this->joins
			)
			: $this->getGrammar()->compileUpdate(
				$this->table,
				$this->values
			);
		//
		$whereChain = $this->wheresToChain($this->wheres);
		//
		$sql .= $this->getGrammar()->compileWhereChain($whereChain);
		//
		return $sql;
	}

	/**
	 * Execute the update query.
	 *
	 * @return	int|false
	 */
	public function execute()
	{
		return $this->getConnection()->update($this->toSql(), $this->values());
	}
}

