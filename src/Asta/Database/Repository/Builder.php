<?php
namespace Asta\Database\Repository;

use Asta\Database\Query\Builder as QueryBuilder;
use Asta\Database\Exceptions\ModelNotFoundException;

use Jeht\Support\Traits\ForwardsCalls;
use Jeht\Support\Traits\TapsValues;
use Jeht\Support\Interfaces\Arrayable;

use BadMethodCallException;

/**
 *	@mixin \Asta\Database\Query\Builder
 */ 
class Builder
{
	use ForwardsCalls;
	use TapsValues;

	protected $query;
	protected $model;
	protected $eagerLoad;

	/**
	 * The methods that should be returned from query builder.
	 *
	 * @var array
	 */
	protected $passthru = [
		'insert', 'insertOrIgnore', 'insertGetId', 'insertUsing', 'getBindings', 'toSql', 'dump', 'dd',
		'exists', 'doesntExist', 'count', 'min', 'max', 'avg', 'average', 'sum', 'getConnection', 'raw', 'getGrammar',
	];

	/**
	 * Builds a new instance.
	 *
	 * @param	\Asta\Database\Query\Builder	$query
	 * @return	void
	 */
	public function __construct(QueryBuilder $query)
	{
		$this->query = $query;
	}

	/**
	 * Gets the underlying builder.
	 *
	 * @return	\Asta\Database\Query\Builder
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * Sets the underlying builder.
	 *
	 * @param	\Asta\Database\Query\Builder	$query
	 * @return	$this
	 */
	public function setQuery(QueryBuilder $query)
	{
		$this->query = $query;
		//
		return $this;
	}

	/**
	 * Gets the related model instance.
	 *
	 * @return	\Asta\Database\Repository\Model
	 */
	public function getModel()
	{
		return $this->model;
	}

	/**
	 * Sets the related model instance.
	 *
	 * @param	\Asta\Database\Repository\Model	$model
	 * @return	$this
	 */
	public function setModel(Model $model)
	{
		$this->model = $model;
		//
		return $this;
	}

	/**
	 * Returns an instance of the related model.
	 *
	 * @param	array	$attributes = []
	 * @return	\Asta\Database\Repository\Model
	 */
	public function make(array $attributes = [])
	{
		return $this->newModelInstance($attributes);
	}

	/**
	 * Searches for records matching the given $id.
	 *
	 * @param	int|string|array|\Jeht\Support\Interfaces\Arrayable	$model
	 * @return	$this
	 */
	public function whereKey($id)
	{
		if (is_array($id) || $id instanceof Arrayable) {
			$this->query->whereIn($this->model->getQualifiedKeyName(), $id);
			//
			return $this;
		}

		if ($id !== null && $this->model->getKeyType() === 'string') {
			$id = (string) $id;
		}

		return $this->where($this->model->getQualifiedKeyName(), '=', $id);
	}

	/**
	 * Searches for records not matching the given $id.
	 *
	 * @param	int|string|array|\Jeht\Support\Interfaces\Arrayable	$model
	 * @return	$this
	 */
	public function whereKeyNot($id)
	{
		if (is_array($id) || $id instanceof Arrayable) {
			$this->query->whereNotIn($this->model->getQualifiedKeyName(), $id);
			//
			return $this;
		}

		if ($id !== null && $this->model->getKeyType() === 'string') {
			$id = (string) $id;
		}

		return $this->where($this->model->getQualifiedKeyName(), '!=', $id);
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
	public function where($column, $operator = null, $value = null, $boolean = 'and')
	{
		if ($column instanceof Closure && is_null($operator)) {
			$column($query = $this->model->newQueryWithoutRelationships());
			//
			$this->query->addNestedWhereQuery($query->getQuery(), $boolean);
		} else {
			$this->query->where(...func_get_args());
		}

		return $this;
	}

	/**
	 * Returns the first matching entity instance.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @param	string	$boolean = 'and'
	 * @return	\Asta\Database\Repository\Model|static
	 */
	public function firstWhere($column, $operator = null, $value = null, $boolean = 'and')
	{
		return $this->where($column, $operator, $value, $boolean)->first();
	}

	/**
	 * Adds a 'or where' expression to the where clause of the current query.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @return	$this
	 */
	public function orWhere($column, $operator = null, $value = null)
	{
		[$value, $operator] = $this->query->prepareValueAndOperator(
			$value, $operator, func_num_args() === 2
		);
		//
		return $this->where($column, $operator, $value, 'or');
	}

	/**
	 * Creates a collection of models from plain arrays.
	 *
	 * @param	array	$items
	 * @return	\Asta\Database\Repository\ModelCollection
	 */
	public function hydrate(array $items)
	{
		$instance = $this->newModelInstance();
		//
		return $instance->newCollection(array_map(function ($item) use ($instance) {
			return $instance->newFromBuilder($item);
		}, $items));
	}

	/**
	 * Creates a collection of models from a raw query.
	 *
	 * @param	string	$query
	 * @param	array	$bindings
	 * @return	\Asta\Database\Repository\ModelCollection
	 */
	public function fromQuery($query, $bindings = [])
	{
		return $this->hydrate(
			$this->query->getConnection()->select($query, $bindings);
		);
	}

	/**
	 * Finds a model by its primary key.
	 *
	 * @param	mixed	$id
	 * @param	array	$columns = [*]
	 * @return	\Asta\Database\Repository\ModelCollection
	 */
	public function find($id, $columns = ['*'])
	{
		if (is_array($id) || $id instanceof Arrayable) {
			return $this->findMany($id, $columns);
		}
		//
		return $this->whereKey($id)->first($columns);
	}

	/**
	 * Finds multiple models by their primary keys.
	 *
	 * @param	\Jeht\Support\Interfaces\Arrayable|array	$ids
	 * @param	array	$columns = [*]
	 * @return	\Asta\Database\Repository\ModelCollection
	 */
	public function findMany($ids, $columns = ['*'])
	{
		$ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;
		//
		if (empty($ids)) {
			return $this->model->newCollection();
		}
		//
		return $this->whereKey($ids)->get($columns);
	}

	/**
	 * Finds a model by its primary key or returns a new Model instance.
	 *
	 * @param	mixed	$id
	 * @param	array	$columns = [*]
	 * @return	\Asta\Database\Repository\Model|static
	 */
	public function findOrNew($id, $columns = ['*'])
	{
		if (! is_null($model = $this->where($id, $columns))) {
			return $model;
		}
		//
		return $this->newModelInstance();
	}

	/**
	 * Finds a model by its primary key or returns a new Model instance.
	 *
	 * @param	mixed	$id
	 * @param	array	$columns = [*]
	 * @return	\Asta\Database\Repository\Model|static
	 *
	 * @throws	\Asta\Database\Exceptions\ModelNotFoundException
	 */
	public function findOrFail($id, $columns = ['*'])
	{
		$result = $this->find($id);
		//
		$id = $id instanceof Arrayable ? $id->toArray() : $id;
		//
		if (is_array($id)) {
			if (count($result) === count($array_unique($id))) {
				return $result;
			}
		} elseif (! is_null($result)) {
			return $result;
		}
		//
		throw (new ModelNotFoundException)->setModel(
			get_class($this->model), $id
		);
	}

	/**
	 * Executes the query and gets the first result. 
	 *
	 * @param	array|string	$columns = ['*']
	 * @return	\Asta\Database\Repository\Model|object|static|null
	 */
	public function first($columns = ['*'])
	{
		return $this->take(1)->get($columns)->first();
	}

	/**
	 * Gets the first model matching the attributes or instantiate irt.
	 *
	 * @param	array	$attributes = []
	 * @param	array	$values = []
	 * @return	\Asta\Database\Repository\Model|static
	 */
	public function firstOrNew(array $attributes = [], array $values = [])
	{
		if (! is_null($model = $this->where($attributes)->first())) {
			return $model;
		}
		//
		return $this->newModelInstance($attributes + $values);
	}

	/**
	 * Gets the first model matching the attributes or instantiate irt.
	 *
	 * @param	array	$attributes = []
	 * @param	array	$values = []
	 * @return	\Asta\Database\Repository\Model|static
	 */
	public function firstOrCreate(array $attributes = [], array $values = [])
	{
		if (! is_null($model = $this->where($attributes)->first())) {
			return $model;
		}
		//
		return $this->tapValue(
			$this->newModelInstance($attributes + $values), function($model) {
				$model->save();
			}
		);
	}

	/**
	 * Gets the first model matching the attributes or instantiate irt.
	 *
	 * @param	array	$attributes = []
	 * @param	array	$values = []
	 * @return	\Asta\Database\Repository\Model|static
	 */
	public function firstOrFail(array $attributes = [], array $values = [])
	{
		if (! is_null($model = $this->where($attributes)->first())) {
			return $model;
		}
		//
		return $this->tapValue(
			$this->newModelInstance($attributes + $values), function($model) {
				$model->save();
			}
		);
	}

	/**
	 * Create or update a record matching the attributes, filling it with values.
	 *
	 * @param	array	$attributes = []
	 * @param	array	$values = []
	 * @return	\Asta\Database\Repository\Model|static
	 */
	public function updateOrCreate(array $attributes = [], array $values = [])
	{
		return $this->tapValue(
			$this->firstOrNew($attributes), function($model) use ($values) {
				$model->fill($values)->save();
			}
		);
	}

	/**
	 * Executes the query as a select statement.
	 *
	 * @param	array	$columns
	 * @return	\Asta\Database\Repository\Collection|static[]
	 */
	public function get($columns = ['*'])
	{
		$builder = clone $this;
		//
		if (count($models = $builder->getModels($columns)) > 0) {
			//$models = $builder->eagerLoadRelations($models);
		}
		//
		return $builder->getModel()->newCollection($models);
	}

	/**
	 * Gets the hydrated models without eager loading.
	 *
	 * @param	array	$columns
	 * @return	\Asta\Database\Repository\Model[]|static[]
	 */
	public function getModels($columns = ['*'])
	{
		return $this->model->hydrate(
			$this->query->get($columns)->all()
		)->all();
	}

	/**
	 * Dynamically handle calls into the query instance.
	 *
	 * @param	string	$method
	 * @param	array	$parameters
	 * @return	mixed
	 * @throws	\BadMethodCallException
	 */
	public function __call($method, $parameters)
	{
		if (in_array($method, $this->passthru)) {
			return $this->toBase()->{$method}(...$parameters);
		}
		//
		$this->forwardCallTo($this->query, $method, $parameters);
		//
		return $this;
	}

	/**
	 * Gets a base query builder instance.
	 *
	 * @return	\Asta\Database\Query\Builder
	 */
	public function toBase()
	{
		return $this->getQuery();
	}

}
