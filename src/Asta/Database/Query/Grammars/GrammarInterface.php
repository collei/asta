<?php
namespace Asta\Database\Query\Grammars;

use DateTimeInterface;

interface GrammarInterface
{
	/**
	 * Define whether a single leading space should be added.
	 *
	 * @param	bool	$mode
	 * @return	$this
	 */
	public function setLeadingSpaceMode(bool $mode);

	/**
	 * Define whether a single leading space should be added.
	 *
	 * @param	bool	$mode
	 * @return	$this
	 */
	public function setTrailingSpaceMode(bool $mode);

	/**
	 * Returns whether $column is a valid SQL column name.
	 *
	 * @return	bool
	 */
	public function isValidColumnName(string $column);

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
	);

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
	);

	/**
	 * Returns the regex expression for validate select field.
	 *
	 * @return	string
	 */
	public function getRegexFieldspecSelect();

	/**
	 * Returns the regex expression for validate where clause field.
	 *
	 * @return	string
	 */
	public function getRegexFieldspecWhere();

	/**
	 * Returns the regex expression for validate order by item.
	 *
	 * @return	string
	 */
	public function getRegexFieldspecOrderByItem();

	/**
	 * Wraps a column name for the SQL processor.
	 *
	 * @param	string	$column
	 * @return	string
	 */
	public function wrapColumn(string $column);

	/**
	 * Formats bool value to SQL literal.
	 *
	 * @param	bool	$value
	 * @return	string
	 */
	public function valueToSqlBoolean(bool $value);

	/**
	 * Formats integer value to SQL literal.
	 *
	 * @param	int	$value
	 * @return	string
	 */
	public function valueToSqlInt(int $value);

	/**
	 * Formats a numeric value to SQL literal in a integer or float format,
	 * depending on it having a non-zero floating portion or not.
	 *
	 * @param	float	$value
	 * @return	string
	 */
	public function valueToSqlIntOrFloat(float $value);

	/**
	 * Formats a float value to SQL float literal with the given $precision.
	 *
	 * @param	float	$value
	 * @param	int		$precision = 12
	 * @return	string
	 */
	public function valueToSqlFloat(float $value, int $precision = 12);

	/**
	 * Formats a DateTimeInterface value to SQL date literal.
	 *
	 * @param	\DateTimeInterface	$value
	 * @return	string
	 */
	public function valueToSqlDateTime(DateTimeInterface $value);

	/**
	 * Formats a string value to SQL string literal.
	 *
	 * @param	string	$value
	 * @return	string
	 */
	public function valueToSqlString(string $value);

	/**
	 * Wraps the given parameter into ( and ), trying to balance them.
	 *
	 * @param	string	$thing
	 * @return	string
	 */
	public function wrapItInParenthesis(string $thing);

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
	);

	/**
	 * Compiles the given parameters into a join clause.
	 *
	 * @param	string	$type
	 * @param	string	$table
	 * @param	string	$as = null
	 * @param	array	$whereChain = []
	 * @return	string
	 */
	public function compileJoin($type, $table, $as = null, array $wheres = []);

	/**
	 * Compiles the given array into a where clause.
	 *
	 * @param	array	$whereChain
	 * @return	string
	 */
	public function compileWhereChain(array $whereChain);

	/**
	 * Compiles the given parameters into a expression.
	 *
	 * @param	string	$first
	 * @param	string	$operator
	 * @param	string	$last
	 * @return	string
	 */
	public function compileExpression($first, $operator, $last);

	/**
	 * Compiles the given parameters into an IN expression.
	 *
	 * @param	string	$column
	 * @param	array	$listing
	 * @param	bool	$not = false
	 * @return	string
	 */
	public function compileInExpression($column, $listing, bool $not = false);

	/**
	 * Compiles the given parameters into a BETWEEN expression.
	 *
	 * @param	string	$column
	 * @param	array	$pair
	 * @param	bool	$not = false
	 * @return	string
	 */
	public function compileBetweenExpression($column, array $pair, bool $not = false);

	/**
	 * Compiles the given $subquery into a EXISTS subquery.
	 *
	 * @param	string	$subquery
	 * @param	bool	$not = false
	 * @return	string
	 */
	public function compileExists($subquery, bool $not = false);

	/**
	 * Compiles the field aliasing.
	 *
	 * @param	string	$thing
	 * @param	string	$as
	 * @return	string
	 */
	public function compileAliasing(string $thing, string $as);

	/**
	 * Compiles the ORDER BY clause.
	 *
	 * @param	array	$orderList
	 * @return	string
	 */
	public function compileOrderByClause(array $orderList);

	/**
	 * Compiles a single item for the ORDER BY clause.
	 *
	 * @param	string	$field
	 * @param	string|bool	$direction
	 * @return	string
	 */
	public function compileOrderByItem(string $field, $direction);

	/**
	 * Compiles the offset clause.
	 *
	 * @param	int		$offset
	 * @return	string
	 */
	public function compileOffsetClause(int $offset);

	/**
	 * Compiles the limit clause.
	 *
	 * @param	int		$limit
	 * @param	int		$offset = null
	 * @return	string
	 */
	public function compileLimitClause(int $limit, int $offset = null);

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
	);

	/**
	 * Compiles the given parameters into a sql insert into values instruction.
	 *
	 * @param	string	$table
	 * @param	array	$columns
	 * @param	array	$values
	 * @return	string
	 */
	public function compileInsertValues(string $table, array $columns, array $values);

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
	);

	/**
	 * Compiles the given parameters into a leading Update header.
	 *
	 * @param	string	$target
	 * @param	array	$targetExpressions
	 * @return	string
	 */
	public function compileUpdate(string $target, array $targetExpressions);

	/**
	 * Compiles the given parameters into a leading Update Join header.
	 *
	 * @param	string	$target
	 * @param	array	$targetExpressions
	 * @param	array	$compiledJoins = []
	 * @return	string
	 */
	public function compileUpdateJoin(
		string $target, array $targetExpressions, array $compiledJoins = []
	);

	/**
	 * Compiles the given parameters into a SET unit for a UPDATE statement.
	 *
	 * @param	string	$field
	 * @param	string	$expression
	 * @return	string
	 */
	public function compileUpdateExpressions(array $expressions);

	/**
	 * Compiles the given parameters into a SET unit for a UPDATE statement.
	 *
	 * @param	string	$field
	 * @param	string	$expression
	 * @return	string
	 */
	public function compileUpdateExpression(string $field, string $expression);

	/**
	 * Compiles the given parameters into a Delete without where clause.
	 *
	 * @param	string	$target
	 * @return	string
	 */
	public function compileDeleteAll(string $target);

	/**
	 * Compiles the given parameters into a sql delete instruction.
	 *
	 * @param	string	$target
	 * @param	array	$joins = []
	 * @return	string
	 */
	public function compileDelete(string $target, array $joins = []);
}
