<?php
namespace Asta\Database\Repository;

use InvalidArgumentException;
use Asta\Database\DatabaseException;
use Asta\Database\Query\Builder;
use Asta\Database\Relations\OneToMany;
use Asta\Database\Relations\ManyToMany;
use Jeht\Interfaces\Support\Jsonable;
use Jeht\Support\Arr;
use Jeht\Support\Str;
use Jeht\Collections\Collection;
use Jeht\Support\Calendar\Date;

/**
 *	Encapsulates a given database Model, its attributes and methods
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2021-07-xx
 */
abstract class Model implements Jsonable
{
	/**
	 *	@var \Asta\Database\Connections\ConnectionInterface $connection
	 */
	private $connection = null;

	/**
	 *	@var \Asta\Database\Query\Builder $builder
	 */
	private $builder = null;

	/**
	 *	@var array $cache
	 */
	private $cache = [];

	/**
	 *	@var bool $is_changed
	 */
	private $is_changed = false;

	/**
	 *	@var bool $is_new
	 */
	private $is_new = true;

	/**
	 *	@var array $attributes
	 */
	protected $attributes = [];

	/**
	 *	@var array $original
	 */
	protected $original = [];

	/**
	 *	@var array $fillable
	 */
	protected $fillable = [];

	/**
	 *	@var array
	 */
	protected $guarded = ['*'];

	/**
	 *	@var @static array
	 */
	protected static $guardableColumns = [];

	/**
	 *	@var array
	 */
	protected $readonly = [];

	/**
	 *	@var array
	 */
	protected $casts = [];

	/**
	 *	@var array
	 */
	protected static $primitiveCastTypes = [
		'array',
		'bool',
		'boolean',
		'collection',
		'custom_datetime',
		'date',
		'datetime',
		'decimal',
		'double',
		'float',
		'int',
		'integer',
		'json',
		'object',
		'real',
		'string',
		'timestamp',
	];

	/**
	 *	@var array
	 */
	protected $classCastCache = [];

	/**
	 *	Keep caching of the results.
	 *
	 */
	protected $relationCache = [
		'has_many' => [],
		'belongs_to' => [],
		'belongs_to_many' => []
	];

	/**
	 *	Builds and instantiates a Model
	 *
	 *	@param	array	$attributes = []
	 *	@return	void
	 */
	public function __construct(array $attributes = [])
	{
		$this->fill($attributes);
	}

	/**
	 * Catter the table name from the class name.
	 *
	 * @return string
	 */
	public static function tableFromClassName()
	{
		$names = explode('\\', get_class());
		//
		$name = strtolower(
			preg_replace('/([A-Z][^A-Z]+)/', '_\1', array_pop($names))
		);
		//
		if ('_' === substr($name,0,1)) {
			return substr($name, 1);
		}
		//
		return $name;
	}

	/**
	 * Returns the table column as qualified with table name.
	 *
	 * @param string $column
	 * @return string
	 */
	public function qualifyColumn(string $column)
	{
		if (strpos($column, '.') > 0) {
			return $column;
		}
		//
		return $this->getTable().'.'.$column;
	}

	/**
	 * Determine if two models have the same ID and belong to the same table.
	 *
	 * @param \Asta\Database\Repository\Model|null $model
	 * @return bool
	 */
	public function is($model)
	{
		return !is_null($model)
			&& $this->getKey() === $model->getKey()
			&& $this->getTable() === $model->getTable()
			&& $this->getConnectionName() === $model->getConnectionName();
	}

	/**
	 *	Verify if $that is equals to the current instance
	 *
	 *	@param	\Asta\Database\Yanfei\Model	$that
	 *	@return	bool
	 */
	public final function equals(Model $that = null)
	{
		if (is_null($that)) {
			return false;
		}
		//
		if (!($that instanceof static)) {
			return false;
		}
		//
		if (count($this->attributes) != count($that->attributes)) {
			return false;
		}
		//
		foreach ($this->attributes as $n => $v) {
			if ($v != $that->attributes[$n]) {
				return false;
			}
		}
		//
		return true;
	}

	/**
	 *	Returns the table name from the model class name
	 *
	 *	@return	string
	 */
	private function nameFromModel()
	{
		return $this->entityNameFromModel() . 's';
	}

	/**
	 *	Calculates and returns the table name from the model class name
	 *
	 *	@return	string
	 */
	private function entityNameFromModel()
	{
		$classname = get_class($this);
		if ($pos = strrpos($classname, '\\')) {
			$classname = substr($classname, $pos + 1);
		}
		//
		return Str::toSnake($classname);
	}

	/**
	 *	Attaches a given query
	 *
	 *	@param	mixed	$builder
	 */
	protected function setSourceQuery($builder)
	{
		$this->builder = $builder;
	}

	/**
	 *	Returns the table name
	 *
	 *	@return	string
	 */
	public function getTable()
	{
		return $this->table ?? $this->nameFromModel();
	}

	/**
	 *	Returns the related attributes (sub-entites and so on)
	 *	defined as it by the user
	 *
	 *	@return	array
	 */
	public function getRelated()
	{
		return $this->related ?? [];
	}

	/**
	 *	Returns the entity name
	 *
	 *	@return	string
	 */
	public function getEntity()
	{
		return $this->entityNameFromModel();
	}

	/**
	 *	Returns the name of the primary key field
	 *
	 *	@return	string
	 */
	public function getKey()
	{
		return $this->primaryKey ?? 'id';
	}

	/**
	 *	Returns the data type of the primary key field
	 *
	 *	@return	string
	 */
	public function getKeyType()
	{
		return $this->keyType ?? 'integer';
	}

	/**
	 *	Returns the name of the created_at field
	 *
	 *	@return	string
	 */
	protected function getCreatedAt()
	{
		return $this->created_at ?? 'created_at';
	}

	/**
	 *	Returns the name of the updated_at field
	 *
	 *	@return	string
	 */
	protected function getUpdatedAt()
	{
		return $this->updated_at ?? 'updated_at';
	}

	/**
	 *	Returns whether there is an incrementing field in the model's table
	 *
	 *	@return	bool
	 */
	protected function isIncrementing()
	{
		return $this->incrementing ?? true;
	}

	/**
	 *	Returns whether $fieldName is writeable or not
	 *
	 *	@return	bool
	 */
	protected function isFillable(string $fieldName)
	{
		return in_array($fieldName, $this->fillable ?? []);
	}

	/**
	 *	Returns whether $fieldName is readonly
	 *
	 *	@return	bool
	 */
	protected function isReadonly(string $fieldName)
	{
		return in_array($fieldName, $this->readonly ?? []);
	}

	/**
	 *	Ask if $fieldName does exist for the model
	 *
	 *	@return	bool
	 */
	protected function hasField(string $fieldName)
	{
		return ($this->getKey() == $fieldName)
			|| in_array($fieldName, $this->fillable ?? [])
			|| in_array($fieldName, $this->readonly ?? []);
	}

	/**
	 *	Returns whether there are timestamp control fields in the model's table
	 *
	 *	@return	bool
	 */
	protected function hasTimestamps()
	{
		return $this->timestamps ?? false;
	}

	/**
	 *	Returns the specified attribute
	 *
	 *	@param	string	$name
	 *	@return	mixed
	 */
	protected final function getAttribute($name = null)
	{
		if (! $name) {
			return;
		}
		//
		if (
			array_key_exists($name, $this->attributes)
			|| array_key_exists($name, $this->casts)
			|| $this->hasGetMutator($name)
			|| $this->isClassCastable($name)
		) {
			return $this->getAttributeValue($name);
		}
		//
		if (method_exists(self::class, $name)) {
			return;
		}
		//
		return $this->getRelationValue($name);
	}

	protected function hasGetMutator($name)
	{
		return method_exists($this, static::getMutatorNameFor($name))
	}

	protected static function getMutatorNameFor($name)
	{
		return 'get'.Str::studly($name).'Attribute';
	}

	protected function mutateAttribute($name, $value)
	{
		$method = static::getMutatorNameFor($name);
		//
		return $this->$method($value);
	}

	public function getAttributeValue($name)
	{
		return $this->transformModelValue($name, $this->getAttributeFromArray($name));
	}

	protected function transformModelValue($name, $value)
	{
		if ($this->hasGetMutator($name)) {
			return $this->mutateAttribute($name, $value);
		}
		//
		if ($this->hasCast($name)) {
			return $this->castAttribute($name, $value);
		}
		//
		return $value;
	}

	protected function getAttributeFromArray($name)
	{
		return $this->getAttributes()[$key] ?? null;
	}

	public function getAttributes()
	{
		$this->mergeAttributesFromClassCasts();
		//
		return $this->attributes;
	}

	protected function mergeAttributesFromClassCasts()
	{
		//
	}

	protected function isClassCastable($name)
	{
		return false;
	}

	public function setRawAttributes(array $attributes, $sync = false)
	{
		$this->attributes = $attributes;
		//
		if ($sync) {
			$this->syncOriginal();
		}
		$this->classCastCache = [];
		//
		return $this;
	}

	/**
	 * Cast an attribute to a native PHP type.
	 *
	 * @param  string  $key
	 * @param  mixed  $value
	 * @return mixed
	 */
	protected function castAttribute($key, $value)
	{
		$castType = $this->getCastType($key);

		if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
			return $value;
		}

		switch ($castType) {
			case 'int':
			case 'integer':
				return (int) $value;
			case 'real':
			case 'float':
			case 'double':
				return $this->fromFloat($value);
			case 'decimal':
				return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
			case 'string':
				return (string) $value;
			case 'bool':
			case 'boolean':
				return (bool) $value;
			case 'object':
				return $this->fromJson($value, true);
			case 'array':
			case 'json':
				return $this->fromJson($value);
			case 'collection':
				return new BaseCollection($this->fromJson($value));
			case 'date':
				return $this->asDate($value);
			case 'datetime':
			case 'custom_datetime':
				return $this->asDateTime($value);
			case 'timestamp':
				return $this->asTimestamp($value);
		}

		if ($this->isClassCastable($key)) {
			return $this->getClassCastableAttributeValue($key, $value);
		}

		return $value;
	}


	/**
	 * Get the type of cast for a model attribute.
	 *
	 * @param  string  $key
	 * @return string
	 */
	protected function getCastType($key)
	{
		if ($this->isCustomDateTimeCast($this->getCasts()[$key])) {
			return 'custom_datetime';
		}

		if ($this->isDecimalCast($this->getCasts()[$key])) {
			return 'decimal';
		}

		return trim(strtolower($this->getCasts()[$key]));
	}

	/**
	 * Determine if the cast type is a custom date time cast.
	 *
	 * @param  string  $cast
	 * @return bool
	 */
	protected function isCustomDateTimeCast($cast)
	{
		return strncmp($cast, 'date:', 5) === 0 ||
			   strncmp($cast, 'datetime:', 9) === 0;
	}

	/**
	 * Determine if the cast type is a decimal cast.
	 *
	 * @param  string  $cast
	 * @return bool
	 */
	protected function isDecimalCast($cast)
	{
		return strncmp($cast, 'decimal:', 8) === 0;
	}

	/**
	 * Returns the defined casts
	 *
	 * @return array
	 */
	public function getCasts()
	{
		if ($this->getIncrementing()) {
			return array_merge(
				[$this->getKeyName() => $this->getKeyType()], $this->casts
			);
		}
		//
		return $this->casts;
	}

	/**
	 * Decodes value from json format.
	 *
	 * @param string $value
	 * @param bool $asObject = false
	 * @return mixed
	 */
	public function fromJson($value, $asObject = false)
	{
		return json_decode($value, !$asObject);
	}

	/**
	 * Casts to float.
	 *
	 * @param string $value
	 * @return float
	 */
	public function fromFloat($value)
	{
		switch ((string) $value) {
			case 'Infinity':
				return INF;
			case '-Infinity':
				return -INF;
			case 'NaN':
				return NAN;
			default:
				return (float) $value;
		}
	}

	/**
	 * Casts to a decimal string.
	 *
	 * @param string $value
	 * @param int|string $decimals
	 * @return string
	 */
	public function asDecimal($value, $decimals)
	{
		return number_format($value, $decimals, '.', '');
	}

	/**
	 * Returns a timestamp value as a DateTime instance into midnight.
	 *
	 * @param mixed $value
	 * @return \DateTimeInterface
	 */
	public function asDate($value)
	{
		return $this->asDateTime($value)->setTime(0,0);
	}

	/**
	 * Returns a timestamp value as a DateTime by trying parsing it.
	 *
	 * @param mixed $value
	 * @return \DateTimeInterface
	 */
	public function asDateTime($value)
	{
		if ($value instanceof DateTimeInterface) {
			return $value;
		}
		//
		if (is_numeric($value)) {
			return (new DateTime())->setTimestamp((int)$value);
		}
		//
		if ($format = $this->guessDateFormat($value)) {
			return DateTime::createFromFormat($format, $value);
		}
		//
		return $this->parseDate($value);
	}

	/**
	 * Try guessing the date format. Returns null on fail.
	 *
	 * @param string $value
	 * @return string|null
	 */
	public function guessDateFormat($value)
	{
		foreach (static::$dateFormats as $format => $regex) {
			if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value)) {
				return $format;
			}
		}
		//
		return null;
	}

	/**
	 * Try parsing the date. Returns null on fail.
	 *
	 * @param mixed $value
	 * @return \DateTimeInterface|null
	 */
	public function parseDate($value)
	{
		if (false !== ($timestamp = strtotime($value))) {
			return (new DateTime())->setTimestamp($timestamp);
		}
		//
		return null;
	}

	/**
	 * Returns a timestamp value by trying parsing it.
	 *
	 * @param mixed $value
	 * @return int
	 */
	public function asTimestamp($value)
	{
		return $this->asDateTime($value)->getTimestamp();
	}

	/**
	 *	Sets the value of the specified attribute
	 *
	 *	@param	string	$name
	 *	@param	mixed	$value
	 *	@return	void
	 */
	protected final function setAttribute(string $name, $value)
	{
		if ($name == $this->getKey()) {
			$this->is_new = false;
		}
		//
		if (array_key_exists($name, $this->attributes)) {
			if ($this->attributes[$name] instanceof \DateTime) {
				$cal = Date::toDateObject($value);
				if (!is_null($cal)) {
					$this->attributes[$name]
						->setDate($cal->year, $cal->month, $cal->day)
						->setTime($cal->hour, $cal->minute, $cal->second);
				}
			} elseif ($this->attributes[$name] instanceof Date) {
				$cal = Date::toDateObject($value);
				if (!is_null($cal)) {
					$this->attributes[$name] = $cal;
				}
			} else {
				$this->attributes[$name] = $value;
			}
		} else {
			$this->attributes[$name] = $value;
		}
	}

	/**
	 *	Returns if the specified attribute exists
	 *
	 *	@param	string	$name
	 *	@return	bool
	 */
	protected final function hasAttribute(string $name)
	{
		return array_key_exists($name, $this->attributes);
	}

	/**
	 *	Returns if the model was created from zero or not
	 *
	 *	@return	bool
	 */
	protected final function isNew()
	{
		return $this->is_new;
	}

	/**
	 *	Returns values by calling no-argument methods as attributes
	 *	and also retrieves attributes.
	 */
	public function __get($name)
	{
		if (method_exists($this, $name)) {
			if (!isset($this->cache[$name])) {
				$this->cache[$name] = $this->$name();
			}
			//
			return $this->cache[$name];
		}
		//
		return $this->getAttribute($name);
	}

	/**
	 *	Sets attributes when they exist
	 */
	public function __set($name, $value)
	{
		if ($name != $this->getKey()) {
			$this->setAttribute($name, $value);
		}
	}

	/**
	 *	Asks whether an attribute exists (used by isset() function)
	 */
	public function __isset(string $name)
	{
		return $this->hasAttribute($name);
	}

	/**
	 *	Used internally by PHP debug functions.
	 *	Helps making results more clear and concise. 
	 */
	public function __debugInfo()
	{
		$result = [];
		$id_name = $this->getKey();
		//
		if (!array_key_exists($id_name, $this->attributes)) {
			$result[$id_name] = null;
		}
		//
		foreach ($this->attributes as $n => $v) {
			$result[$n] = $v;
		}
		//
		foreach ($this->cache as $n => $v) {
			$result['cached:' . $n] = $v;
		}
		//
		return $result;
	}

	/**
	 *	Send changes to the database
	 *
	 *	@return	void
	 */
	public function save()
	{
		return Model::insertOrUpdate($this);
	}

	/**
	 *	Removes the associated data from the database
	 *
	 *	@return	mixed
	 */
	public function remove()
	{
		return Model::delete($this);
	}

	/**
	 *	A convenient mode for using with Model::from([field => 'value']) queries
	 *	that may return either Model or ModelResult instances.
	 *
	 *	e.g.: get the first person of list
	 *
	 *	$workers = Employee::from(['city' => 'New York']);
	 *	$first = $workers->firstResult();
	 *
	 *	@return	instanceof Model
	 */
	public function firstResult()
	{
		return $this;
	}

	/**
	 *	Performs database insertion or update
	 *
	 *	@param	\Asta\Database\Yanfei\Model	$model
	 *	@return	mixed
	 */
	protected static function insertOrUpdate(Model $model)
	{
		$table = $model->getTable();
		$key = $model->getKey();
		$timeCreated = $model->getCreatedAt();
		$timeUpdated = $model->getUpdatedAt();
		$data = Arr::rekey(
			Arr::exceptKeys($model->attributes, [ $key, $timeCreated, $timeUpdated ]),
			function ($arrayKey) { return Str::toSnake($arrayKey); }
		);
		//
		if (!$model->isNew() && $model->hasAttribute($key)) {
			$updater = DB::update($table);
			//
			foreach ($data as $n => $v) {
				$updater->set($n, $v);
			}
			//
			return $updater->where()
				->is($key, $model->$key)
				->execute();
		} else {
			if ($model->hasTimestamps()) {
				$data[$model->getUpdatedAt()] = ':updated_at'; 
			}
			//
			$model->setAttribute(
				$key, DB::into($table)->insert($data)->done()
			);
			//
			return $model;
		}
	}

	/**
	 *	Performs data deletion
	 *
	 *	@param	\Asta\Database\Yanfei\Model	$model
	 *	@return	mixed
	 */
	protected static function delete(Model $model)
	{
		$table = $model->getTable();
		$key = $model->getKey();
		//
		if (!$model->isNew() && $model->hasAttribute($key)) {
			$eraser = DB::delete($table);
			return $eraser->where()
				->is($key, $model->$key)
				->execute();
		}
		//
		return false;
	}

	/**
	 *	Returns the name of the table
	 *
	 *	@return	string
	 */
	protected static function askTableName()
	{
		return (new static())->getTable();
	}

	/**
	 *	Returns the name of the table key
	 *
	 *	@return	string
	 */
	protected static function askTableKey()
	{
		return (new static())->getKey();
	}

	/**
	 *	Returns the data as a specific or generic Model instance
	 *
	 *	@return	instanceof \Asta\Database\Repository\Model
	 */
	protected static function fromRow(array $row)
	{
		$piece = null;
		//
		if (static::class !== self::class) {
			$piece = new static($row);
		} else {
			$piece = new NullModel($row);
			//
			$piece->fill($row);
		}
		//
		return $piece;
	}

	/**
	 *	Returns the data as a list of specific or generic Model instances
	 *
	 *	@param	array	$rowset
	 *	@param	bool	$asCollection
	 *	@param	string	$collectionType
	 *	@return	\Asta\Database\Repository\Model
	 */
	protected static function fillModelList(
		array $rowset, bool $asCollection = false,
		string $collectionType = Model::class
	) {
		$list = [];
		//
		foreach ($rowset as $row) {
			$list[] = $collectionType::fromRow($row);
		}
		//
		if ($asCollection) {
			return ModelResult::fromTypedArray($list, $collectionType, false);
		}
		//
		return $list;
	}

	protected static function tableFromModel($model)
	{
		return (new $model)->askTableName();
	}

	/**
	 *	Returns a Model instance from the database $id.
	 *
	 *	@param	int	$id
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	public static function findById(int $id)
	{
		return static::fromId($id);
	}

	/**
	 *	Returns a Model instance from the database $id
	 *
	 *	@param	int	$id
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	public static function fromId(int $id)
	{
		$model = new static();
		$key = $model->getKey();
		$data = DB::from($model->getTable())
					->select('*')
					->where()->is($key, $id)
					->gather();
		//
		if (!is_null($data)) {
			if (count($data) >= 1) {
				return static::fillModel($data[0]);
			}
		}
		//
		return null;
	}

	/**
	 *	Returns a Model instance - or a collection of Model instances -
	 *	from specific data 
	 *
	 *	@param	mixed	$data	int index of the record/query fields to be matched
	 *	@param	int		$rowsPerPage	number of results per page
	 *	@param	int		$page	which page to query
	 *	@return	\Asta\Database\Yanfei\Model|\Asta\Database\Yanfei\ModelResult
	 */
	public static function from(
		$data, int $rowsPerPage = null, int $page = null
	) {
		if (is_int($data) || is_numeric($data)) {
			return static::fromId((int)(double)$data);
		} else {
			$first = true;
			$query = DB::from(static::askTableName())
						->select('*')
						->pageSize($rowsPerPage)
						->page($page)
						->where();
			//
			foreach ($data as $n => $v) {
				if (!$first) {
					$query->and();
				}
				$query->is($n, $v);
			}
			//
			$data = $query->gather();
			if (!is_null($data)) {
				$count = count($data);
				if ($count == 1) {
					return static::fillModel($data[0]);
				} elseif ($count > 1) {
					return static::fillModelList($data, true, static::class);
				}
			}
			//
			return null;
		}
	}

	/**
	 *	Creates a new instance of the given Model
	 *
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	public static function new()
	{
		return new static();
	}

	/**
	 *	Returns the number of records in the table associated with the given Model
	 *
	 *	@return	int
	 */
	public static function count()
	{
		$info = DB::from(static::askTableName())
					->select('COUNT(*) AS [numberofrows]')
					->gather(true);
		//
		return $info[0]->numberofrows;
	}

	/**
	 *	Returns the number of records in the table associated with the given Model
	 *
	 *	@return	int
	 */
	public static function pageCount(int $pageSize)
	{
		$rowCount = static::count();
		$pageSize = ($pageSize < 1) ? $rowCount : $pageSize;
		$pages = \intdiv($rowCount, $pageSize);
		//
		if (($rowCount % $pageSize) > 0) {
			++$pages;
		}
		//
		return $pages;
	}

	/**
	 *	Returns all database rows as a collection of Model instances.
	 *
	 *	Order by may be issued as follows:
	 *		Person::all('name asc', 'date_birth desc')
	 *
	 *	@param	string	...$orderBy
	 *	@return	\Asta\Database\Yanfei\ModelResult
	 */
	public static function all(string ...$orderBy)
	{
		$query = Builder::new()->from(static::askTableName())->select('*');
		//
		foreach ($orderBy as $ord) {
			$elements = '';
			//
			if (
				preg_match('/^(\s*(\w+(\.\w+)*)\s+(asc|desc)\s*)$/i', $ord, $elements)
			) {
				$query->orderBy(
					$elements[2],
					strtolower($elements[4] ?? '') == 'desc'
				);
			}
		}
		//
		echo '<div>'.__FILE__.','.__LINE__.','.__METHOD__.': $query->gather() not implemented: @todo implement it.</div>';
		//$query = $query->gather();
		return $query->toSql();
		//
//		if (!is_null($query)) {
//			return self::fillModelList($query, true, static::class);
//		}
		//
//		return ModelResult::fromEmpty();
	}

	/**
	 *	Returns some database rows (amounts based on pagination)
	 *	as a collection of Model instances.
	 *
	 *	Order by may be issued as follows:
	 *		Person::paged(1, 10, 'name asc', 'date_birth desc')
	 *
	 *	@param	int	$page
	 *	@param	int	$rowsPerPage
	 *	@param	string	...$orderBy
	 *	@return	\Asta\Database\Yanfei\ModelResult
	 */
	public static function paged(int $page, int $rowsPerPage = null, string ...$orderBy)
	{
		$page = ($page > 0) ? $page : 1;
		$rowsPerPage = $rowsPerPage ?? 10;
		$rowsPerPage = ($rowsPerPage > 0) ? $rowsPerPage : 10;
		//
		$query = DB::from(static::askTableName())
					->select('*')
					->page($page)
					->pageSize($rowsPerPage);
		//
		foreach ($orderBy as $ord) {
			$elements = '';
			//
			if (
				preg_match('/^(\s*(\w+(\.\w+)*)\s+(asc|desc)\s*)$/i', $ord, $elements)
			) {
				$query->orderBy(
					$elements[2],
					strtolower($elements[4] ?? '') == 'desc'
				);
			}
		}
		//
		$query = $query->gather();
		//
		if (!is_null($query)) {
			return self::fillModelList($query, true, static::class);
		}
		//
		return ModelResult::fromEmpty();
	}

	/**
	 * Returns the model's query Builder
	 *
	 * @return \Asta\Database\Query\Builder
	 */
	protected function getBuilder()
	{
		if ($this->builder) {
			return $this->builder;
		}
		//
		return $this->builder = Builder::new()->from($this->getTable());
	}

	/**
	 *	Creates and returns a where clause. It accepts an optional where subclause
	 *
	 *	@param	mixed	$left = null
	 *	@param	mixed	$middle = null
	 *	@param	mixed	$right = null
	 *	@return	\Asta\Database\Query\Clauses\Where
	 */
	public function where($left = null, $middle = null, $right = null)
	{
		if ($left ?? $middle ?? $right) {
			return $this->getBuilder()->where($left, $middle, $right);
		}
		//
		return $this->getBuilder();
	}

	/**
	 *	Returns a select clause after the join performed
	 *
	 *	@return	\Asta\Database\Query\Select
	 */
	public static function join($anotherModel, string $ownedKey = null)
	{
		if (!is_subclass_of($anotherModel, Model::class)) {
			throw new InvalidArgumentException(
				$anotherModel . ' is not a subclass of ' . Model::class . '.'
			);
		}
		//
		$me = static::askTableName();
		$there = $anotherModel::askTableName();
		//
		return $this->getBuilder()
			->select($me . '.*')
			->join($there, static::askTableKey(), '=', $ownedKey);
	}

	/**
	 *	Returns a ModelResult collection of all child Models related to the current Model
	 *
	 *	@param	mixed	$relatedModelClass
	 *	@param	string	$foreignKey
	 *	@param	string	$localKey
	 *	@return	\Asta\Database\Yanfei\ModelResult
	 */
	public function hasMany(
		$relatedModelClass,
		string $foreignKey = null, string $localKey = null
	) {
		if (!is_subclass_of($relatedModelClass, Model::class)) {
			throw new InvalidArgumentException(
				$relatedModelClass . ' is not a subclass of ' . Model::class . '.'
			);
		}
		//
		if (!isset($this->relationCache['has_many'][$relatedModelClass])) {
			$oneToMany = new OneToMany(
				$this, new $relatedModelClass, $foreignKey, $localKey
			);
			//
			$results = $oneToMany->fetch();
			//
			$this->relationCache['has_many'][$relatedModelClass] =
				static::fillModelList($results, true, $relatedModelClass);
		}
		//
		return $this->relationCache['has_many'][$relatedModelClass];
	}

	/**
	 *	Returns the parent Model related to the current Model
	 *
	 *	@param	mixed	$relatedModelClass
	 *	@param	string	$localForeign
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	public function belongsTo($relatedModelClass, string $localForeign = null)
	{
		if (!is_subclass_of($relatedModelClass, Model::class)) {
			throw new InvalidArgumentException(
				$relatedModelClass . ' is not a subclass of ' . Model::class . '.'
			);
		}
		//
		if (!isset($this->relationCache['belongs_to'][$relatedModelClass])) {
			$localForeign = $localForeign ?? ((new $relatedModelClass)->getEntity() . '_id');
			$localForeign = Str::toCamel($localForeign);
			$localForeignId = $this->$localForeign;
			//
			$this->relationCache['belongs_to'][$relatedModelClass] =
				$relatedModelClass::fromId($localForeignId);
		}
		//
		return $this->relationCache['belongs_to'][$relatedModelClass];
	}

	/**
	 *	Returns a ModelResult collection of all parent/brother Models related to the current Model
	 *
	 *	@param	mixed	$relatedModelClass
	 *	@param	string	$intermediate
	 *	@param	string	$foreignNear
	 *	@param	string	$foreignFar
	 *	@return	\Asta\Database\Yanfei\ModelResult
	 */
	public function belongsToMany(
		$relatedModelClass,
		string $intermediate = null,
		string $foreignNear = null,
		string $foreignFar = null
	) {
		if (!is_subclass_of($relatedModelClass, Model::class)) {
			throw new InvalidArgumentException(
				$relatedModelClass . ' is not a subclass of ' . Model::class . '.'
			);
		}
		//
		if (
			!isset($this->relationCache['belongs_to_many'][$relatedModelClass])
		) {
			$manyToMany = new ManyToMany(
				$this,
				new $relatedModelClass(),
				$intermediate,
				$foreignNear,
				$foreignFar
			);
			//
			$this->relationCache['belongs_to_many'][$relatedModelClass] = 
				static::fillModelList(
					$manyToMany->fetch(), true, 	$relatedModelClass
				);
		}
		//
		return $this->relationCache['belongs_to_many'][$relatedModelClass];
	}

	/**
	 *	Fills all the attributes.
	 *
	 *	@param	array	$attributes = []
	 *	@return	void
	 */
	protected function fill(array $attributes)
	{
		$totallyGuarded = $this->totallyGuarded();

		foreach ($this->fillableFromArray($attributes) as $key => $value) {
			$key = $this->removeTableFromKey($key);

			// The developers may choose to place some attributes in the "fillable" array
			// which means only those attributes may be set through mass assignment to
			// the model, and all others will just get ignored for security reasons.
			if ($this->isFillable($key)) {
				$this->setAttribute($key, $value);
			} elseif ($totallyGuarded) {
				throw new MassAssignmentException(sprintf(
					'Add [%s] to fillable property to allow mass assignment on [%s].',
					$key, get_class($this)
				));
			}
		}

		return $this;
	}

	/**
	 * Determine if the given key is guarded.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function isGuarded($key)
	{
		if (empty($this->getGuarded())) {
			return false;
		}

		return $this->getGuarded() == ['*'] ||
			   ! empty(preg_grep('/^'.preg_quote($key).'$/i', $this->getGuarded())) ||
			   ! $this->isGuardableColumn($key);
	}

	/**
	 * Determine if the given column is a valid, guardable column.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	protected function isGuardableColumn($key)
	{
		if (! isset(static::$guardableColumns[get_class($this)])) {
			static::$guardableColumns[get_class($this)] = $this->getConnection()
						->getSchemaBuilder()
						->getColumnListing($this->getTable());
		}

		return in_array($key, static::$guardableColumns[get_class($this)]);
	}

	/**
	 * Determine if the model is totally guarded.
	 *
	 * @return bool
	 */
	public function totallyGuarded()
	{
		return count($this->getFillable()) === 0 && $this->getGuarded() == ['*'];
	}

	/**
	 * Get the fillable attributes from a given array.
	 *
	 * @param array $attributes
	 * @return array
	 */
	protected function fillableFromArray(array $attributes)
	{
		$fillables = $this->getFillable();
		//
		if (count($fillables) > 0 && ! static::$unguarded) {
			return array_intersect_key($attributes, array_flip($fillables));
		}
		//
		return $attributes;
	}

	/**
	 * Get the fillable attributes for the model.
	 *
	 * @return array
	 */
	public function getFillable()
	{
		return $this->fillable;
	}

	/**
	 * Set the fillable attributes for the model.
	 *
	 * @param array $fillable
	 * @return $this
	 */
	public function fillable(array $fillable)
	{
		$this->fillable = $fillable;
		//
		return $this;
	}

	/**
	 * Get the guarded attributes for the model.
	 *
	 * @return array
	 */
	public function getGuarded()
	{
		return $this->guarded;
	}











	/**
	 *	Syncs attribute values.
	 *
	 *	@return	$this
	 */
	protected function syncOriginal()
	{
		$this->original = array_merge($this->original, $this->attributes);
		//
		return $this;
	}

	/**
	 *	Retrieves the entity (and related ones) as JSON string
	 *
	 *	@param	string	...$except	fields and relations to exclude from result
	 *	@return	string
	 */
	public function asJson(string ...$except)
	{
		$fields = [];
		//
		foreach ($this->attributes as $n => $v) {
			if (!in_array($n, $except, true)) {
				$fields[$n] = $v;
			}
		}
		//
		$metafields = $this->getRelated();
		foreach ($metafields as $n => $v) {
			$proof = $n;
			$callee = $v;
			$child = [];
			//
			if (is_numeric($n)) {
				$proof = $v;
			}
			//
			if (!in_array($proof, $except, true)) {
				$child = method_exists($this, $callee)
					? ($this->{$callee}())
					: ($this->$callee);
				//
				$jsonable = (
					is_subclass_of($child, Model::class) ||
					is_subclass_of($child, ModelResult::class)
				);
				//
				$fields[$proof] = $jsonable
					? json_decode($child->asJson())
					: $child;
			}
		}
		//
		return json_encode($fields);
	}

	/**
	 *	converts the object data to Json string
	 *
	 *	@return	string
	 */
	public function toJson($options = 0)
	{
		return $this->asJson();
	}

}


