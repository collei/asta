<?php
namespace Asta\Database\Repository;

use InvalidArgumentException;
use LogicException;

use Asta\Database\Interfaces\Repository\CasterProperties;
use Asta\Database\Connections\Connection;

use Asta\Database\DatabaseException;
use Asta\Database\Query\Builder;
use Asta\Database\Repository\Relations\OneToMany;
use Asta\Database\Repository\Relations\ManyToMany;
use Asta\Database\Repository\Relations\BelongsTo;
use Asta\Database\Interfaces\Repository\Castable;
use Asta\Database\Interfaces\Repository\CastsInboundAttributes;
use Asta\Database\Interfaces\Repository\CastsAttributes;
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
	 *	@var \Asta\Database\Connections\ConnectionInterface
	 */
	private $connection = null;

	/**
	 *	@var \Asta\Database\Query\Builder
	 */
	private $builder = null;

	/**
	 *	@var string
	 */
	protected $table;

	/**
	 *	@var string
	 */
	protected $privateKey;

	/**
	 *	@var string
	 */
	protected $keyType;

	/**
	 *	@var bool
	 */
	protected $incrementing;

	/**
	 *	@var bool
	 */
	protected $timestamps;

	/**
	 *	@var bool
	 */
	protected $wasRecentlyCreated;

	/**
	 *	@var array $attributes
	 */
	protected $attributes = [];

	/**
	 *	@var array $original
	 */
	protected $original = [];

	/**
	 *	@var array
	 */
	protected $fillable = [];

	/**
	 *	@var array
	 */
	protected $readonly = ['id'];

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
	protected $hasManyRelationsCache = [];

	/**
	 *	@var array
	 */
	protected $belongsToRelationsCache = [];

	/**
	 *	@var array
	 */
	protected $belongsToManyRelationsCache = [];

	/**
	 *	@var array
	 */
	protected $casts = [];

	/**
	 *	@var array
	 */
	protected $castCache = [];

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
	protected $dates = [];

	/**
	 *	@var string
	 */
	protected $dateFormat = 'd/m/Y H:i';

	/**
	 *	@var @static array
	 */
	protected const DATE_FORMATS = [
		'd/m/Y' => '#(0?[0-9]|[12][1-9]|3[01])\\/(0?[0-9]|1[012])\\/(\d{4})#',
		'd-m-Y' => '#(0?[0-9]|[12][1-9]|3[01])-(0?[0-9]|1[012])-(\d{4})#',
		'd.m.Y' => '#(0?[0-9]|[12][1-9]|3[01])\\.(0?[0-9]|1[012])\\.(\d{4})#',
		'Y/m/d' => '#(\d{4})\\/(0?[0-9]|1[012])\\/(0?[0-9]|[12][1-9]|3[01])#',
		'Y-m-d' => '#(\d{4})-(0?[0-9]|1[012])-(0?[0-9]|[12][1-9]|3[01])#',
		'Y.m.d' => '#(\d{4})\\.(0?[0-9]|1[012])\\.(0?[0-9]|[12][1-9]|3[01])#',
		'm/d/Y' => '#(0?[0-9]|1[012])\\/(0?[0-9]|[12][1-9]|3[01])\\/(\d{4})#',
		'm-d-Y' => '#(0?[0-9]|1[012])-(0?[0-9]|[12][1-9]|3[01])-(\d{4})#',
		'm.d.Y' => '#(0?[0-9]|1[012])\\.(0?[0-9]|[12][1-9]|3[01])\\.(\d{4})#',
	];

	/**
	 *	@var @static string
	 */
	protected const CREATED_AT = 'created_at';

	/**
	 *	@var @static string
	 */
	protected const UPDATED_AT = 'updated_at';

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
		return $this->builder = Builder::new();
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
	 * Get the table associated with the model.
	 *
	 * @return string
	 */
	public function getTable()
	{
		return $this->table ?? self::catterTableName();
	}

	/**
	 * Get the table associated with the model.
	 *
	 * @return string
	 */
	public function getEntity()
	{
		return self::catterEntityName();
	}

	/**
	 * Get the table associated with the model.
	 *
	 * @param string $table
	 * @return $this
	 */
	public function setTable(string $table)
	{
		if (! empty($table)) {
			$this->table = $table;
		}
		//
		return $this;
	}

	/**
	 * Get the name of the primary key.
	 *
	 * @return string
	 */
	public function getKey()
	{
		return $this->primaryKey ?? 'id';
	}

	/**
	 * Define the primary key field name.
	 *
	 * @param string $key
	 * @return $this
	 */
	public function setKey(string $key)
	{
		if (! empty($key)) {
			$this->primaryKey = $key;
		}
		//
		return $this;
	}

	/**
	 * Get the name of the created_at.
	 *
	 * @return string
	 */
	public function getCreatedAt()
	{
		return self::CREATED_AT;
	}

	/**
	 * Get the name of the updated_at.
	 *
	 * @return string
	 */
	public function getUpdatedAt()
	{
		return self::UPDATED_AT;
	}

	/**
	 * Tells if the record data was inserted in the current request cycle.
	 *
	 * @return bool
	 */
	public function isRecent()
	{
		return $this->wasRecentlyCreated;
	}

	/**
	 * Get the type of the primary key.
	 *
	 * @return string
	 */
	public function getKeyType()
	{
		return $this->keyType ?? 'int';
	}

	/**
	 * Tells if primary key is incrementing.
	 *
	 * @return bool
	 */
	public function isIncrementing()
	{
		return $this->incrementing;
	}

	/**
	 * Define the primary key field name.
	 *
	 * @param bool $incrementing = true
	 * @return $this
	 */
	public function setIncrementing(bool $incrementing = true)
	{
		$this->incrementing = $incrementing;
		//
		return $this;
	}

	/**
	 * Tells if the table has timestamp fields.
	 *
	 * @return bool
	 */
	public function hasTimestamps()
	{
		return $this->timestamps;
	}

	/**
	 * Define if timestamps should be enabled or not.
	 *
	 * @param bool $timestamps = true
	 * @return $this
	 */
	public function setTimestamps(bool $timestamps = true)
	{
		$this->timestamps = $timestamps;
		//
		return $this;
	}

	/**
	 * Catter the table name from the class name.
	 *
	 * @static
	 * @return string
	 */
	protected static function catterTableName()
	{
		return Str::pluralize(static::catterEntityName());
	}

	/**
	 * Catter the entity name from the class name.
	 *
	 * @static
	 * @return string
	 */
	protected static function catterEntityName()
	{
		$names = explode('\\', get_called_class());
		//
		return Str::snake(array_pop($names));
	}

	/**
	 * Catter the attribute getter name.
	 *
	 * @param string $name
	 * @return string
	 */
	protected static function catterAttributeGetter(string $name)
	{
		return 'get'.Str::studly($name).'Attribute';
	}

	/**
	 * Ask if is there any configured attribute getter.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasAttributeGetter(string $name)
	{
		return method_exists($this, $this->catterAttributeGetter($name));
	}

	/**
	 * Invoke the configured attribute getter.
	 *
	 * @param string $name
	 * @return mixed
	 */
	protected function callAttributeGetter(string $name)
	{
		$method = $this->catterAttributeGetter($name);
		//
		return $this->$method();
	}

	/**
	 * Catter the attribute setter name.
	 *
	 * @param string $name
	 * @return string
	 */
	protected static function catterAttributeSetter(string $name)
	{
		return 'set'.Str::studly($name).'Attribute';
	}

	/**
	 * Ask if is there any configured attribute getter.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasAttributeSetter(string $name)
	{
		return method_exists($this, $this->catterAttributeGetter($name));
	}

	/**
	 * Invoke the configured attribute getter.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	protected function callAttributeSetter(string $name, $value)
	{
		$method = $this->catterAttributeSetter($name);
		//
		return $this->$method($value);
	}

	/**
	 * Retrieves an attribute value.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function getAttribute(string $name)
	{
		if ($this->hasAttributeGetter($name)) {
			return $this->callAttributeGetter($name);
		}
		//
		if ($this->hasConfiguredCast($name)) {
			return $this->castValue($name, $value);
		}
		//
		return $this->attributes[$name] ?? null;
	}

	/**
	 * Defines an attribute value.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function setAttribute(string $name, $value)
	{
		if ($this->hasAttributeSetter($name)) {
			$this->callAttributeSetter($name, $value);
			//
			return;
		}
		//
		if ($this->hasConfiguredCast($name)) {
			$value = $this->castValue($name, $value);
		}
		//
		$this->attributes[$name] = $value;
	}

	/**
	 * Fills the attributes
	 *
	 * @param array $attributes
	 * @return $this
	 */
	protected function fill(array $attributes)
	{
		foreach ($attributes as $key => $value) {
			$this->setAttribute($key, $value);
		}
		//
		return $this;
	}

	/**
	 * Check if a cast rule was configured for the field
	 *
	 * @param string $name
	 * @return bool
	 */
	protected function hasConfiguredCast(string $name)
	{
		return in_array($name, $this->dates) || array_key_exists($name, $this->casts);
	}

	/**
	 * Casts values according to the set rules.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return string
	 */
	protected function castValue(string $name, $value)
	{
		if (! $value) {
			return $value;
		}
		//
		if (! $this->hasConfiguredCast($name)) {
			return $value;
		}
		//
		$caster = $this->parseCaster($name);
		//
		if (in_array($caster->type, self::$primitiveCastTypes)) {
			return $this->castPrimitiveValue($name, $value, $caster);
		}
		//
		return $this->castThroughCaster($name, $value, $caster);
	}

	/**
	 * Parse the caster string into a live anonymous object with properties.
	 *
	 * @param string $name
	 * @return \Asta\Database\Interfaces\Repository\CasterProperties|null
	 */
	protected function parseCaster(string $name)
	{
		if (! array_key_exists($name, $this->casts)) {
			return null;
		}
		//
		$caster = $this->casts[$name] . ':';
		//
		list($type, $format) = explode(':', $caster, 2);
		//
		$format = trim($format, ': 	');
		//
		return new class($name, $type, $format) implements CasterProperties {
			public $name;
			public $type;
			public $format;
			public function __construct($name, $type, $format) {
				$this->name = $name;
				$this->type = $type;
				$this->format = empty($format) ? null : $format;
			}
		};
	}

	/**
	 * Cast an attribute to a native PHP type.
	 *
	 * @param	string	$key
	 * @param	mixed	$value
	 * @param	\Asta\Database\Interfaces\Repository\CasterProperties	$caster
	 * @return	mixed
	 */
	protected function castPrimitiveValue(string $key, $value, $caster)
	{
		$castType = strtolower($caster->type);
		//
		switch ($castType) {
			case 'int':
			case 'integer':
				return (int) $value;
			case 'real':
			case 'float':
			case 'double':
				return $this->fromFloat($value);
			case 'decimal':
				return $this->asDecimal($value, $caster->format);
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
	 * Try guessing the date format. Returns null on fail.
	 *
	 * @param string $value
	 * @return string|null
	 */
	public function guessDateFormat($value)
	{
		foreach (static::DATE_FORMATS as $format => $regex) {
			if (preg_match($regex, $value)) {
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
	 *	Returns if the specified attribute exists
	 *
	 *	@return	bool
	 */
	protected final function hasKeyAttribute()
	{
		return array_key_exists($this->getKey(), $this->attributes);
	}

	/**
	 *	Retrieves attributes by name.
	 *
	 *	@param	string	$name
	 *	@return	mixed
	 */
	public function __get($name)
	{
		// Let's check if there is a defined method on such name.
		// If yes, let's call it and return its call result.
		if (method_exists($this, $name)) {
			return call_user_func_array([$this, $name], []);
		}
		//
		return $this->getAttribute($name);
	}

	/**
	 *	Sets attributes by name if they are writable.
	 *
	 *	@param	string	$name
	 *	@param	mixed	$value
	 *	@return	$this
	 *	@throws	\LogicException	if the attribute is defined as readonly
	 */
	public function __set($name, $value)
	{
		if ($name == $this->getKey() || in_array($name, $this->readonly)) {
			throw new LogicException("The field [$name] is readonly!");
		}
		//
		$this->setAttribute($name, $value);
		//
		return $this;
	}

	/**
	 *	Asks whether an attribute exists (used by isset() function)
	 *
	 *	@param	string	$name
	 *	@return	bool
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
		//
		$id_name = $this->getKey();
		//
		if (!array_key_exists($id_name, $this->attributes)) {
			$result[$id_name] = null;
		}
		//
		$result = array_merge(
			$result,
			$this->attributes, 
			$this->belongsToRelationsCache,
			$this->belongsToManyRelationsCache,
			$this->hasManyRelationsCache
		);
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
		Model::performSave($this);
	}

	/**
	 *	Removes the associated data from the database
	 *
	 *	@return	mixed
	 */
	public function delete()
	{
		Model::performDelete($this);
	}

	/**
	 *	Performs database insertion or update
	 *
	 *	@param	\Asta\Database\Repository\Model	$model
	 *	@return	mixed
	 */
	protected static function performSave(Model $model)
	{
		$data = static::retrieveAttributes($model);
		//
		if (!$model->isRecent() && $model->hasKeyAttribute()) {
			
echo '<fieldset><pre>'.__FILE__.','.__LINE__.'.'.__METHOD__.":\r\n".print_r(compact('data','model'),true).'</pre></fieldset>';

			return static::performUpdateOf($model, $data);
		}
		//
		return static::performInsertOf($model, $data);
	}

	/**
	 *	Prepares and retrieves attributes.
	 *
	 *	@param	\Asta\Database\Repository\Model	$model
	 *	@return	array
	 */
	protected static function retrieveAttributes(Model $model)
	{
		$attributes = Arr::exceptKeys($model->attributes, [
			$model->getKey(),
			$model->getCreatedAt(),
			$model->getUpdatedAt()
		]);
		//
		return Arr::rekey($attributes, function ($arrayKey) {
			return Str::toSnake($arrayKey);
		});		
	}

	/**
	 *	Performs database update.
	 *
	 *	@param	\Asta\Database\Repository\Model	$model
	 *	@param	array	$data
	 *	@return	mixed
	 */
	protected static function performUpdateOf(Model $model, array $data)
	{
		$updater = $model->getConnection()->getUpdater($model->getTable());
		//
		foreach ($data as $n => $v) {
			$updater->set($n, $v);
		}
		//
		if ($model->hasTimestamps()) {
			$updater->set($model->getUpdatedAt(), new DateTime());
		}
		//
		return $updater
				->where($model->getKey(), '=', $model->$key)
				->execute();
	}

	/**
	 *	Performs database insert.
	 *
	 *	@param	\Asta\Database\Repository\Model	$model
	 *	@param	array	$data
	 *	@return	mixed
	 */
	protected static function performInsertOf(Model $model, array $data)
	{
		$inserter = $model->getConnection()->getInserter($model->getTable());
		//
		$inserter->fields($data);
		//
		if ($model->hasTimestamps()) {
			$inserter->field($model->getCreatedAt(), new DateTime());
		}
		//
		$model->setAttribute(
			$model->getKey(), $inserter->execute()
		);
		//
		return $model;
	}

	/**
	 *	Performs data deletion
	 *
	 *	@param	\Asta\Database\Repository\Model	$model
	 *	@return	mixed
	 */
	protected static function performDelete(Model $model)
	{
		$table = $model->getTable();
		$key = $model->getKey();
		//
		if (!$model->isRecent() && $model->hasAttribute($key)) {
			$remover = $this->getConnection()->getRemover($table);
			//
			return $remover
					->where($key, '=', $model->$key)
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
	 *	@param	string	$modelClass
	 *	@return	\Asta\Database\Repository\Model[]
	 */
	protected static function fillModelList(array $rowset, string $modelClass = null)
	{
		$list = [];
		//
		$modelClass = $modelClass ?? Model::class;
		//
		foreach ($rowset as $row) {
			$list[] = $modelClass::fromRow($row);
		}
		//
		return $list;
	}

	/**
	 *	Returns a Model instance from the database $id.
	 *
	 *	@param	int	$id
	 *	@return	\Asta\Database\Repository\Model
	 */
	public static function findById(int $id)
	{
		return static::fromId($id);
	}

	/**
	 *	Returns a Model instance from the database $id
	 *
	 *	@param	int	$id
	 *	@return	\Asta\Database\Repository\Model
	 */
	public static function fromId(int $id)
	{
		$model = new static();
		$table = $model->getTable();
		$key = $model->getKey();
		//
		$data = $model->getConnection()->getBuilder()
					->from($table)
					->select('*')
					->where($key, '=', $id)
					->execute();
		//
		if (!is_null($data) && (count($data) >= 1)) {
			return static::fromRow($data[0]);
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
	 *	@return	\Asta\Database\Repository\Model|\Asta\Database\Repository\Model[]
	 */
	public static function from($criteria, int $rowsPerPage = null, int $page = null)
	{
		if (is_int($criteria) || is_numeric($criteria)) {
			return static::fromId(
				(int)(double)$criteria
			);
		}
		//
		if (is_array($criteria)) {
			$query = static::getBuilderForStatic()->select('*');
			//
			foreach ($criteria as $field => $conditions) {
				if (is_int($field) && is_array($conditions)) {
					switch (count($conditions)) {
						case 0:
							break;
						case 1:
							$query->whereNull($conditions[0]);
							break;
						case 2:
							$query->where($conditions[0], '=', $conditions[1]);
							break;
						default:
							$query->where($conditions[0], $conditions[1], $conditions[2]);
					}
				} else {
					if (is_array($conditions)) {
						$query->whereIn($field, $conditions);
					} else {
						$query->where($field, '=', $conditions);
					}
				}
			}
			//
			if ($rowsPerPage > 0) {
				$query->pageSize($rowsPerPage);
			}
			//
			if ($page > 0) {
				$query->page($page);
			}
			//
			if ($result = $query->execute()) {
				$count = count($result);
				//
				if ($count == 1) {
					return static::fromRow($result[0]);
				} elseif ($count > 1) {
					return static::fillModelList($result, static::class);
				}
			}
		}
		//
		return null;
	}

	/**
	 *	Creates a new instance of the given Model
	 *
	 *	@return	\Asta\Database\Repository\Model
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
		$result = static::getBuilderForStatic()
					->select('COUNT(*) AS [tablenumberofrows]')
					->execute();
		//
		if (empty($result)) {
			return 0;
		}
		//
		return is_object($result[0])
					? $result[0]->tablenumberofrows
					: $result[0]['tablenumberofrows'];
	}

	/**
	 *	Returns the number of records in the table associated with the given Model
	 *
	 *	@return	int
	 */
	public static function pageCount(int $pageSize)
	{
		$rowCount = static::count();
		//
		$pageSize = ($pageSize < 1) ? $rowCount : $pageSize;
		//
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
	 *	@return	\Asta\Database\Repository\Model[]
	 */
	public static function all(string ...$orderBy)
	{
		$query = Builder::new()->from(static::askTableName())->select('*');
		//
		foreach ($orderBy as $ord) {
			if ($this->validateOrderByItem($ord, $field, $direction)) {
				$query->orderBy($field, strtolower($direction) == 'desc');
			}
		}
		//
		$result = $query->execute();
		//
		if (!is_null($result)) {
			return self::fillModelList($result, static::class);
		}
		//
		return $result;
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
	 *	@return	\Asta\Database\Repository\ModelResult
	 */
	public static function paged(int $page, int $rowsPerPage = null, string ...$orderBy)
	{
		$page = ($page > 0) ? $page : 1;
		//
		$rowsPerPage = ($rowsPerPage > 0) ? $rowsPerPage : 10;
		//
		$query = static::getBuilderForStatic()
					->select('*')
					->page($page)
					->pageSize($rowsPerPage);
		//
		foreach ($orderBy as $ord) {
			if ($this->validateOrderByItem($ord, $field, $direction)) {
				$query->orderBy($field, strtolower($direction) == 'desc');
			}
		}
		//
		$query = $query->execute();
		//
		if (!is_null($query)) {
			return self::fillModelList($query, static::class);
		}
		//
		return ModelResult::fromEmpty();
	}

	/**
	 *	Creates and returns a where clause. It accepts an optional where subclause
	 *
	 *	@param	mixed	$left = null
	 *	@param	mixed	$middle = null
	 *	@param	mixed	$right = null
	 *	@return	\Asta\Database\Query\Builder
	 */
	public static function where($left = null, $middle = null, $right = null)
	{
		if ($right ?? $middle ?? $left ?? false) {
			return $this->getBuilder()->where($left, $middle, $right);
		}
		//
		return $this->getBuilder();
	}

	/**
	 *	Returns a select clause after the join performed
	 *
	 *	@param	mixed	$anotherModel
	 *	@param	string	$ownedKey = null
	 *	@return	\Asta\Database\Query\Builder
	 */
	public static function join($anotherModel, string $ownedKey = null)
	{
		if (!is_subclass_of($anotherModel, Model::class)) {
			throw new InvalidArgumentException(sprintf(
				'%s is not a subclass of %s.', $anotherModel, Model::class
			));
		}
		//
		list($me, $there) = Caller::forStatic(static::class, $anotherModel)->askTableName();
		//
		return static::getBuilderForStatic()
			->select($me . '.*')
			->join($there, static::askTableKey(), '=', $ownedKey);
	}














































































	/**
	 *	Returns a ModelResult collection of all child Models related to the current Model
	 *
	 *	@param	mixed	$related
	 *	@param	string	$foreignKey
	 *	@param	string	$localKey
	 *	@return	\Asta\Database\Repository\Model[]
	 */
	public function hasMany(
		$related, string $foreignKey = null, string $localKey = null
	) {
		$caller = debug_backtrace(2,2)[1]['function'];
		//
		if (!is_subclass_of($related, Model::class)) {
			throw new InvalidArgumentException(
				$related . ' is not a subclass of ' . Model::class . '.'
			);
		}
		//
		if (isset($this->hasManyRelationsCache[$caller])) {
			return $this->hasManyRelationsCache[$caller];
		}
		//
		$oneToMany = new OneToMany($this, new $related, $foreignKey, $localKey);
		//
		return $this->hasManyRelationsCache[$caller] = static::fillModelList(
			$results = $oneToMany->fetch(), $related
		);
	}

	/**
	 *	Returns the parent Model related to the current Model
	 *
	 *	@param	mixed	$related
	 *	@param	string	$foreignKey
	 *	@param	string	$localKey
	 *	@return	\Asta\Database\Repository\Model
	 */
	public function belongsTo(
		$related, string $foreignKey = null, string $localKey = null
	) {
		$caller = debug_backtrace(2,2)[1]['function'];
		//
		if (!is_subclass_of($related, Model::class)) {
			throw new InvalidArgumentException(
				$related . ' is not a subclass of ' . Model::class . '.'
			);
		}
		//
		if (!empty($this->belongsToRelationsCache[$caller])) {
			return $this->belongsToRelationsCache[$caller];
		}
		//
		$belongsTo = new BelongsTo($this, new $related, $foreignKey, $localKey);
		//
		return $this->belongsToRelationsCache[$caller] = $related::fromRow(
			$results = $belongsTo->fetch()
		);
	}

	/**
	 *	Returns a ModelResult collection of all parent/brother Models related to the current Model
	 *
	 *	@param	mixed	$related
	 *	@param	string	$intermediate
	 *	@param	string	$foreign
	 *	@param	string	$foreignFar
	 *	@return	\Asta\Database\Repository\Model[]
	 */
	public function belongsToMany(
		$related,
		string $intermediate = null,
		string $foreign = null,
		string $foreignFar = null
	) {
		$caller = debug_backtrace(2,2)[1]['function'];
		//
		if (!is_subclass_of($related, Model::class)) {
			throw new InvalidArgumentException(
				$related . ' is not a subclass of ' . Model::class . '.'
			);
		}
		//
		if (! empty($this->belongsToManyRelationsCache[$caller])) {
			return $this->belongsToManyRelationsCache[$caller];
		}
		//
		$manyToMany = new ManyToMany(
			$this, new $related(), $intermediate, $foreign, $foreignFar
		);
		//
		return $this->belongsToManyRelationsCache[$related] = static::fillModelList(
			$manyToMany->fetch(), $related
		);
	}

	/**
	 *	Returns the model data as Json format.
	 *
	 *	@param	int	$options = 0
	 *	@return	string
	 */
	public function toJson($options = 0)
	{
		return json_encode($this);
	}

	/**
	 *	Returns if the ORDER BY clause item is accepted by the current
	 *	database driver.
	 *
	 *	@param	string	$clauseItem
	 *	@return	bool
	 */
	public function validateOrderByItem(string $clauseItem)
	{
		return $this->getConnection()->getGrammar()->isValidOrderByItem($clauseItem);
	}


}


