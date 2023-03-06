<?php
require __DIR__ . '/vendor/autoload.php';

use Asta\Database\Query\Builder;
use Asta\Database\Repository\Model;

class Produto extends Model
{
	protected $table = 'produtos';
}



function prettyPrintNestedParenthesis($text, bool $return = false)
{
	$delim = null;
	$delims_open = ['{','[','(','<','"',"'"];
	$delims_close = ['}',']',')','>','"',"'"];
	$crlf = ["\r","\n","\r\n"];
	$res = '';
	$tab = '    ';
	$tabs = 0;
	$charlist = str_split($text);
	//
	foreach ($charlist as $ch) {
		if ('(' == $ch) {
			++$tabs;
			$res .= $ch . $crlf[2] . str_repeat($tab, $tabs);
		} elseif (')' == $ch) {
			--$tabs;
			$res .= $crlf[2] . str_repeat($tab, $tabs) . $ch;
		} else {
			$res .= $ch;
		}
	}
	//
	if ($return) {
		return $res;
	}
	//
	echo $res;
}






$client_supplied_flithy_data = "d' or 1=1 or ''='"; //%d\'--\r\n select * from usuarios ";

$produtos = Produto::all();

$subquery = Builder::new()->from('attendants', 'a')
	->join('homes','homes.city','city')
	->select('name','age','city')
	->where('name','like','%z')
	->orWhere('name','like','%s')
	->orWhere('name','like',"%{$client_supplied_flithy_data}%");

$subwhere = Builder::new()->fromSub(function($query){
		$query->from('names')->whereIn('origin', ['JP','EU','HB','AR']);
	}, 'allowed_names')
	->select('name')
	->where('language', 'in', ['en-us','pt-br','jp-jp'])
	->orWhere('fosters', Builder::raw('homes.city'));

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
	}, 'countings')->where('r.item_count','>',10)->skip(10)->take(20);

$sql = $querist->toSql();

$builders = compact('produtos','subquery','subwhere','querist');

?>
<html>
	<fieldset>
		<legend>Generated SQL</legend>
		<pre><?=(prettyPrintNestedParenthesis($sql))?></pre>
	</fieldset>
	<fieldset>
	<?php 
	foreach ($builders as $which => $builder) {
		?>
		<fieldset>
			<legend><b>Builder:</b> <i><?=($which)?></i></legend>
			<pre><?=(print_r($builder, true))?></pre>
		</fieldset>
		<?php
	}
	?>
	</fieldset>
</html>



