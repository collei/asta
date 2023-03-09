<?php
namespace Asta\Database\Traits;

use DateTime;

/**
 *	The query builder, fully naïve mode (never checks for real existence
 *	of any DB object for query generation).
 *
 *	@author	Collei de Laravel, Collei Inc. <collei@collei.com.br>
 *	@since	2022-10-22
 *
 */
trait CastsValues
{
	/**
	 * Executes value casting.
	 *
	 * @param	string	$value
	 * @param	mixed	$alternative = null
	 * @return	mixed
	 */
	public function castValue($value, $alternative = null)
	{
		$value = trim($value);
		//
		if (is_numeric($value)) {
			return $this->castNumeric($value);
		} elseif ($dateTime = $this->castDateTime($value)) {
			return $dateTime;
		} elseif (false !== ($str = $this->castString($value))) {
			return $str;
		}
		//
		return $alternative ?: null;
	}

	/**
	 * Casts a numeric string into a int or float.
	 *
	 * @param	mixed	$value
	 * @return	int|float
	 */
	public function castNumeric($value)
	{
		if (is_numeric($value)) {
			if (is_int($value) || ctype_digit($value)) {
				return (int)$value;
			}
			//
			return (float)$value;
		}
		//
		return 0;
	}

	/**
	 * Casts a numeric string into a integer.
	 *
	 * @param	mixed	$value
	 * @return	int
	 */
	public function castInteger($value)
	{
		if (is_int($value) || ctype_digit($value)) {
			return (int)$value;
		}
		//
		return 0;
	}

	/**
	 * Alias of castInteger().
	 * @see castInteger()
	 *
	 * @param	mixed	$value
	 * @return	int|float
	 */
	public function castInt($value)
	{
		return $this->castInteger($value);
	}

	/**
	 * Cast to fload value.
	 *
	 * @param	mixed	$value
	 * @return	float
	 */
	public function castFloat($value)
	{
		if (is_numeric($value) || is_float($value)) {
			return (float)$value;
		}
		//
		return 0.0;
	}

	/**
	 * Alias of castFloat().
	 * @see castFloat()
	 *
	 * @param	mixed	$value
	 * @return	float
	 */
	public function castDouble($value)
	{
		return $this->castFloat($value);
	}

	/**
	 * Try casting to a DateTimeInterface value.
	 *
	 * @param	mixed	$value
	 * @return	\DateTimeInterface
	 */
	public function castDateTime($value)
	{
		if ($time = strtotime($value)) {
			return DateTime::createFromFormat(
				'Y-m-d H:i:s.u', date('Y-m-d H:i:s.u', $time)
			);
		}
		//
		return false;
	}

	/**
	 * Wraps the value into an array if not already one.
	 *
	 * @param	mixed	$value
	 * @return	array
	 */
	public function castArray($value)
	{
		return is_array($value) ? $value : array($value);
	}

	/**
	 * Try cast a value to a string. Returns false if not convertible.
	 *
	 * @param	mixed	$value
	 * @return	string|false
	 */
	public function castString($value)
	{
		$stringable = is_numeric($value)
			|| is_int($value)
			|| is_float($value)
			|| is_bool($value)
			|| is_string($value)
			|| (is_object($value) and method_exists($value, '__toString'));
		//
		if ($stringable) {
			return (string)$value;
		}
		//
		return false;
	}

}

