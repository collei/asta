<?php
namespace Asta\Database\Repository\Relations;

use Asta\Database\Repository\Model;
use Asta\Database\Query\Builder;
use Jeht\Support\Arr;

/**
 *	Embodies many-to-many relation.
 *
 *	@author alarido <alarido.su@gmail.com>
 *	@since 2021-07-xx
 */
class ManyToMany extends Relation
{
	/**
	 *	@var string $associative
	 */
	private $associative = null;

	/**
	 *	Tries to infer the keys of the involved left and right tables
	 *
	 *	@param	string	$left
	 *	@param	string	$right
	 *	@return void	
	 */
	protected function inferKeys(string $left = null, string $right = null)
	{
		$this->leftKey = $left ?? $this->left->getTable() . '_id';
		$this->rightKey = $right ?? $this->right->getTable() . '_id';
	}

	/**
	 *	Builds and instantiates
	 *
	 *	@param	\Asta\Database\Repository\Model	$near
	 *	@param	\Asta\Database\Repository\Model	$far
	 *	@param	string	$middle
	 *	@param	string	$foreignNearKey	foreign key in left table pointing to the right table
	 *	@param	string	$foreignFarKey	foreign key in right table pointing to the left table
	 *	@return	void
	 */
	public function __construct(
		Model $near,
		Model $far,
		string $middle = null,
		string $foreignNearKey = null,
		string $foreignFarKey = null
	) {
		$this->left = $near;
		$this->right = $far;
		$this->associative = $middle;

		$this->inferKeys($foreignNearKey, $foreignFarKey);
	}

	/**
	 *	Returns the data from the relation results
	 *
	 *	@return	mixed
	 */
	protected function fetchData()
	{
		$nearEntity = $this->left->getEntity();	// e.g., user
		$farEntity = $this->right->getEntity();	// e.g., role

		$interSet = $this->associative ?? Arr::join('_', Arr::sorted($nearEntity, $farEntity)); // e.g., role_user

		$interKeyNear = $interSet . '.' . $this->leftKey; // role_user.user_id
		$interKeyFar = $interSet . '.' . $this->rightKey; // role_user.role_id

		$farSet = $this->right->getTable(); // roles
		$farKey = $farSet . '.' . $this->right->getKey(); // roles.id

		$nearKey = $this->left->getKey();
		$nearId = $this->left->$nearKey;

		$farSetFields = $farSet . '.*'; // roles.*

		return $this->getBuilder()->from($farSet) //
					->select($farSetFields) // 
					->join($interSet, $interKeyFar, '=', $farKey)
					->where($interKeyNear, '=', $nearId)
					->execute();
	}
	
}


