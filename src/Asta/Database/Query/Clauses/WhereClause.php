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
	private $parentConnection;
	private $parentGrammar;
	private $parentProcessor;
	private $parentClass;

	private $wheres = [];

	public function __construct(Builder $parentQuery)
	{
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
