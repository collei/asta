<?php
namespace Asta\Database\Repository\Relations;

use Closure;
use Asta\Database\Connections\Connection;
use Asta\Database\Connections\ConnectionInterface;

/**
 *	Embodies basic relation tasks and features
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2021-07-xx
 */
abstract class Relation
{
	/**
	 *	@var \Asta\Database\Connections\ConnectionInterface
	 */
	protected $connection = null;

	/**
	 *	@var \Asta\Database\Query\Builder
	 */
	protected $builder = null;

	/**
	 *	@var \Asta\Database\Repository\Model
	 */
	protected $left = null;

	/**
	 *	@var \Asta\Database\Repository\Model
	 */
	protected $right = null;

	/**
	 *	@var string
	 */
	protected $leftKey = '';

	/**
	 *	@var string
	 */
	protected $rightKey = '';

	/**
	 *	@var mixed
	 */
	protected $result = '';

	/**
	 *	Builds and instantiates
	 *
	 */
	public function __construct()
	{
	}

	public function __get(string $name)
	{
		if ($this->result instanceof Model) {
			if ($this->result->hasAttribute($name)) {
				return $this->result->$name;
			}
			//
			if (method_exists($this->result, $name)) {
				$arguments = func_get_args();
				//
				return $this->result->$name(...$arguments);
			}
			//
			return $this->result->$name;
		}
	}

	/**
	 * Get the connection used by the model.
	 *
	 * @return \Asta\Database\Connections\ConnectionInterface
	 */
	public function getConnection()
	{
		if (! isset($this->connection)) {
			$this->connection = Connection::getFromPool();
		}

		return $this->connection;
	}

	/**
	 * Get the active Builder instance used by the model.
	 *
	 * @return \Asta\Database\Query\Builder
	 */
	public function getBuilder()
	{
		if ($this->builder) {
			return $this->builder;
		}
		//
		return $this->builder = $this->getConnection()->getBuilder();
	}

	/**
	 *	Returns a Builder instance for a static context.
	 *
	 *	@static
	 *	@return	\Asta\Database\Query\Builder
	 */
	protected static function getBuilderForStatic()
	{
		$model = new static();
		//
		return $model->getBuilder()->from($model->getTable());
	}

	/**
	 *	Fetch the resulting data from relatrion
	 *
	 *	@param	\Closure	$transform
	 *	@return	mixed
	 */
	public function fetch(Closure $transform = null)
	{
		$this->result = $data = $this->fetchData();
		//
		if (! is_null($transform)) {
			return $this->result = $transform($data);
		}
		//
		return $data;
	}

	/**
	 *	Tries to infer the keys of the involved left and right tables
	 *
	 *	@param	string	$left
	 *	@param	string	$right
	 *	@return void	
	 */
	protected function inferKeys(string $left = null, string $right = null)
	{
		$this->leftKey = $left ?? $this->left->getKey();
		$this->rightKey = $right ?? $this->left->getEntity() . '_id';
	}

	/**
	 *	Returns the data from the relation results
	 *
	 *	@return	mixed
	 */
	protected function fetchData()
	{
		throw new RuntimeException(sprintf(
			'Class %s has not implemented the method %s', get_called_class(), __METHOD__
		));
	}

}


