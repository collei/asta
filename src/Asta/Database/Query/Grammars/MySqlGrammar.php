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
class MySqlGrammar extends Grammar
{
	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_SIMPLE = '^(\w+|`[^`]+`)(\.(\w+|`[^`]+`))*$';

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_ALIASED = '^(\w+|`[^`]+`)(\.(\w+|`[^`]+`))*\s+as\s+(\w+|`[^`]+`)$';

	/**
	 * @var bool
	 */
	public const REGEX_FIELDSPEC_ORDER_BY_ITEM = '^(\w+|`[^`]+`)(\.(\w+|`[^`]+`))*\s+(asc|desc)$';

	/**
	 * Wraps column name unit for the SQL processor.
	 *
	 * @param	string	$unit
	 * @return	string
	 */
	protected function wrapColumnName(string $unit)
	{
		return '`' . $unit . '`';
	}

}


