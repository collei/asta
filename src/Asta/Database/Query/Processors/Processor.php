<?php
namespace Asta\Database\Query\Processors;

use Asta\Database\Query\Builder;

/**
 *	Database query processor
 *
 *	@author	Collei de Laravel, Collei Inc. <collei@collei.com.br>
 *	@since	2022-10-22
 *
 */
class Processor
{
	/**
	 * Process the results of a select query.
	 *
	 * @param \Asta\Database\Query\Builder $query
	 * @param array $results
	 * @return array
	 */
	public function processSelect(Builder $query, $results)
	{
		return $results;
	}

	/**
	 * Process an Insert and returns the id.
	 *
	 * @param \Asta\Database\Query\Builder $query
	 * @param string $sql
	 * @param array $values
	 * @param string|null $sequence
	 * @return int
	 */
	public function processInsert(Builder $query, $sql, $values, $sequence = null)
	{
		$query->getConnection()->insert($sql, $values);
		//
		$id = $query->getConnection()->getPdo()->lastInsertId($sequence);
		//
		return is_numeric($id) ? (int)$id : $id;
	}

	/**
	 * Process the results of a column listing query.
	 *
	 * @param array $results
	 * @return array
	 */
	public function processColumnListing($results)
	{
		return $results;
	}
	
}


