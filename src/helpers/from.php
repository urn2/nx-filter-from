<?php

namespace nx\helpers\filter;
/**
 * 需要做几件事：
 * 1 解析规则成标准规则
 * 2 获取值从in里获取
 *   2.1 不存在直接返回并处理 ？
 * 3 对获取到的值进行类型转换
 * 4 进行后置规则检验
 * 5 返回规则对应的值
 */
class from{
	protected bool $cache =false;
	/**
	 * @throws \Exception
	 */
	public function __invoke($keysSet = [], $GlobalSet = []): mixed{
		if(!$this->cache){
			rules::buildCache();
			$this->cache =true;
		}
		$r = [];
		$one_key =false;
		if(is_string($keysSet)){
			$one_key =$keysSet;
			$keysSet=[$keysSet];
		}
		foreach($keysSet as $key => $set){
			if(is_numeric($key) && is_string($set)){
				$key =$set;
				$set =[];
			}
			$rule = rules::parse(['key' => $key, ...$set], $GlobalSet);
			//\nx\app::$instance?->log('parse rule:', $rule);
			$value = $this->get($rule);
			$value = rules::transfer($value, $rule);
			//\nx\app::$instance?->log('value:', $value);
			[$result, $options] = rules::check($value, $rule);
			//\nx\app::$instance?->log('check result:', $result, $options);
			switch($result){
				case 'pass':
					$r[$key] = $options['value'];
					break;
				case 'remove':
					continue 2;
				default://todo 保留
					$r[$key] = $value;
			}
		}
		return $one_key ?$r[$one_key] :$r;
	}
	public function get($rule): mixed{
		//  返回标准格式？
		$from = $rule['from'] ?? 'body';
		if(is_string($from)){
			return \nx\app::$instance?->in?->{$from}($rule['key']);
		}else if(is_callable($from)) return call_user_func($from, $rule['key'], $rule);
		return $from[$rule['key']] ?? null;
	}
	/**
	 * @param string        $name  规则名
	 * @param rule          $type  类型 可选 from result type check
	 * @param callable|null $parse 规则解析方法 输入规则名 ->parse($name) 返回标准格式
	 * @param callable|null $check 规则检测 ->check($value, $rule) 返回 result
	 * @param array         $abbr  缩写的规则名数组 方便扩充
	 * @return void
	 */
	public function set(string $name,
		rule $type,
		?callable $parse = null,
		?callable $check = null,
		array $abbr = []
	): void{
		rules::set($name, $type, $parse, $check, $abbr);
	}
}