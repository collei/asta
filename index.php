<?php
require __DIR__ . '/vendor/autoload.php';

use Asta\Database\Query\Builder;

$subquery = Builder::new()->from('attendants', 'a')
	->select('name','age','city')
	->where('name','like','%z')
	->orWhere('name','like','%s');

$querist = Builder::new()->from('clients', 'c')
	->join('requests as r','c.id','=','r.id_client')
	->join('stats as s', function($join){
		$join->on('s.id_request','r.id')
			->on('s.id_client','c.id');
	})->joinSub($subquery, 'sq', function($join){
		$join->on('a.city','sq.city');
	})->select('c.id','c.name','c.reg')
	->selectSub(function($query){
		$query->select('count(*)')->from('customers', 'u')
			->join('cities','u.id_city','=','cities.id')
			->where('name', 'like', 'A%')
			->where('city', 'r.id_city');
	}, 'countings')->where('r.item_count','>',10);
$sql = $querist->toSql();

$builder = print_r($querist, true);

?>
<html>
	<fieldset>
		<legend>Generated SQL</legend>
		<?=($sql)?>
	</fieldset>
	<fieldset>
		<legend>Builder Internals</legend>
		<pre><?=($builder)?></pre>
	</fieldset>
</html>



