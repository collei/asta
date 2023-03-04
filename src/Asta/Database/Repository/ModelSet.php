<?php
namespace Asta\Database\Yanfei;

use InvalidArgumentException;
use Closure;
use Asta\Database\DatabaseException;
use Asta\Database\Meta\DS;
use Asta\Database\Meta\Table;
use Asta\Database\Yanfei\Model;
use Asta\Database\Query\DB;
use Asta\Database\Query\Select;
use Asta\Database\Query\Clauses\Where;
use Asta\Support\Collections\TypedCollection;
use Asta\Support\Arr;
use Asta\Support\Str;

/**
 *	Encapsulates a set of related models in a single object instance
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2022-04-26
 */
class ModelSet
{
	/**
	 *	@var array $models
	 */
	private $models = [];

	/**
	 *	Obtains the name of the given $model instance
	 *
	 *	@param	mixed	$model
	 *	@return	string
	 */
	private static function nameFrom($model)
	{
		$className = $model;
		//
		if (is_object($model)) {
			$className = get_class($model);
		}
		//
		if ($pos = strrpos($className, '\\')) {
			$className = substr($className, $pos + 1);
		}
		//
		return $className;
	}

	/**
	 *	Initializes a new instance...
	 *
	 *	@return	void
	 */
	public function __construct()
	{
	}

	/**
	 *	Appends model instances to a brand new set.
	 *
	 *	@param	\Asta\Database\Yanfei\Model	...$instances
	 *	@return	\Asta\Database\Yanfei\ModelSet
	 */
	public static function with(Model ...$instances)
	{
		$that = new static();
		//
		foreach ($instances as $instance) {
			$that->models[static::nameFrom($instance)] = $instance;
		}
		//
		return $that;
	}

	/**
	 *	Returns the corresponding instance of the $name model
	 *
	 *	@param	\Asta\Database\Yanfei\Model	...$instances
	 *	@return	\Asta\Database\Yanfei\ModelSet
	 */
	public function __get($name)
	{
		if (array_key_exists($name, $this->models)) {
			return $this->models[$name];
		}
		//
		return null;
	}

}


