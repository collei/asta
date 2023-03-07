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
	public const REGEX_FIELDSPEC_SELECT = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)*(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))(\s+as\s+(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))?$';

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_WHERE = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)+)?(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)$';

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
		return 1 === preg_match('#' . static::REGEX_FIELDSPEC_WHERE . '#i', $column);
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
		return ('#'.self::REGEX_FIELDSPEC_SELECT.'#i');
	}

	/**
	 * Returns the regex expression for validate where clause field.
	 *
	 * @return	string
	 */
	public function getRegexFieldspecWhere()
	{
		return ('#'.self::REGEX_FIELDSPEC_WHERE.'#i');
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
		while (round($value, $precision) != $value) {
			++$precision;
			//
			if ($precision > 15) { break; }
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
		$type, $table, $as = null, array $whereChain = []
	) {
		return $this->getLeadingSpace() . (
			($as)
				? "{$type} JOIN ({$table}) AS {$as} ON"
				: "{$type} JOIN {$table} ON"
		) . implode(' ', $whereChain) . $this->getTrailingSpace();
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
	public function compileInExpression($column, array $listing, bool $not = false)
	{
		$in = $not ? 'NOT IN' : 'IN';
		//
		return $this->getLeadingSpace()
			. "({$column} {$in} (".implode(', ', $listing)."))"
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
	 * @return	string
	 */
	public function compileExists($subquery)
	{
		return $this->getLeadingSpace()
			. "EXISTS ({$subquery})"
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
	 * @return	string
	 */
	public function compileLimitClause(int $limit)
	{
		return $this->getLeadingSpace()
			. 'FETCH NEXT ' . $this->valueToSqlInt($limit) . ' ROWS ONLY'
			. $this->getTrailingSpace();
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
		array $destinationColumns
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
	 * @param	array	$expressions
	 * @return	string
	 */
	public function compileUpdate(
		string $target,
		array $expressions
	) {
		return 'UPDATE  ' . $target
			. ' SET ' . $this->compileUpdateExpressions($targetExpressions)
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a leading Update Join header.
	 *
	 * @param	string	$target
	 * @param	array	$expressions
	 * @param	string	$fromTable
	 * @param	array	$compiledJoins = []
	 * @return	string
	 */
	public function compileUpdateJoin(
		string $target,
		array $expressions,
		string $fromTable,
		array $compiledJoins = []
	) {
		return 'UPDATE  ' . $target
			. ' SET ' . $this->compileUpdateExpressions($targetExpressions)
			. ' FROM ' . $fromTable
			. ' ' . implode(' ', $compiledJoins)
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a leading Delete header.
	 *
	 * @param	string	$target
	 * @return	string
	 */
	public function compileDelete(
		string $target
	) {
		return 'DELETE FROM  ' . $target
			. $this->getTrailingSpace();
	}

	/**
	 * Compiles the given parameters into a leading Delete Join header.
	 *
	 * @param	string	$target
	 * @param	array	$compiledJoins = []
	 * @return	string
	 */
	public function compileDeleteJoin(
		string $target,
		array $compiledJoins = []
	) {
		return 'DELETE FROM ' . $target
			. ' ' . implode(' ', $compiledJoins)
			. $this->getTrailingSpace();
	}

}


