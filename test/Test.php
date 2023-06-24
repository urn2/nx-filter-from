<?php
error_reporting(E_ALL);

use PHPUnit\Framework\TestCase;
const AGREE_LICENSE = true;
class testFilter extends \nx\app{
	use \nx\parts\filter\from;
}
class Test extends TestCase{
	protected ?testFilter $app =null;
	public function __construct(){
		parent::__construct();
		$this->app =new testFilter();
	}
	public function testX(){
		$data =$this->app->filter(['id' => ['from' => ['id' => '1234']]]);
		$this->assertSame('1234', $data['id']);
	}
	public function testTest(){
		$data = $this->app->filter([
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
		$this->assertSame(2, $data['id']);
	}
	public function testRuleSort(){
		$data = $this->app->filter([
			'id' => [
				'int',
				'null' => 1,
			],
		], [
			'null' => 2,
			'from' => ['id' => null],
		]);
		$this->assertSame(1, $data['id']);
	}
	public function testRuleSort2(){
		$data=[];
		try{
			$data = $this->app->filter([
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
			$this->assertSame('Exception', 'throw before null set');
		}
		$this->assertSame(null, $data['id']);
	}
	public function testOneKey(){//todo 是否有必要？？
		$data = $this->app->filter('id', ['from' => ['id' => '1234']]);
		$this->assertSame('1234', $data);
	}
	public function testFromArray(){
		$data = $this->app->filter(['id' => ['from' => ['id' => '1234']]]);
		$this->assertSame('1234', $data['id']);
	}
	public function testFromCallable(){
		$callable = function($key){
			//$this->log('in callable key:', $key);
			return 1234;
		};
		$data = $this->app->filter(['id'], ['from' => $callable]);
		$this->assertSame(1234, $data['id']);
	}
	public function testTypeInt(){
		$data = $this->app->filter(['id' => ['int']], ['from' => ['id' => '1234']]);
		$this->assertSame(1234, $data['id']);
	}
	public function testTypeString(){
		$data = $this->app->filter(['id' => ['str']], ['from' => ['id' => 1234]]);
		$this->assertSame('1234', $data['id']);
	}
	public function testTypeStringNull(){
		$data = $this->app->filter(['id' => ['str']], ['from' => ['id' => null]]);
		$this->assertSame(null, $data['id']);
	}
}
