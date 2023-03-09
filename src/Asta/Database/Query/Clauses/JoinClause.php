<?php
namespace Asta\Database\Query\Clauses;

use Asta\Database\Query\Builder;
use Asta\Database\Query\Expression;
use Closure;

/**
 *	The query builder, fully naïve mode (never checks for real existence
 *	of any DB object for query generation).
 *
 *	@author	Collei de Laravel, Collei Inc. <collei@collei.com.br>
 *	@since	2022-10-22
 *
 */
class JoinClause extends Builder
{
	protected $type;
	protected $table;
	private $parentConnection;
	private $parentGrammar;
	private $parentProcessor;
	private $parentClass;

	/**
	 * Creates a new instance.
	 *
	 * @param	\Asta\Database\Query\Builder	$parentQuery
	 * @param	string	$type
	 * @param	mixed	$table
	 * @return	void
	 */
	public function __construct(Builder $parentQuery, $type, $table)
	{
		$this->type = $type;
		$this->table = $table;
		$this->parentClass = get_class($parentQuery);
		$this->parentConnection = $parentQuery->getConnection();
		$this->parentGrammar = $parentQuery->getGrammar();
		$this->parentProcessor = $parentQuery->getConnection()->getProcessor();
		//
		parent::__construct(
			$this->parentConnection,
			$this->parentGrammar,
			$this->parentProcessor
		);
	}

	/**
	 * Creates a new instance.
	 *
	 * @static
	 * @param	\Asta\Database\Query\Builder	$parentQuery
	 * @param	string	$type
	 * @param	mixed	$table
	 * @return	static
	 */
	public static function make(Builder $parentQuery, $type, $table)
	{
		return new static($parentQuery, $type, $table);
	}

	/**
	 * Adds a join condition.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @param	string	$boolean = 'and'
	 * @return	$this
	 */
	public function on($column, $operator = null, $second = null, $boolean = 'and')
	{
		if (is_null($second)) {
			list($operator, $second) = array('=', $operator);
		}
		//
		$this->where($column, $operator, new Expression($second), $boolean);
		//
		return $this;
	}

	/**
	 * Adds a 'or join' condition.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$second = null
	 * @return	$this
	 */
	public function orOn($column, $operator = null, $second = null)
	{
		return $this->on($column, $operator, $second, 'or');
	}

}

