<?php
namespace Asta\Database\Query;

class Expression
{
	/**
	 *	@var mixed $value
	 */
	protected $value;

	/**
	 *	Create a new Expression
	 *
	 *	@param	mixed	$value
	 *	@return	void
	 */
	public function __construct($value)
	{
		$this->value = $value;
	}

	/**
	 *	Returns the value of the expression
	 *
	 *	@return	mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 *	Returns the value of the expression as string 
	 *
	 *	@return	string
	 */
	public function __toString()
	{
		return (string)($this->getValue());
	}

}



