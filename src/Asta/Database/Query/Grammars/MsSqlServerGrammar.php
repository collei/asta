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
class MsSqlServerGrammar extends Grammar
{
	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_SIMPLE = '^(\w+|\[[^\]]+\])(\.(\w+|\[[^\]]+\]))*$';

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_ALIASED = '^(\w+|\[[^\]]+\])(\.(\w+|\[[^\]]+\]))*\s+as\s+(\w+|\[[^\]]+\])$';

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_ORDER_BY_ITEM = '^(\w+|\[[^\]]+\])(\.(\w+|\[[^\]]+\]))*\s+(asc|desc)$';

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
		if (!empty($orders)) {
			$orders = ' ORDER BY ' . implode(' ', $this->planifyOrderByItems($orders));
			//
			if (! is_null($limit)) {
				$offset = $offset ?? 0;
				//
				$offset = ($offset < 0) ? 0 : $offset;
				//
				$limits = " OFFSET {$offset} ROWS  FETCH NEXT {$limit} ROWS ONLY";
			} else {
				$limits = '';
			}
		} else {
			$orders = $limits = '';
		}
		//
		return "SELECT {$columns} FROM {$table}"
			. ($joins.$wheres.$groups.$havings.$orders.$limits);
	}

}


