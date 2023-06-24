<?php

use nx\helpers\filter\rule;
use nx\helpers\log\Escape;

error_reporting(E_ALL);
include_once '../vendor/autoload.php';
const AGREE_LICENSE = true;
class demo extends \nx\app{
	use \nx\parts\filter\from, \nx\parts\log\cli;

	public function main(){
		$this->filter->set('xx', rule::transfer);
		//		$this->log($this);
		//		$this->log($data);
		foreach(get_class_methods($this) as $method){
			if(strlen($method) > 5 && substr_compare($method, 'match', 0, 5) == 0){
				$data = $this->{$method}();
				$this->comp(substr($method, 5), ...$data);
			}
		}
	}
	protected function matchTest(): array{
		$data = $this->filter([
			'id' => [
				'query',
				'int',
				'throw' => 404,
				'null' => 'default',
				'>' => 3,
				'<=' => ['value' => 10, 'error' => 405, 'default' => 2, 'fail' => 'default'],
				'default' => 40,
				'from' => ['id' => null],
			],
		], ['body', 'null' => ['fail' => 'remove'], 'error' => ['code' => '400']]);
		return [$data['id'], 2];
	}
	protected function matchRuleSort(): array{
		$data = $this->filter([
			'id' => [
				'int',
				'null' => 1,
			],
		], [
			'null' => 2,
			'from' => ['id' => null],
		]);
		return [$data['id'], 1];
	}
	protected function matchRuleSort2(): array{
		try{
			$data = $this->filter([
				'id' => [
					'int',
					'throw' => 404,
					'null' => 'default',
				],
			], [
				'null' => 2,
				'from' => ['id' => null],
			]);
		}catch(\Exception){
			return ['Exception', 'throw before null set'];
		}
		return [$data['id'], null];
	}
	protected function matchOneKey(): array{//todo 是否有必要？？
		$data = $this->filter('id', ['from' => ['id' => '1234']]);
		return [$data, '1234'];
	}
	protected function matchFromArray(): array{
		$data = $this->filter(['id' => ['from' => ['id' => '1234']]]);
		return [$data['id'], '1234'];
	}
	protected function matchFromCallable(): array{
		$callable = function($key){
			//$this->log('in callable key:', $key);
			return 1234;
		};
		$data = $this->filter(['id'], ['from' => $callable]);
		return [$data['id'], 1234];
	}
	protected function matchTypeInt(): array{
		$data = $this->filter(['id' => ['int']], ['from' => ['id' => '1234']]);
		return [$data['id'], 1234];
	}
	protected function matchTypeString(): array{
		$data = $this->filter(['id' => ['str']], ['from' => ['id' => 1234]]);
		return [$data['id'], '1234'];
	}
	protected function matchTypeStringNull(): array{
		$data = $this->filter(['id' => ['str']], ['from' => ['id' => null]]);
		return [$data['id'], null];
	}
	protected function comp(string $label, $check, $value){
		$match = $check === $value;
		$dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
		//$this->log($dbt);
		//$d = $dbt[1] ?? $dbt[0];//调用函数信息在前一次位置
		$f = $dbt[0];
		echo Escape::template("\n{u}$label{uN}");
		if(!$match){
			$c = $this->var_dump($check);
			$v = $this->var_dump($value);
			echo Escape::template(" {r}no match.{w}\t{$f['file']}:{$f['line']}\n{bkB}{w}$c\n{y}$v");
		}else{
			echo Escape::template(" {g}match.{w}\t{$f['file']}:{$f['line']}");
		}
		echo Escape::template("{0}\n");
	}
}
// var_log( $var, '$name' ) ;
//\nx\helpers\filter\rules::set('xx', 'xx');
$demo = new demo();
//\nx\helpers\filter\rules::set('xx', 'xx');
$demo->run();
//echo Escape::template("\n\n{cB}{bk}done.");

