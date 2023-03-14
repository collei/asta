<?php
namespace Asta\Database\Repository;

use Jeht\Collections\Collection;
use Jeht\Support\Interfaces\Arrayable;
use Jeht\Support\Arr;
use Closure;
use LogicException;

class ModelCollection extends Collection
{
	/**
	 * Determine if a key exists in the collection.
	 *
	 * @param	mixed	$key
	 * @param	mixed	$default = null
	 * @return	\Jeht\Database\Repository\Model|static|null
	 */
	public function find($key, $default = null)
	{
		if ($key instanceof Model) {
			$key = $key->getKey();
		}
		//
		if ($key instanceof Arrayable) {
			$key = $key->toArray();
		}
		//
		if (is_array($key)) {
			if ($this->isEmpty()) {
				return new static;
			}
			//
			return $this->whereIn($this->first()->getKeyName(), $key);
		}
		//
		return Arr::first($this->items, function ($model) use ($key) {
			return $model->getKey() == $key;
		}, $default);
	}

	/**
	 * Determine if a key exists in the collection.
	 *
	 * @param	mixed	$key
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @return	bool
	 */
	public function contains($key, $operator = null, $value = null)
	{
		if (func_num_args() > 1 || $this->useAsCallable($key)) {
			return parent::contains(...func_get_args());
		}
		//
		if ($key instanceof Model) {
			return parent::contains(function ($model) use ($key) {
				return $model->is($key);
			});
		}
		//
		return parent::contains(function ($model) use ($key) {
			return $model->getKey() == $key;
		});
	}

	/**
	 * Get the array of primary keys.
	 *
	 * @return	array
	 */
	public function modelKeys()
	{
		return array_map(function ($model) {
			return $model->getKey();
		}, $this->items);
	}

	/**
	 * Merge the collection with the given items.
	 *
	 * @param	\ArrayAccess|array	$items
	 * @return	static
	 */
	public function merge($items)
	{
		$dictionary = $this->getDictionary();
		//
		foreach ($items as $item) {
			$dictionary[$item->getKey()] = $item; 
		}
		//
		return new static(array_values($dictionary));
	}

	/**
	 * Run a map over each of the items.
	 *
	 * @param	callable	$callback
	 * @return	static
	 */
	public function map(callable $callback)
	{
		$result = parent::map($callback);
		//
		return $result->contains(function ($item) {
			return ! $item instanceof Model;
		}) ? $result->toBase() : $result;
	}

	/**
	 * Run an associative map over each of the items.
	 *
	 * The callback should return an associative array with a single
	 * key / value pair.
	 *
	 * @param	callable	$callback
	 * @return	static
	 */
	public function mapWithKeys(callable $callback)
	{
		$result = parent::mapWithKeys($callback);
		//
		return $result->contains(function ($item) {
			return ! $item instanceof Model;
		}) ? $result->toBase() : $result;
	}

	/**
	 * Reloads a fresh model instance from the database for all the entities.
	 *
	 * @param  string|array  $with
	 * @return \Jeht\Support\Collection|static
	 */
	public function fresh($with = [])
	{
		if ($this->isEmpty()) {
			return new static();
		}
		//
		$model = $this->first();
		//
		$freshModels = $model->newQueryWithoutScopes()
			->with(is_string($with) ? func_get_args() : $with)
			->whereIn($model->getKeyName(), $this->modelKeys())
			->get()
			->getDictionary();
		//
		return $this->map(function ($model) use ($freshModels) {
			return $model->exists
				&& isset($freshModels[$model->getKey()])
						? $freshModels[$model->getKey()]
						: null; 
		});
	}

	/**
	 * Diff the collection with the given items.
	 *
	 * @param  \ArrayAccess|array  $items
	 * @return static
	 */
	public function diff($items)
	{
		$diff = new static;
		//
		$dictionary = $this->getDictionary($items);
		//
		foreach ($this->items as $item) {
			if (! isset($dictionary[$item->getKey()])) {
				$diff->add($item);
			}
		}
		//
		return $diff;
	}

	/**
	 * Intersect the collection with the given items.
	 *
	 * @param  \ArrayAccess|array  $items
	 * @return static
	 */
	public function intersect($items)
	{
		$intersect = new static;

		if (empty($items)) {
			return $intersect;
		}

		$dictionary = $this->getDictionary($items);

		foreach ($this->items as $item) {
			if (isset($dictionary[$item->getKey()])) {
				$intersect->add($item);
			}
		}

		return $intersect;
	}

	/**
	 * Return only unique items from the collection.
	 *
	 * @param  string|callable|null  $key
	 * @param  bool  $strict
	 * @return static
	 */
	public function unique($key = null, $strict = false)
	{
		if (! is_null($key)) {
			return parent::unique($key, $strict);
		}

		return new static(array_values($this->getDictionary()));
	}

	/**
	 * Returns only the models from the collection with the specified keys.
	 *
	 * @param  mixed  $keys
	 * @return static
	 */
	public function only($keys)
	{
		if (is_null($keys)) {
			return new static($this->items);
		}

		$dictionary = Arr::only($this->getDictionary(), $keys);

		return new static(array_values($dictionary));
	}

	/**
	 * Returns all models in the collection except the models with specified keys.
	 *
	 * @param  mixed  $keys
	 * @return static
	 */
	public function except($keys)
	{
		$dictionary = Arr::except($this->getDictionary(), $keys);

		return new static(array_values($dictionary));
	}

	/**
	 * Append an attribute across the entire collection.
	 *
	 * @param  array|string  $attributes
	 * @return $this
	 */
	public function append($attributes)
	{
		return $this->each->append($attributes);
	}

	/**
	 * Get a dictionary keyed by primary keys.
	 *
	 * @param  \ArrayAccess|array|null  $items
	 * @return array
	 */
	public function getDictionary($items = null)
	{
		$items = is_null($items) ? $this->items : $items;

		$dictionary = [];

		foreach ($items as $value) {
			$dictionary[$value->getKey()] = $value;
		}

		return $dictionary;
	}

	/**
	 * The following methods are intercepted to always return base collections.
	 */

	/**
	 * Get an array with the values of a given key.
	 *
	 * @param  string|array  $value
	 * @param  string|null  $key
	 * @return \Jeht\Support\Collection
	 */
	public function pluck($value, $key = null)
	{
		return $this->toBase()->pluck($value, $key);
	}

	/**
	 * Get the keys of the collection items.
	 *
	 * @return \Jeht\Support\Collection
	 */
	public function keys()
	{
		return $this->toBase()->keys();
	}

	/**
	 * Zip the collection together with one or more arrays.
	 *
	 * @param  mixed  ...$items
	 * @return \Jeht\Support\Collection
	 */
	public function zip($items)
	{
		return $this->toBase()->zip(...func_get_args());
	}

	/**
	 * Collapse the collection of items into a single array.
	 *
	 * @return \Jeht\Support\Collection
	 */
	public function collapse()
	{
		return $this->toBase()->collapse();
	}

	/**
	 * Get a flattened array of the items in the collection.
	 *
	 * @param  int  $depth
	 * @return \Jeht\Support\Collection
	 */
	public function flatten($depth = INF)
	{
		return $this->toBase()->flatten($depth);
	}

	/**
	 * Flip the items in the collection.
	 *
	 * @return \Jeht\Support\Collection
	 */
	public function flip()
	{
		return $this->toBase()->flip();
	}

	/**
	 * Pad collection to the specified length with a value.
	 *
	 * @param  int  $size
	 * @param  mixed  $value
	 * @return \Jeht\Support\Collection
	 */
	public function pad($size, $value)
	{
		return $this->toBase()->pad($size, $value);
	}

	/**
	 * Get the comparison function to detect duplicates.
	 *
	 * @param  bool  $strict
	 * @return \Closure
	 */
	protected function duplicateComparator($strict)
	{
		return function ($a, $b) {
			return $a->is($b);
		};
	}

	/**
	 * Get the Repository query builder from the collection.
	 *
	 * @return \Jeht\Database\Repository\Builder
	 *
	 * @throws \LogicException
	 */
	public function toQuery()
	{
		$model = $this->first();

		if (! $model) {
			throw new LogicException('Unable to create query for empty collection.');
		}

		$class = get_class($model);

		if ($this->filter(function ($model) use ($class) {
			return ! $model instanceof $class;
		})->isNotEmpty()) {
			throw new LogicException('Unable to create query for collection with mixed types.');
		}

		return $model->newModelQuery()->whereKey($this->modelKeys());
	}

}

