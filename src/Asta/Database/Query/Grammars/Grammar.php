<?php
namespace Asta\Database\Query\Grammars;

use DateTime;
use DateTimeInterface;

/**
 *	Query grammar
 *
 *	@author	Collei de Laravel, Collei Inc. <collei@collei.com.br>
 *	@since	2022-10-22
 *
 */
class Grammar implements GrammarInterface
{
	/**
	 * @var bool
	 */
	private $leadingSpace = true;

	/**
	 * @var bool
	 */
	private $trailingSpace = false;

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_SIMPLE = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)+)?(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)$';

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_ALIASED = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)*(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))(\s+as\s+(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))?$';

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_ORDER_BY_ITEM = '^((((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)+)?(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))\s+(asc|desc)$';

	/**
	 * Define whether a single leading space should be added.
	 *
	 * @param	bool	$mode
	 * @return	$this
	 */
	public function setLeadingSpaceMode(bool $mode)
	{
		$this->leadingSpace = $mode;
		//
		return $this;
	}

	/**
	 * Define whether a single leading space should be added.
	 *
	 * @param	bool	$mode
	 * @return	$this
	 */
	public function setTrailingSpaceMode(bool $mode)
	{
		$this->trailingSpace = $mode;
		//
		return $this;
	}

	/**
	 * Returns a single space when $leadingSpace is true.
	 *
	 * @return	string
	 */
	protected function getLeadingSpace()
	{
		return $this->leadingSpace ? ' ' : '';
	}

	/**
	 * Returns a single space when $trailingSpace is true.
	 *
	 * @return	string
	 */
	protected function getTrailingSpace()
	{
		return $this->trailingSpace ? ' ' : '';
	}

	/**
	 * Returns whether $column is a valid SQL column name.
	 *
	 * @return	bool
	 */
	public function isValidColumnName(string $column)
	{
		return 1 === preg_match(
			'#'.static::REGEX_FIELDSPEC_SIMPLE.'#i', $column
		);
	}

	/**
	 * Returns whether $column is a valid SQL ORDER BY clause item.
	 *
	 * @param	string	$column
	 * @param	string	&$field = null
	 * @param	string	&$ord = null
	 * @return	bool
	 */
	public function isValidAliasedColumnName(
		string $column, string &$field = null, string &$alias = null
	) {
		$result = 1 === preg_match(
			'#'.static::REGEX_FIELDSPEC_ALIASED.'#i', $column, $matches
		);
		//
		if ($result) {
			$field = $matches[1];
			$alias = $matches[6];
		}
		//
		return $result;
	}

	/**
	 * Returns whether $column is a valid SQL ORDER BY clause item.
	 *
	 * @param	string	$column
	 * @param	string	&$field = null
	 * @param	string	&$ord = null
	 * @return	bool
	 */
	public function isValidOrderByItem(
		string $column, string &$field = null, string &$ord = null
	) {
		$result = 1 === preg_match(
			'#'.static::REGEX_FIELDSPEC_ORDER_BY_ITEM.'#i', $column, $matches
		);
		//
		if ($result) {
			$field = $matches[1];
			$ord = $matches[6];
		}
		//
		return $result;
	}

	/**
	 * Returns the regex expression for validate select field.
	 *
	 * @return	string
	 */
	public function getRegexFieldspecSelect()
	{
		return ('#'.self::REGEX_FIELDSPEC_ALIASED.'#i');
	}

	/**
	 * Returns the regex expression for validate where clause field.
	 *
	 * @return	string
	 */
	public function getRegexFieldspecWhere()
	{
		return ('#'.self::REGEX_FIELDSPEC_SIMPLE.'#i');
	}

	/**
	 * Returns the regex expression for validate order by item.
	 *
	 * @return	string
	 */
	public function getRegexFieldspecOrderByItem()
	{
		return ('#'.self::REGEX_FIELDSPEC_ORDER_BY_ITEM.'#i');
	}

	/**
	 * Wraps a column name for the SQL processor.
	 *
	 * @param	string	$column
	 * @return	string
	 */
	public function wrapColumn(string $column)
	{
		if (strpos($column, '.')) {
			$units = explode('.', $column);
			//
			$results = array_map(function ($unit) {
				return $this->wrapColumnName($unit);
			}, $units);
			//
			return implode('.', $results);
		}
		//
		return $this->wrapColumnName($column);
	}

	/**
	 * Wraps column name unit for the SQL processor.
	 *
	 * @param	string	$unit
	 * @return	string
	 */
	protected function wrapColumnName(string $unit)
	{
		return '[' . $unit . ']';
	}

	/**
	 * Formats bool value to SQL literal.
	 *
	 * @param	bool	$value
	 * @return	string
	 */
	public function valueToSqlBoolean(bool $value)
	{
		return $value ? '1' : '0';
	}

	/**
	 * Formats integer value to SQL literal.
	 *
	 * @param	int	$value
	 * @return	string
	 */
	public function valueToSqlInt(int $value)
	{
		return (string)$value;
	}

	/**
	 * Formats a numeric value to SQL literal in a integer or float format,
	 * depending on it having a non-zero floating portion or not.
	 *
	 * @param	float	$value
	 * @return	string
	 */
	public function valueToSqlIntOrFloat(float $value)
	{
		if (round($value) == $value) {
			return $this->valueToSqlInt((int)$value);
		}
		//
		$precision = 1;
		//
		while (round($value, $precision) != $value) {
			++$precision;
			//
			if ($precision > 15) {
				break;
			}
		}
		//
		return $this->valueToSqlFloat($value, $precision);
	}

	/**
	 * Formats a float value to SQL float literal with the given $precision.
	 *
	 * @param	float	$value
	 * @param	int		$precision = 12
	 * @return	string
	 */
	public function valueToSqlFloat(float $value, int $precision = 12)
	{
		return number_format($value, $precision, '.', '');
	}

	/**
	 * Formats a DateTimeInterface value to SQL date literal.
	 *
	 * @param	\DateTimeInterface	$value
	 * @return	string
	 */
	public function valueToSqlDateTime(DateTimeInterface $value)
	{
		return '\'' . $value->format('Y-m-d H:i:s.u') . '\'';
	}

	/**
	 * Formats a string value to SQL string literal.
	 *
	 * @param	string	$value
	 * @return	string
	 */
	public function valueToSqlString(string $value)
	{
		return '\'' . str_replace(["'","\'"], ["''"], $value) . '\'';		
	}

	/**
	 * Wraps the given parameter into ( and ), trying to balance them.
	 *
	 * @param	string	$thing
	 * @return	string
	 */
	public function wrapItInParenthesis(string $thing)
	{
		$par = 0;
		$expression = '(' . $thing . ')';
		$count = strlen($expression);
		//
		for ($pos = 0; $pos < $count; $pos++) {
			$ch = substr($expression, $pos, 1);
			if ('(' == $ch) {
				++$par;
			} elseif (')' == $ch) {
				--$par;
			}
		}
		//
		if ($par > 0) while ($par > 0) {
			$expression .= ')';
			--$par;
		} elseif ($par < 0) while ($par < 0) {
			$expression = '(' . $expression;
			++$par;
		}
		//
		return $expression;
	}

	/**
	 * Compiles the given parameters into a sql select instruction.
	 *
	 * @param	array	$columns
	 * @param	string	$from
	 * @param	array	$joins = []
	 * @param	bool	$distinct = false
	 * @return	string
	 */
	public function compileSelect(
		array $columns, string $from, array $joins = [], bool $distinct = false
	) {
		return 'SELECT ' . ($distinct ? 'DISTINCT ' : '')
			. implode(', ', $columns)
			. ' FROM ' . trim($from)
			. (empty($joins) ? '' : (' ' . implode(' ', $joins)))
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a join clause.
	 *
	 * @param	string	$type
	 * @param	string	$table
	 * @param	string	$as = null
	 * @param	array	$whereChain = []
	 * @return	string
	 */
	public function compileJoin(
		$type, $table, $as = null, array $wheres = []
	) {
		return $this->getLeadingSpace() . (
			($as)
				? "{$type} JOIN ({$table}) AS {$as} ON"
				: "{$type} JOIN {$table} ON"
		) . implode(' ', $wheres) . $this->getTrailingSpace();
	}

	/**
	 * Compiles the given array into a where clause.
	 *
	 * @param	array	$whereChain
	 * @return	string
	 */
	public function compileWhereChain(array $whereChain)
	{
		if (empty($whereChain)) {
			return '';
		}
		//
		return $this->getLeadingSpace()
			. 'WHERE ' . implode(' ', $whereChain)
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a expression.
	 *
	 * @param	string	$first
	 * @param	string	$operator
	 * @param	string	$last
	 * @return	string
	 */
	public function compileExpression($first, $operator, $last)
	{
		return $this->getLeadingSpace()
			. "({$first} {$operator} {$last})"
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into an IN expression.
	 *
	 * @param	string	$column
	 * @param	array	$listing
	 * @param	bool	$not = false
	 * @return	string
	 */
	public function compileInExpression($column, $listing, bool $not = false)
	{
		$in = $not ? 'NOT IN' : 'IN';
		//
		if (is_array($listing)) {
			$listing = implode(', ', $listing);
		}
		//
		return $this->getLeadingSpace()
			. "({$column} {$in} ({$listing}))"
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a BETWEEN expression.
	 *
	 * @param	string	$column
	 * @param	array	$pair
	 * @param	bool	$not = false
	 * @return	string
	 */
	public function compileBetweenExpression($column, array $pair, bool $not = false)
	{
		list($lower, $higher) = $pair;
		//
		$between = $not ? 'NOT BETWEEN' : 'BETWEEN';
		// 
		return $this->getLeadingSpace()
			. "({$column} {$between} {$lower} AND {$higher})"
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given $subquery into a EXISTS subquery.
	 *
	 * @param	string	$subquery
	 * @param	bool	$not = false
	 * @return	string
	 */
	public function compileExists($subquery, bool $not = false)
	{
		return $this->getLeadingSpace()
			. ($not ? 'NOT ' : '') . "EXISTS ({$subquery})"
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the field aliasing.
	 *
	 * @param	string	$thing
	 * @param	string	$as
	 * @return	string
	 */
	public function compileAliasing(string $thing, string $as)
	{
		return $this->getLeadingSpace()
			. "{$thing} AS {$as}"
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the ORDER BY clause.
	 *
	 * @param	array	$orderList
	 * @return	string
	 */
	public function compileOrderByClause(array $orderList)
	{
		$orders = [];
		//
		foreach ($orderList as $key => $value) {
			$orders[] = $this->compileOrderByItem($key, $value);
		}
		//
		return $this->getLeadingSpace()
			. 'ORDER BY ' . implode(', ', $orders)
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles a single item for the ORDER BY clause.
	 *
	 * @param	string	$field
	 * @param	string|bool	$direction
	 * @return	string
	 */
	public function compileOrderByItem(string $field, $direction)
	{
		if (is_bool($direction)) {
			$direction = $direction ? 'ASC' : 'DESC';
		} else {
			$direction = strcasecmp($direction, 'DESC') === 0
				? 'DESC'
				: 'ASC';
		}
		//
		return sprintf('%s %s', $field, $direction); 
	}

	/**
	 * Compiles the offset clause.
	 *
	 * @param	int		$offset
	 * @return	string
	 */
	public function compileOffsetClause(int $offset)
	{
		return $this->getLeadingSpace()
			. 'OFFSET ' . $this->valueToSqlInt($offset) . ' ROWS'
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the limit clause.
	 *
	 * @param	int		$limit
	 * @param	int		$offset = null
	 * @return	string
	 */
	public function compileLimitClause(int $limit, int $offset = null)
	{
		$offset = $offset ?? 0;
		//
		if ($limit < 1 && $offset > 0) {
			return $this->compileOffsetClause($offset);
		}
		//
		return $this->getLeadingSpace()
			. "OFFSET {$offset} ROWS  FETCH NEXT {$limit} ROWS ONLY"
			. $this->getTrailingSpace();
	}

	/**
	 * Normalize the order by field list when needed.
	 *
	 * An array will be converted if it has string keys (i.e., an associative
	 * array with column names as keys and orders as values).
	 *
	 * @param	int		$limit
	 * @param	int		$offset = null
	 * @return	string
	 */
	protected function planifyOrderByItems(array $orders)
	{
		if (empty($orders)) {
			return $orders;
		}
		//
		if (is_int(key($orders))) {
			return $orders;
		}
		//
		return array_map(
			[$this, 'compileOrderByItem'], array_keys($orders), array_values($orders)
		);
	}

	/**
	 * Compiles a whole select statement from its constituent parts.
	 *
	 * @param	string	$table
	 * @param	array	$columns
	 * @param	array	$joins = []
	 * @param	array	$wheres = []
	 * @param	array	$groups = []
	 * @param	array	$havings = []
	 * @param	array	$orders = []
	 * @param	int		$limit = null
	 * @param	int		$offset = null
	 * @return	string
	 */
	public function compileSelectStatement(
		string $table, array $columns,
		array $joins = [], array $wheres = [], array $groups = [],
		array $havings = [], array $orders = [],
		int $limit = null, int $offset = null 
	) {
		$columns = implode(', ', $columns);
		//
		$joins = empty($joins)
			? ''
			: ' ' . implode(' ', $joins);
		//
		$wheres = empty($wheres)
			? ''
			: ' WHERE ' . implode(' ', $wheres);
		//
		$groups = empty($groups)
			? ''
			: ' ORDER BY ' . implode(' ', $groups);
		//
		$havings = empty($havings)
			? ''
			: ' HAVING ' . implode(' ', $havings);
		//
		$orders = empty($orders)
			? ''
			: ' ORDER BY ' . implode(' ', $this->planifyOrderByItems($orders));
		//
		$limits = ($limit)
			? (' LIMIT ' . ($offset ? "{$offset}, " : '') . "{$limit}")
			: '';
		//
		return "SELECT {$columns} FROM {$table}"
			. ($joins.$wheres.$groups.$havings.$orders.$limits);
	}

	/**
	 * Compiles the given parameters into a sql insert into values instruction.
	 *
	 * @param	string	$table
	 * @param	array	$columns
	 * @param	array	$values
	 * @return	string
	 */
	public function compileInsertValues(string $table, array $columns, array $values)
	{
		return 'INSERT INTO  ' . $table
			. $this->wrapItInParenthesis(implode(', ', $columns))
			. ' VALUES '
			. $this->wrapItInParenthesis(implode(', ', $values))
			. ';';
	}

	/**
	 * Compiles the given parameters into a sql select instruction.
	 *
	 * @param	string	$destination
	 * @param	array	$destinationColumns
	 * @param	array	$sourceColumns
	 * @param	string	$source
	 * @param	array	$joins = []
	 * @param	bool	$distinct = false
	 * @return	string
	 */
	public function compileInsertSelect(
		string $destination,
		array $destinationColumns,
		array $sourceColumns,
		string $source,
		array $joins = [],
		bool $distinct = false
	) {
		return 'INSERT INTO  ' . $destination
			. $this->wrapItInParenthesis(implode(', ', $destinationColumns))
			. ' SELECT ' . ($distinct ? 'DISTINCT ' : '')
			. implode(', ', $sourceColumns)
			. ' FROM ' . trim($source)
			. (empty($joins) ? '' : (' ' . implode(' ', $joins)))
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a leading Update header.
	 *
	 * @param	string	$target
	 * @param	array	$targetExpressions
	 * @return	string
	 */
	public function compileUpdate(
		string $target,
		array $targetExpressions
	) {
		return 'UPDATE  ' . $target
			. ' SET ' . $this->compileUpdateExpressions($targetExpressions)
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a leading Update Join header.
	 *
	 * @param	string	$target
	 * @param	array	$targetExpressions
	 * @param	array	$compiledJoins = []
	 * @return	string
	 */
	public function compileUpdateJoin(
		string $target,
		array $targetExpressions,
		array $compiledJoins = []
	) {
		return 'UPDATE  ' . $target
			. ' SET ' . $this->compileUpdateExpressions($targetExpressions)
			. ' FROM ' . $target
			. ' ' . implode(' ', $compiledJoins)
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a SET unit for a UPDATE statement.
	 *
	 * @param	string	$field
	 * @param	string	$expression
	 * @return	string
	 */
	public function compileUpdateExpressions(array $expressions)
	{
		$items = [];
		//
		foreach ($expressions as $field => $expr) {
			$items[] = $this->compileUpdateExpression($field, $expr);
		}
		//
		return implode(',' . $this->getTrailingSpace(), $items);
	}

	/**
	 * Compiles the given parameters into a SET unit for a UPDATE statement.
	 *
	 * @param	string	$field
	 * @param	string	$expression
	 * @return	string
	 */
	public function compileUpdateExpression(string $field, string $expression)
	{
		return sprintf('%s = %s', $field, $expression);	
	}

	/**
	 * Compiles the given parameters into a Delete without where clause.
	 *
	 * @param	string	$target
	 * @return	string
	 */
	public function compileDeleteAll(string $target)
	{
		return 'DELETE FROM  ' . $target . ';';
	}

	/**
	 * Compiles the given parameters into a sql delete instruction.
	 *
	 * @param	string	$target
	 * @param	array	$joins = []
	 * @return	string
	 */
	public function compileDelete(
		string $target,
		array $joins = []
	) {
		return 'DELETE FROM ' . trim($target)
			. (empty($joins) ? '' : (' ' . implode(' ', $joins)))
			. $this->getTrailingSpace();
	}

}


