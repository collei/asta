<?php
namespace Asta\Database\Repository\Relations;

use Asta\Database\Repository\Model;
use Asta\Database\Query\Builder;

/**
 *	Embodies belongs-to relation.
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2021-07-xx
 */
class BelongsTo extends Relation
{
	/**
	 *	Builds and instantiates
	 *
	 *	@param	\Asta\Database\Repository\Model	$current
	 *	@param	\Asta\Database\Repository\Model	$parent
	 *	@param	string	$foreignKey	foreign key in the far table pointing to the local table
	 *	@return	void
	 */
	public function __construct(
		Model $current,
		Model $parent,
		string $foreignKey = null,
		string $localKey = null,
	) {
		$this->left = $current;
		$this->right = $parent;

		$this->inferKeys($localKey, $foreignKey);
	}

	/**
	 *	Returns the data from the relation results
	 *
	 *	@return	mixed
	 */
	protected function fetchData()
	{
		$foreign = $this->rightKey;
		$local = $this->leftKey;

		$farEntity = $this->right->getTable();

		return $this->getBuilder()->from($farEntity)
					->select('*')
					->where($local, '=', $this->left->$local)
					->execute();
	}
	
}

