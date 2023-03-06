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
	private $leadingSpace = true;
	private $trailingSpace = false;

	public function setLeadingSpaceMode(bool $mode)
	{
		$this->leadingSpace = $mode;
	}

	public function setTrailingSpaceMode(bool $mode)
	{
		$this->trailingSpace = $mode;
	}

	protected function getLeadingSpace()
	{
		return $this->leadingSpace ? ' ' : '';
	}

	protected function getTrailingSpace()
	{
		return $this->trailingSpace ? ' ' : '';
	}

	public const REGEX_FIELDSPEC_SELECT = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)*(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))(\s+as\s+(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*))?$';
	public const REGEX_FIELDSPEC_WHERE = '^(((`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)\.)+)?(`[[^`]+]`|\[[^\]]+\]|[A-Za-z_]\w*)$';
	
	public function getRegexFieldspecSelect()
	{
		return ('#' . self::REGEX_FIELDSPEC_SELECT . '#i');
	}

	public function getRegexFieldspecWhere()
	{
		return ('#' . self::REGEX_FIELDSPEC_WHERE . '#i');
	}

	public function valueToSqlBoolean(bool $value)
	{
		return $value ? '1' : '0';
	}

	public function valueToSqlInt(int $value)
	{
		return (string)$value;
	}

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

	public function valueToSqlFloat(float $value, int $precision = 12)
	{
		return number_format($value, $precision, '.', '');
	}

	public function valueToSqlDateTime(DateTime $value)
	{
		return '\'' . $value->format('Y-m-d H:i:s.u') . '\'';
	}

	public function valueToSqlString(string $value)
	{
		return '\'' . str_replace('\'', '', $value) . '\'';		
	}

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

	public function compileSelect(
		array $columns, $from, array $joins = [], bool $distinct = false
	) {
		return 'SELECT ' . ($distinct ? 'DISTINCT ' : '')
			. implode(', ', $columns)
			. ' FROM ' . trim($from)
			. (empty($joins) ? '' : (' ' . implode(' ', $joins)))
			. $this->getTrailingSpace();
	}

	public function compileJoin(
		$type, $table, $as = null, array $whereChain = []
	) {
		return $this->getLeadingSpace() . (
			($as)
				? "{$type} JOIN ({$table}) AS {$as} ON"
				: "{$type} JOIN {$table} ON"
		) . implode(' ', $whereChain) . $this->getTrailingSpace();
	}

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

	public function compileExpression($first, $operator, $last)
	{
		return $this->getLeadingSpace()
			. "({$first} {$operator} {$last})"
			. $this->getTrailingSpace();
	}

	public function compileExists($subquery)
	{
		return $this->getLeadingSpace()
			. "EXISTS ({$subquery})"
			. $this->getTrailingSpace();
	}

	public function compileAliasing(string $thing, string $as)
	{
		return $this->getLeadingSpace()
			. "{$thing} AS {$as}"
			. $this->getTrailingSpace();
	}

	public function compileOffsetClause(int $limit)
	{
		return $this->getLeadingSpace()
			. 'OFFSET ' . $this->valueToSqlInt($limit) . ' ROWS'
			. $this->getTrailingSpace();
	}

	public function compileLimitClause(int $limit)
	{
		return $this->getLeadingSpace()
			. 'FETCH NEXT ' . $this->valueToSqlInt($limit) . ' ROWS ONLY'
			. $this->getTrailingSpace();
	}



}


