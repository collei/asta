<?php
namespace Asta\Database\Query\Clauses;

use Asta\Database\Query\Builder;
use Closure;

/**
 *	The Where clause builder, fully naïve mode (never checks for real existence
 *	of any DB object for query generation).
 *
 *	@author	Collei de Laravel, Collei Inc. <collei@collei.com.br>
 *	@since	2022-10-24
 *
 */
class WhereClause extends Builder
{
	/**
	 * @var \Asta\Database\Connections\ConnectionInterface
	 */
	private $parentConnection;

	/**
	 * @var \Asta\Database\Query\Grammars\Grammar
	 */
	private $parentGrammar;

	/**
	 * @var \Asta\Database\Query\Processors\Processor
	 */
	private $parentProcessor;

	/**
	 * @var \Asta\Database\Query\Builder
	 */
	private $parentClass;

	/**
	 * @var array
	 */
	private $wheres = [];

	/**
	 * Creates a new Where clause instance.
	 *
	 * @var \Asta\Database\Query\Builder	$parentQuery
	 * @return	void
	 */
	public function __construct(Builder $parentQuery)
	{
		$this->parentClass = get_class($parentQuery);
		//
		parent::__construct(
			$this->parentConnection = $parentQuery->getConnection(),
			$this->parentGrammar = $parentQuery->getGrammar(),
			$this->parentProcessor = $parentQuery->getProcessor()
		);
	}

	/**
	 * Adds a expression to the where clause of the current query.
	 *
	 * @param	mixed	$column
	 * @param	mixed	$operator = null
	 * @param	mixed	$value = null
	 * @param	string	$boolean = 'and'
	 * @return	$this
	 */
	public function where($column, $operator = null, $value = null, $boolean = 'and')
	{
		if (is_array($column)) {
			foreach ($column as $name => $value) {
				$this->wheres[] = ['basic', $name, '=', $value, $boolean];
			}
			return $this;
		}
		//
		if ($column instanceof Closure && is_null($operator)) {
			$this->wheres[] = ['nested', ]
		}
	}
}
