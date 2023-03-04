<?php
namespace Asta\Database\Yanfei;

use InvalidArgumentException;
use Asta\Database\DatabaseException;
use Asta\Database\Meta\DS;
use Asta\Database\Meta\Table;
use Asta\Database\Yanfei\Model;
use Asta\Database\Query\DB;
use Asta\Database\Query\Select;
use Asta\Database\Query\Clauses\Where;
use Asta\Support\Arr;
use Asta\Support\Str;

/**
 *	Encapsulates associative models
 *
 *	@author	alarido <alarido.su@gmail.com>
 *	@since	2021-06-xx
 */
abstract class AssociativeModel extends Model
{
	/**
	 *	Returns the first associated model, if any
	 *
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	protected function first()
	{
		$list = $this->associates ?? [];
		//
		if (count($list) < 1) {
			throw new DatabaseException(
				'Undefined first associate for the associative model '
					. get_class($this)
			);
		}
		//
		return $list[0];
	}

	/**
	 *	Returns the second associated model, if any
	 *
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	protected function second()
	{
		$list = $this->associates ?? [];
		//
		if (count($list) < 2) {
			throw new DatabaseException(
				'Undefined second associate for the associative model '
					. get_class($this)
			);
		}
		//
		return $list[1];
	}

	/**
	 *	Returns the third associated model, if any
	 *
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	protected function third()
	{
		$list = $this->associates ?? [];
		//
		if (count($list) < 3) {
			throw new DatabaseException(
				'Undefined third associate for the associative model '
					. get_class($this)
			);
		}
		//
		return $list[2];
	}

	/**
	 *	Returns the ($index-1)-th associated model, if any
	 *
	 *	@return	\Asta\Database\Yanfei\Model
	 */
	protected function further(int $index)
	{
		$list = $this->associates ?? [];
		//
		if (count($list) < $index) {
			throw new DatabaseException(
				'Undefined associate #'	. $index
					. ' for the associative model ' . get_class($this)
			);
		}
		//
		return $list[$index - 1];
	}

}


