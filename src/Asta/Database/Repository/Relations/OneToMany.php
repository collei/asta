<?php
namespace Asta\Database\Repository\Relations;

use Asta\Database\Repository\Model;
use Asta\Database\Query\Builder;

/**
 *	Embodies one-to-many relation
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2021-07-xx
 */
class OneToMany extends Relation
{
	/**
	 *	Builds and instantiates
	 *
	 *	@param	\Asta\Database\Repository\Model	$near
	 *	@param	\Asta\Database\Repository\Model	$far
	 *	@param	string	$foreignKey	foreign key in the far table pointing to the local table
	 *	@param	string	$localKey	local table key
	 *	@return	void
	 */
	public function __construct(
		Model $near,
		Model $far,
		string $foreignKey = null,
		string $localKey = null
	) {
		$this->left = $near;
		$this->right = $far;

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
					->where($foreign, '=', $this->left->$local)
					->execute();
	}
	
}


