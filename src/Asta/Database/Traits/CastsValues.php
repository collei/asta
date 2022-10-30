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

	public function castInteger($value)
	{
		if (is_int($value) || ctype_digit($value)) {
			return (int)$value;
		}
		//
		return 0;
	}

	public function castInt($value)
	{
		return $this->castInteger($value);
	}

	public function castFloat($value)
	{
		if (is_numeric($value) || is_float($value)) {
			return (float)$value;
		}
		//
		return 0.0;
	}

	public function castDouble($value)
	{
		return $this->castFloat($value);
	}

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

	public function castArray($value)
	{
		return is_array($value) ? $value : array($value);
	}

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

