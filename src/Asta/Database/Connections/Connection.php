<?php
namespace Asta\Database\Connections;

use PDO;
use PDOException;
use PDOStatement;
use Exception;
use Throwable;
use Closure;
use DateTime;
use InvalidArgumentException;
use Asta\Database\Query\Processors\Processor;
use Asta\Database\Query\Grammars\Grammar;
use Asta\Database\Query\Grammars\GrammarInterface;
use Asta\Database\Query\Builder;
use Asta\Database\Query\InsertBuilder;
use Asta\Database\Query\UpdateBuilder;
use Asta\Database\Query\DeleteBuilder;

use Asta\Database\Box\QueryBox;
use Asta\Database\Query\DatabaseQueryException;
use Jeht\Support\Arr;

function logerror(...$info)
{
	$dt = debug_backtrace(2,2)[1] ?? debug_backtrace(2,2)[0];
	//
	$file = $dt['file'] ?? '(none)';
	$line = $dt['line'] ?? '(none)';
	$method = isset($dt['class'])
		? ($dt['class'] . ($dt['type'] ?? '-:') . $dt['function'])
		: $dt['function'];
	//
	$dumpit = '<div><b>dd</b>'
		. " (<code>$file</code>, <code>$line</code>, <code>$method</code>): <pre>"
		. print_r($info,true)
		. '</pre></div>';
	//
	echo ($dumpit);
}

/**
 *	Encapsulates the connection features and tasks
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2021-07-xx
 */
class Connection implements ConnectionInterface
{
	/**
	 *	@var array
	 */
	protected static $connectionPool = [];

	/**
	 *	@var string $name
	 */
	protected $name = null;

	/**
	 *	@var \PDOConnection $handle
	 */
	protected $handle;

	/**
	 *	@var \Asta\Database\Query\Grammars\GrammarInterface
	 */
	protected $grammar;

	/**
	 *	@var \Asta\Database\Processors\ProcessorInterface
	 */
	protected $processor;

	/**
	 *	@var bool $is_open
	 */
	protected $is_open = true;

	/**
	 *	@var array $errors
	 */
	protected $errors = [];

	/**
	 *	@var string
	 */
	protected $dsn;

	/**
	 *	@var string
	 */
	protected $database;

	/**
	 *	@var string
	 */
	protected $username;

	/**
	 *	@var string
	 */
	protected $password;

	/**
	 *	@var array
	 */
	protected $options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8';",
	];

	/**
	 *	Initializes a new instance
	 *
	 *	@param	mixed	$dsn
	 *	@param	string	$database
	 *	@param	string	$username
	 *	@param	string	$password
	 */
	public function __construct($dsn = '', string $database = '', string $username = '', string $password = '')
	{
		$this->dsn = $dsn;
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
		//
		$this->grammar = new Grammar();
		$this->processor = new Processor();
		//
		$this->name = $name = 'DBC0' . (new DateTime())->format('YmdHisu');
		//
		$this->open();
		//
		self::$connectionPool[] = $this;
	}

	/**
	 *	Finalizes this instance
	 *
	 *	@return	void
	 */
	public function __destruct()
	{
		if (is_array($this->errors)) {
			foreach ($this->errors as $error) {
				logerror('DBCE: ' . get_class($this), print_r($error, true));
			}
		}
		//
		$this->is_open = false;
		$this->handle = $this->errors = null;
		$this->dsn = $this->database = $this->username = $this->password = null;
	}

	/**
	 *	Returns a connection from the pool.
	 *
	 *	@return	\Asta\Database\Connections\ConnectionInterface
	 */
	public static function getFromPool()
	{
		if (!empty(static::$connectionPool)) {
			return static::$connectionPool[0];
		}
		//
		return null;
	}

	/**
	 *	Returns the corresponding Grammar for this Connection
	 *
	 *	@return	\Asta\Database\Query\Grammars\GrammarInterface
	 */
	public function getGrammar()
	{
		return $this->grammar;
	}

	/**
	 *	Returns the corresponding Grammar for this Connection
	 *
	 *	@return	\Asta\Database\Processors\ProcessorInterface
	 */
	public function getProcessor()
	{
		return $this->processor;
	}

	/**
	 *	Returns a select query builder for this Connection
	 *
	 *	@param	string|null	$table
	 *	@return	\Asta\Database\Query\Builder
	 */
	public function getBuilder(string $table = null)
	{
		if (!empty($table)) {
			return (new Builder($this))->from($table);
		}
		//
		return new Builder($this);
	}

	/**
	 *	Returns a Insert builder for this Connection
	 *
	 *	@return	\Asta\Database\Query\InsertBuilder
	 */
	public function getInserter(string $table)
	{
		return new InsertBuilder($this, $table);
	}

	/**
	 *	Returns a Insert builder for this Connection
	 *
	 *	@return	\Asta\Database\Query\UpdateBuilder
	 */
	public function getUpdater(string $table)
	{
		return new UpdateBuilder($this, $table);
	}

	/**
	 *	Returns a Insert builder for this Connection
	 *
	 *	@return	\Asta\Database\Query\DeleteBuilder
	 */
	public function getRemover(string $table)
	{
		return new DeleteBuilder($this, $table);
	}

	/**
	 *	Defines a Processor for this Connection
	 *
	 *	@param	\Asta\Database\Processors\ProcessorInterface	$processor
	 *	@return	void
	 */
	public function setProcessor(ProcessorInterface $processor)
	{
		$this->processor = $processor;
	}

	/**
	 *	Sets the name of the connection (if it is currently unnamed)
	 *
	 *	@param	string	$name
	 *	@return	bool
	 */
	public final function setName(string $name = null)
	{
		if (is_null($this->name)) {
			if (empty($name)) {
				$name = 'DBC1' . Str::random(28);
			}
			//
			$this->name = $name;
			//
			return true;
		}
		//
		return false;
	}

	/**
	 *	Retrieves the name of the connection
	 *
	 *	@return	string
	 */
	public final function getName()
	{
		return $this->name ?? '';
	}

	/**
	 *	Opens the connection with the parameters already set by the constructor
	 *
	 *	@return	$this
	 */
	public function open()
	{
		$this->openHandle(
			$this->dsn, $this->username, $this->password, $this->options
		);
		//
		return $this;
	}

	/**
	 *	Closes the connection
	 *
	 *	@return	$this
	 */
	public function close()
	{
		$this->closeHandle();
		//
		return $this;
	}

	/**
	 *	Performs select query
	 *
	 *	@param	string	$query
	 *	@param	array	$data = []
	 *	@return	mixed
	 */
	public function select(string $query, array $data = [])
	{
		try {
			return $this->selectQuery($query, $data);
			//
		} catch (Throwable $ex) {
			$this->processError($ex, $query, __METHOD__);
			//
			return null;
		}
	}

	/**
	 *	Performs insertion of one row
	 *
	 *	@param	string	$query
	 *	@param	array	$row
	 *	@return	mixed
	 */
	public function insert(string $query, array $row = [])
	{
		$results = 0;
		//
		try {
			$results = $this->insertQuery($query, $row);
		} catch (Exception $ex) {
			$this->processError($ex, $query, __METHOD__);
			//
			return null;
		}
		//
		return $results;
	}

	/**
	 *	Performs update
	 *
	 *	@param	string	$query
	 *	@param	array	$data
	 *	@return	mixed
	 */
	public function update(string $query, array $data)
	{
		$results = 0;

		try {
			$results = $this->updateQuery($query, $data);
			//
		} catch (Exception $ex) {
			$this->processError($ex, $query, __METHOD__ . ' PDO::prepare() ');
			//
			return null;
		}

		return $results;
	}

	/**
	 *	Performs deletion
	 *
	 *	@param	string	$query
	 *	@param	array	$data
	 *	@return	mixed
	 */
	public function delete(string $query, array $data)
	{
		$results = 0;

		try
		{
			$results = $this->deleteQuery($query, $data);
		}
		catch (Exception $ex)
		{
			$this->processError($ex, $query, __METHOD__ . ' PDO::prepare() ');
			return null;
		}

		return $results;
	}

	/**
	 *	Returns if there is an error of such $code
	 *
	 *	@return	bool
	 */
	public function hasError($code)
	{
		$code = '' . $code . '';
		return array_key_exists($code, $this->errors);
	}

	/**
	 *	Returns if there are registered errors
	 *
	 *	@return	bool
	 */
	public function hasErrors()
	{
		return count($this->errors) > 0;
	}

	/**
	 *	Returns the last registered error
	 *
	 *	@return	array|null
	 */
	public function lastError()
	{
		if ($this->hasErrors())
		{
			return $this->errors[count($this->errors) - 1];
		}
		return null;
	}

	/**
	 *	Returns the index of last registered error
	 *
	 *	@return	int
	 */
	public function lastErrorIndex()
	{
		if ($this->hasErrors())
		{
			return count($this->errors) - 1;
		}
		return 0;
	}

	/**
	 *	Performs a transaction
	 *
	 *	@param	\Closure	$bunch
	 *	@return	mixed
	 */
	public function transact(Closure $bunch)
	{
		$result = 0;

		try {
			$this->getHandle()->beginTransaction();
			$lei_before = $this->lastErrorIndex();
			$result = $bunch();
			$lei_after = $this->lastErrorIndex();
			//
			if ($lei_after > $lei_before) {
				throw new DatabaseQueryException(
					'There are errors during transaction inside transact($bunch).'
				);
			}

			$this->getHandle()->commit();			
		}
		catch (Exception $ex)
		{
			$this->errors[] = [
				'type' => get_class($ex),
				'code' => '' . $ex->getCode() . '',
				'message' => 'Error on transact($bunch): ' . $ex->getMessage(),
			];
			$this->getHandle()->rollback();
			return false;
		}

		return $result;
	}

	/**
	 *	Returns all registered errors
	 *
	 *	@return	array
	 */
	public function getErrors()
	{
		$errors = $this->errors;
		return $errors;
	}


	/**
	 *	@property	string	$name
	 *	@property	instanceof \Asta\Database\Processors\Processor $processor
	 *	@property	string	$dsn
	 *	@property	string	$database
	 *	@property	string	$username
	 *	@property	array	$options
	 *	@property	$name
	 */
	public function __get($name)
	{
		if (in_array($name, ['name','processor','grammar'])) {
			return $this->$name;
		}
		//
		if (in_array($name, ['dsn','database','username','options'])) {
			return $this->$name;
		}
	}

	/**
	 *	Register errors
	 *
	 *	@param	mixed	$type	
	 *	@param	mixed	$code		
	 *	@param	string	$message	
	 *	@return	void	
	 */
	protected function addError($type, $code, string $message)
	{
		$this->errors[] = [
			'type' => $type,
			'code' => $code,
			'message' => $message,
		];
	}

	/**
	 *	Process error and exception messages
	 *
	 *	@param	\Throwable	$ex
	 *	@param	string		$query
	 *	@param	string		$whereItOccurred
	 *	@param	array		$data
	 *	@return	void
	 */
	protected function processError(Throwable $ex, string $query, string $whereItOccurred, array $data = null)
	{
		$dt = debug_backtrace(2,2)[1] ?? debug_backtrace(2,2)[0];
		//
		$file = $dt['file'] ?? '(none)';
		$line = $dt['line'] ?? '(none)';
		$method = isset($dt['class'])
			? ($dt['class'] . ($dt['type'] ?? '-:') . $dt['function'])
			: $dt['function'];
		//
		if ($this->handle) {
			$message = $pdo_error = $this->handle->errorInfo();
			//
			$info = print_r([
				'location' => compact('file','line','method'),
				'pdo_error' => $pdo_error,
				'sql' => $query,
				'data' => ($data ?? ''),
				'exception' => get_class($ex),
				'code' => $ex->getCode(),
				'message' => $ex->getMessage(),
				'this' => $this,
			], true);
		} else {
			$message = $ex->getMessage();
			//
			$info = compact('message','ex','query','data','this');
		}
		//
		logerror('DBCE: ' . get_class($this), $whereItOccurred . ': ' . $message . ', ' . print_r($info, true));
		//
		$this->addError(get_class($ex), $ex->getCode(), $ex->getMessage());		
		$this->addError('PDO', -1, 'ST: ' . print_r($info, true));
	}

	/**
	 *	Opens the connection
	 *
	 *	@param	mixed	$dsn
	 *	@param	string	$user
	 *	@param	string	$pass
	 *	@param	array	$options
	 *	@return	void
	 */
	protected function openHandle($dsn, string $user = '', string $pass = '', array $options = [])
	{
		try {
			$this->handle = new PDO($dsn, $user, $pass, $options);
			//
			if (!is_null($this->handle)) {
				$this->is_open = true;
			}
		} catch (Exception $ex) {
			$location = 'At \''.__FILE__.'\' ('.__LINE__.', '.__METHOD__.'): ';
			//
			$this->is_open = false;
			//
			$this->addError(get_class($ex), $ex->getCode(), $location.$ex->getMessage());
		}
	}

	/**
	 *	Closes the connection
	 *
	 *	@return	void
	 */
	protected function closeHandle()
	{
		$this->handle = null;
		//
		$this->is_open = false;
	}

	/**
	 *	Closes the connection
	 *
	 *	@return	void
	 */
	protected function getHandle()
	{
		return $this->handle;
	}

	/**
	 *	Defines the connection database.
	 *
	 *	@param	string	$name
	 *	@return	$this
	 *	@throws	\InvalidArgumentException
	 */
	public function setDatabase(string $name)
	{
		if (1 !== preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*\s*$/', $name)) {
			throw new InvalidArgumentException("Invalid database name: $name.");
		}
		//
		$this->database = $name;
		//
		return $this;
	}

	/**
	 *	Returns the connection database name.
	 *
	 *	@return	string
	 */
	public function getDatabase()
	{
		return $this->database;
	}

	/**
	 *	Switch the database the connection is working upon.
	 *
	 *	@return	string
	 */
	public function switchDatabase(string $name)
	{
		$this->setDatabase($name);
		//
		if ($driver = $this->getHandle()) {
			$driver->exec('use ' . $name . ';');
		}
		//
		return $this->database;
	}

	/**
	 *	Defines the connection database.
	 *
	 *	@param	string|null	$name
	 *	@return	$this
	 *	@throws	\InvalidArgumentException
	 */
	public function useDatabase(string $name = null)
	{
		if ($name) {
			$this->setDatabase($name);
		}
		//
		if ($pdo = $this->getHandle()) {
			$pdo->exec('use ' . $this->getDatabase() . ';');
		}
		//
		return $this;
	}

	/**
	 *	Executes select query and returns the resulting rows
	 *
	 *	@param	string	$sql
	 *	@param	array	$data = []
	 *	@return	array
	 */
	protected function selectQuery(string $sql, array $data = [])
	{
		if (empty($data)) {
			return $this->executeSelectQuery($sql);
		}
		//
		try {
			$stmt = $this->getHandle()->prepare($sql);
		} catch (Throwable $ex) {
			return $this->executeSelectQuery($sql);
		}
		//
		$index = 0;
		//
		foreach ($data as $key => $value) {
			$stmt->bindValue(++$index, $value);
		}
		//
		$stmt->execute();
		$rowset = $this->resultToArray($stmt);
		$stmt->closeCursor();
		//
		return $rowset;
	}

	/**
	 *	Executes the raw select query and returns the resulting rows
	 *
	 *	@param	string	$sql
	 *	@return	array
	 */
	protected function executeSelectQuery(string $sql)
	{
		try {
			$rowset = $this->open()->useDatabase()
						->getHandle()
						->query($sql);
			//
			return $this->resultToArray($rowset);
		}  catch (Exception $ex) {
			$this->processError($ex, $sql, __METHOD__ . ' » PDO::prepare(): ', []);
		}
		//
		return null;
	}

	/**
	 *	Extracts the array result from the statement result.
	 *
	 *	@param	\PDOStatement	$rowset
	 *	@return	array
	 */
	protected function resultToArray(PDOStatement $rowset)
	{
		$result = [];
		//
		foreach ($rowset as $row) {
			$result[] = $row;
		}
		//
		return $result;
	}

	/**
	 *	Executes insert query and returns last inserted id
	 *	(may depends on the underlying db engine)
	 *
	 *	@param	string	$sql
	 *	@param	array	$data = []
	 *	@return	int
	 */
	protected function insertQuery(string $sql, array $data = [])
	{
		if (empty($data)) {
			return $this->executeInsertQuery($sql);
		}

		$stmt = null;

		try {
			$stmt = $this->getHandle()->prepare($sql);
			//
			foreach ($data as $n => $v) {
				$stmt->bindValue($n, $v);
			}
			//
		} catch (Exception $ex) {
			$this->processError($ex, $sql, __METHOD__ . ' » PDO::prepare(): ', $data);
		}

		$stmt->execute();
		$last_id = $this->getHandle()->lastInsertId();

		return $last_id;
	}

	/**
	 *	Executes the raw insert query and returns the resulting id (if any)
	 *
	 *	@param	string	$sql
	 *	@return	array
	 */
	protected function executeInsertQuery(string $sql)
	{
		try {
			return $this->open()->useDatabase()
						->getHandle()
						->exec($sql);
		}  catch (Exception $ex) {
			$this->processError($ex, $sql, __METHOD__ . ' » PDO::prepare(): ');
		}
		//
		return null;
	}

	/**
	 *	Executes update query and returns the number of affected rows (may depends on the underlying db engine)
	 *
	 *	@param	string	$sql
	 *	@param	array	$row
	 *	@return	int
	 */
	protected function updateQuery(string $sql, array $data)
	{
		try {
			$stmt = $this->getHandle()->prepare($sql);
			//
			foreach ($data as $n => $v) {
				$stmt->bindValue($n, $v);
			}
			//
		} catch (Exception $ex) {
			$this->processError($ex, $sql, __METHOD__ . ' » PDO::prepare(): ', $data);
			return 0;
		}

		$stmt->execute();
		$rows_affected = $stmt->rowCount();

		return $rows_affected;
	}

	/**
	 *	Executes deletion and returns the number of affected rows (may depends on the underlying db engine)
	 *
	 *	@param	string	$sql
	 *	@param	array	$data
	 *	@return	int
	 */
	protected function deleteQuery(string $sql, array $data = [])
	{
		if (empty($data)) {
			return $this->executeDeleteQuery($sql);
		}

		$stmt = null;

		try {
			$stmt = $this->getHandle()->prepare($sql);
			//
			foreach ($data as $n => $v) {
				$stmt->bindValue($n, $v);
			}
			//
		} catch (Exception $ex) {
			$this->processError($ex, $sql, __METHOD__ . ' » PDO::prepare(): ', $row);
			return 0;
		}

		$stmt->execute();
		$rows_affected = $stmt->rowCount();

		return $rows_affected;
	}

	/**
	 *	Executes the raw delete query and returns the number of rows affected.
	 *
	 *	@param	string	$sql
	 *	@return	array
	 */
	protected function executeDeleteQuery(string $sql)
	{
		try {
			return $this->open()->useDatabase()
						->getHandle()
						->exec($sql);
		}  catch (Exception $ex) {
			$this->processError($ex, $sql, __METHOD__ . ' » PDO::prepare(): ');
		}
		//
		return null;
	}

	/**
	 *	Run several queries in a single transaction
	 *
	 *	@param	\Closure	$bunch
	 *	@return	mixed
	 */
	protected function transactBunch(Closure $bunch)
	{
		$result = 0;

		try
		{
			$this->getHandle()->beginTransaction();
			$result = $bunch();
			$this->getHandle()->commit();			
		}
		catch (Exception $ex)
		{
			$this->getHandle()->rollback();
			//
			throw new DatabaseQueryException('There are errors during transaction inside transact($bunch).');
		}

		return $result;
	}


}

