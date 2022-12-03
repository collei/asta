<?php
require __DIR__ . '/vendor/autoload.php';

use Asta\Database\Query\Builder;

$client_supplied_flithy_data = "%d' or 1=1 or ''='"; //%d\'--\r\n select * from usuarios ";

$subquery = Builder::new()->from('attendants', 'a')
	->join('homes','homes.city','city')
	->select('name','age','city')
	->where('name','like','%z')
	->orWhere('name','like','%s')
	->orWhere('name','like',$client_supplied_flithy_data);

$subwhere = Builder::new()->from('allowed_names')
	->select('name')
	->where('language', 'in', ['en-us','pt-br','jp-jp']);

$querist = Builder::new()->from('clients', 'c')
	->join('requests as r','c.id','=','r.id_client')
	->join('stats as s', function($join){
		$join->on('s.id_request','r.id')
			->on('s.id_client','c.id');
	})->joinSub($subquery, 'sq', function($join){
		$join->on('a.city','sq.city');
	})->select('c.id','c.name','c.reg')
	->selectSub(function($query) use ($subwhere){
		$query->select('count(*)')->from('customers', 'u')
			->join('cities','u.id_city','=','cities.id')
			->where('name', 'in', $subwhere)
			->where('city', 'r.id_city');
	}, 'countings')->where('r.item_count','>',10);

$sql = $querist->toSql();

$builders = [$querist];

?>
<html>
	<fieldset>
		<legend>Generated SQL</legend>
		<?=($sql)?>
	</fieldset>
	<fieldset>
	<?php 
	foreach ($builders as $builder) {
		?>
		<fieldset>
			<legend>Builder Internals</legend>
			<pre><?=(print_r($builder, true))?></pre>
		</fieldset>
		<?php
	}
	?>
	</fieldset>
</html>



