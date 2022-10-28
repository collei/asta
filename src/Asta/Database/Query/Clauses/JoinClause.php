<?php
namespace Asta\Database\Query\Clauses;

use Asta\Database\Query\Builder;
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

	public function __construct(Builder $parentQuery, $type, $table)
	{
		$this->type = $type;
		$this->table = $table;
		$this->parentClass = get_class($parentQuery);
		$this->parentConnection = $parentQuery->getConnection();
		$this->parentGrammar = $parentQuery->getGrammar();
		$this->parentProcessor = $parentQuery->getProcessor();
		//
		parent::__construct(
			$this->parentConnection,
			$this->parentGrammar,
			$this->parentProcessor
		);
	}

	public static function make(Builder $parentQuery, $type, $table)
	{
		return new static($parentQuery, $type, $table);
	}

	public function on($column, $operator = null, $second = null, $boolean = 'and')
	{
		$this->where($column, $operator, $second, $boolean);
		//
		return $this;
	}

	public function orOn($column, $operator = null, $second = null)
	{
		return $this->on($column, $operator, $second, 'or');
	}

}
