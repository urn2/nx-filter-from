<?php

namespace nx\helpers\filter;

class rules{
	/**
	 * @var array [{rule, parse:callable, transfer:callable, check:callable, abbr:string[], mode:merge|replace|repeat}]
	 */
	static protected array $Rules = [
		'error' => [rule::error, 'parse' => 'parseError',],
		'default' => [rule::default, 'parse' => 'parseDefault', 'mode' => 'replace'],
		'from' => [rule::from, 'parse' => 'parseFrom', 'abbr' => ['body', 'query', 'uri', 'header', 'cookie'], 'mode' => 'replace'],
		'key' => [rule::key, 'parse' => 'parseKey', 'mode' => 'replace'],
		'type' => [
			rule::transfer,
			'parse' => 'parseType',
			'transfer' => 'transferType',
			'abbr' => ['bool', 'boolean', 'obj', 'object', 'keys', 'arr', 'array', 'values', 'json', 'int', 'integer', 'uint', 'unsigned', 'str', 'string', 'date', 'hex', 'base64'],
			'mode' => 'replace',
		],
		'json' => [rule::transfer, 'transfer' => 'transferJson', 'mode' => 'replace'],
		'null' => [rule::check, 'parse' => 'parseNull', 'check' => 'checkNull', 'abbr' => ['throw', 'remove'],],
		'empty' => [rule::check, 'parse' => 'parseNull', 'check' => 'checkEmpty'],
		'digit' => [rule::check, 'parse' => 'parseDigit', 'check' => 'checkDigit', 'abbr' => ['=', '>', '<', '!=', '>=', '<='], 'mode' => 'repeat'],
		'match' => [rule::check, 'abbr' => ['number', 'email', 'url', 'ip-v4', 'ip-v6']],
		'email' => [rule::check, 'check' => 'matchEmail', 'mode' => 'replace'],
	];
	static protected array $_names = [];
	/**
	 * 生成规则缓存待用
	 * ruleName=>[0 =>typeName, parse=>解析回调, transfer=>格式转换, abbr=>规则缩写]
	 * $_names[0=>name索引引用, typeName=>[[rule1], [rule2]...], typeName2=>[]...]
	 *
	 * @return void
	 */
	static public function buildCache(): void{
		static::$_names = [];
		foreach(static::$Rules as $rule => $data){
			$type = $data[0]->name;
			if(!array_key_exists($type, static::$_names)) static::$_names[$type] = [];//初始化类型组
			static::$_names[0][$rule] =& static::$Rules[$rule];//索引
			static::$_names[$type][] = $rule;//分组存储规则
			foreach($data['abbr'] ?? [] as $abbr){//缩写等同规则处理
				static::$_names[0][$abbr] =& static::$Rules[$rule];
				static::$_names[$type][] = $abbr;
			}
		}
	}
	static public function log($log):void{
		if(\nx\app::$instance) \nx\app::$instance?->runtime($log, 'ff');
	}
	/**
	 * 设置规则
	 *
	 * @param string        $name   规则名
	 * @param rule          $type   类型
	 * @param callable|null $parse  规则解析方法 输入规则名 ->parse($name) 返回标准格式
	 * @param callable|null $check  规则检测 ->check($value, $rule) 返回 result
	 * @param array         $abbr   缩写的规则名数组 方便扩充
	 * @param bool          $repeat 规则是否允许配置多条
	 * @return void
	 */
	static public function set(string $name, rule $type, ?callable $parse = null, ?callable $check = null, array $abbr = [], bool $repeat = false): void{
		static::log("set rule: $name ");
		self::$Rules[$name] = [$type, 'repeat' => $repeat];
		if(is_callable($parse)) self::$Rules[$name]['parse'] = $parse;
		if(is_callable($check)) self::$Rules[$name]['check'] = $check;
		if(count($abbr)) self::$Rules['abbr'] = $abbr;
		static::buildCache();
	}
	static protected function callIt($call, ...$args){
		if(is_callable($call)) return call_user_func($call, ...$args);
		if(is_string($call) && method_exists(static::class, $call)) return call_user_func([static::class, $call], ...$args);
		throw new \LogicException('filter:未知回调格式');
		//static::log('unknown callable: ', $call, ...$args);
		//return null;
	}
	/**
	 * 格式化规则格式
	 *
	 * @param array $chaos_rules ['int', 'error'=>400, 'throw'=>[message=>'xx'], fn(), fn()=>[]]
	 * @return array
	 */
	static protected function formatRules(array $chaos_rules = []): array{
		$rules = [];
		foreach($chaos_rules as $rule => $set){
			if(is_int($rule)){//['int']
				if(is_string($set)){//'int'
					$rule = $set;
					$set = null;
				}else{
					if($set instanceof \Closure){//fn()
						//static::log('chaos rule ');
						$rule = 'callback';
						$set = ['call' => $set];
						//}elseif(is_array($set)){ // [[]] => ['keys'=>[]]
						//	$rule = 'object';
					}else throw new \LogicException('filter:未知规则格式');
				}
			}else{
				if($rule instanceof \Closure){//[fn()=>[]]
					//static::log('chaos rule', $rule, $set);
					$rule = 'callback';
					$set = ['call' => $rule, 'args' => $set];
				}else{
					if(!is_string($rule)){
						throw new \LogicException('filter:未知规则类型');
					}
				}
			}
			if(array_key_exists($rule, static::$_names[0])){//存在规则设定
				$config = &static::$_names[0][$rule];
				$parse = $config['parse'] ?? null;
				$rules[] = (null !== $parse && $r = static::callIt($parse, $rule, $set, $config)) ? $r : [$rule, $set];
			}else throw new \LogicException("filter:未知规则 ( $rule )");
		}
		return $rules;
	}
	/**
	 * 合并规则，去除多余和重复的规则
	 *
	 * @param $rules
	 * @return array|array[]
	 */
	static protected function mergeRules($rules): array{
		//static::log('merge rules: ', $rules);
		$final = [];
		$check = [];
		foreach($rules as $rule){
			if(!isset($rule[0])) continue;
			$config = &static::$_names[0][$rule[0]];
			//static::log('rule: ', $rule, $config);
			if(null === $config){//未匹配到规则
				static::log('no rule: '. $rule[0]);
				continue;
			}
			if(rule::check === $config[0]) $_c = &$check[$rule[0]];else$_c = &$final[$config[0]->name];
			match ($config['mode'] ?? 'merge') {
				'merge' => $_c = $rule[1] + ($_c ?? []),
				'replace' => $_c = $rule[1],
				'repeat' => $_c[] = $rule[1],
			};
		}
		$final['check'] = $check;
		return $final;
	}
	/**
	 * 解析并合并规则，按规则类型分别进行处理，形成最终执行规则
	 *
	 * @param array $set
	 * @param array $global //全局用，被 set 覆盖
	 * @return array[]
	 */
	static public function parse(array $set = [], array $global = []): array{//mergeRules
		//为啥要先合并后再整理？？？ bug: set[error]='xxx', global[error]=400 => error[code:400, message:'xxx']
		//$rules = static::formatRules([...$global, ...$set]);
		return static::mergeRules(array_merge(static::formatRules($global), static::formatRules($set)));
	}
	static public function transfer($value, $rule): mixed{
		//static::log('transfer: ', $rule['transfer']);
		return array_key_exists('transfer', $rule) ? static::callIt($rule['transfer']['transfer'], $value, $rule['transfer']['type'], $rule['transfer']['options']) : $value;
	}
	static protected function mergeResult($options, $rule): array{
		$_options = ['error' => $rule['error'] ?? [], 'default' => $rule['default'] ?? null];
		if(array_key_exists('error', $options)){
			[, $_error] = static::parseError('error', $options['error'], []);
			$_options['error'] = $_error;
		}
		if(array_key_exists('default', $options)){
			$_options['default'] = $options['default'];
		}
		//static::log('mergeResult', $options, $rule, $_options);
		return $_options;
	}
	/**
	 * @throws \Exception
	 */
	static public function check($value, $rule = []): array{//todo 顺序，如 先 null or 先其他check
		foreach($rule['check'] as $check => $options){
			$config = &static::$_names[0][$check];
			//static::log('check', $value, $check, $options, $config);
			$r = static::callIt($config['check'], $value, $options, $rule);
			if(null === $r) throw new \LogicException("filter:无效的检测 ( $check )");
			//static::log('check', $config['check'], $r, $value, $check, $options, $config);
			if(is_bool($r)){//check() 只返回 true / false，自动构建后续逻辑
				$r = $r ? ['pass', []] : [$options['fail'] ?? 'throw', $options];
			}
			if(is_array($r) && 2 === count($r)){
				if(is_bool($r[0])) $r[0] = $r[0] ? 'pass' : $r[1]['fail'] ?? 'throw';
				$_options = static::mergeResult($r[1], $rule);
				//static::log("check [$check] result", $r, $_options);
				switch($r[0]){
					case 'pass':
						//nothing
						break;
					case 'throw':
						$throwable =$_options['error']['exception'] ?? \Exception::class;
						throw new $throwable($_options['error']['message'] ?? "filter check: [{$rule['key']}] $check : fail.", $_options['error']['code'] ?? 0);
					case 'default':
						$value = $_options['default'];
						break;
					case 'remove':
						return ['remove', null];
				}
			}else throw new \LogicException("filter:无效的检测结果 ( $check )");
		}
		return ['pass', ['value' => $value]];
	}
	/*
	 * from=>'body|query|uri|header|cookie'|ArrayAccess
	 */
	static protected function parseFrom($name, $set, $config): array{
		match ($name) {
			'from' => $from = $set ?? [],
			'body', 'query', 'uri', 'header', 'cookie' => $from = $name,
			default => throw new \LogicException("filter:未知来源 ( $name )"),
		};
		return ['from', $from];
	}
	static protected function parseKey($name, $set, $config): array{
		return ['key', $set];
	}
	static protected function parseType($name, $set, $config): array{
		//static::log('parseType', $rule, $set, $config);
		match ($name) {
			'obj' => $name = 'object',
			'arr' => $name = 'array',
			'int' => $name = 'integer',
			'uint' => $name = 'unsigned',
			'str' => $name = 'string',
			'bool' => $name = 'boolean',
		};
		return ['type', ['type' => $name, 'options' => $set, 'transfer' => &$config['transfer']]];
	}
	/*
	 * null=>'remove|throw|default' | null=>[set:'remove|throw|default', default:any, error:ERROR_SET]
	 */
	static protected function parseNull($name, $set): array{
		//static::log('parseNull', $name, $set);
		$_set = [];
		switch($name){
			case 'empty':
			case 'null':
				if(is_array($set)){
					$has_fail = array_key_exists('fail', $set);
					$has_default = array_key_exists('default', $set);
					$has_error = array_key_exists('error', $set);
					if($has_fail || $has_default || $has_error){//null=>[set:'remove|throw|default', default:any, error:ERROR_SET]
						$has_fail && $_set['fail'] = $set['fail'];//允许缺少，被后续规则合并覆盖
						$has_default && $_set['default'] = $set['default'];
						$has_error && $_set['error'] = $set['error'];
					}else{
						$_set['fail'] = 'default';
						$_set['default'] = $set;
					}
				}else{
					if(is_string($set)){//null=>'remove|throw|default'
						if('remove' === $set || 'throw' === $set || 'default' === $set){
							$_set['fail'] = $set;
						}else{
							$_set['fail'] = 'default';
							$_set['default'] = $set;
						}
					}else{// 'null', 'null'=>123
						$_set['fail'] = 'default';
						$_set['default'] = $set;
					}
				}
				break;
			case 'throw'://throw | throw=>ERROR_SET
				$set && $_set['error'] = $set;
				$_set['fail'] = $name;
				$name ='null';
				break;
			case 'remove':
				$_set['fail'] = $name;
				$name ='null';
				break;
			//			case 'default':
			//				$_set['fail'] = 'default';
			//				$_set['default'] = null;
			//				break;
		}
		return [$name, $_set];
	}
	static protected function checkNull($value, $options = [], $rule = []): bool{
		return !is_null($value);
	}
	static protected function checkEmpty($value, $options = [], $rule = []): bool{
		return !empty($value);
	}
	static protected function parseDefault($name, $set, $config = []): array{
		return ['default', $set ?? null];
	}
	static protected function parseError($name, $set, $config): array{
		$_set = [];
		if(is_array($set)){//[code:400, message:'错误提示信息', exception:'\Exception']
			array_key_exists('code', $set) && $_set['code'] = (int)$set['code'];
			array_key_exists('message', $set) && $_set['message'] = $set['message'];
			array_key_exists('exception', $set) && $_set['exception'] = $set['exception'];
		}else{
			if(is_int($set)){
				$_set['code'] = $set;
			}else{
				if(is_string($set)){
					$_set['message'] = $set;
				}else{
					if($set instanceof \Throwable){
						$_set['exception'] = $set::class;
					}
				}
			}
		}
		return ['error', $_set];
	}
	/*
	 * '=', '>', '<', '!=', '>=', '<=', digit:{'=':3, '<':10, '>':0, '!=':5, '>=':0, '<=':10}
	 */
	static protected function parseDigit($rule, $set, $config): array{
		$_set = [];
		switch($rule){
			case 'digit':
				$_set = $set ?? [];
				break;
			case '=':
			case '>':
			case '<':
			case '!=':
			case '>=':
			case '<=':
				if(is_array($set)){
					$_set = $set;
					if(array_key_exists('value', $set)){
						$_set[$rule] = $set['value'];
						unset($_set['value']);
					}
					//					if(array_key_exists('error', $set)){
					//						$_set['error'] = $set['error'];
					//					}
				}else $_set = is_array($set) ? $set : [$rule => $set];
				break;
			default:
				//$_set = [];
				break;
		}
		return ['digit', $_set];
	}
	static protected function checkDigit($value, $options = [], $rule = []): array{
		//static::log('check digit', $value, $options, $rule);
		foreach($options as $option){
			foreach(['=', '>', '<', '!=', '>=', '<='] as $opt){
				if(array_key_exists($opt, $option)){
					match ($opt) {
						'=' => $r = $value == $option['='],
						'>' => $r = $value > $option['>'],
						'<' => $r = $value < $option['<'],
						'!=' => $r = $value != $option['!='],
						'>=' => $r = $value >= $option['>='],
						'<=' => $r = $value <= $option['<='],
					};
					if(!$r) return [false, $option];
				}
			}
		}
		return [true, []];
	}
	static protected function transferType($value, $type, $set){
		//static::log('transfer type', $value, $type, $set);
		if(null === $value) return null;
		switch($type){
			case 'bool':
			case 'boolean':// id:{type:'bool'}
				$value = !!$value;
				break;
			case 'str':
			case 'string':// id:{type:'str'}
				$value = (string)$value;
				break;
			case 'int':
			case 'integer':// id:{type:'int'}
				$value = is_numeric($value) ? (int)$value : null; //'-123'=>123,'abc'=>null
				break;
			case 'uint':
			case 'unsigned'://
				$value = is_numeric($value) ? (int)$value : null;
				if($value < 0) $value = null;
				break;
			case 'json'://id:{type:'json'}, id:{type:{value:'json', children:{}}}
				$value = json_decode(trim($value), true);//if error return null
				//todo 识别json结构
				//				if($set['type'] ??false){//type:{value:json, type:int}
				//					$_rules =['type'=>['value'=>$set['type'], 'type'=>null]] +$rules;
				//					$value =self::transferType($value, $_rules);
				//				}
				break;
			case 'obj':
			case 'object':
			case 'keys':
				$value = (array)$value;
				if(!is_array($value)) $value = null;
				//todo 识别对象结构
				//				if(null!==$value && $set['children'] ??false){
				//					$opts=[]+$set;
				//					$opts['from'] =$value;
				//					unset($opts['value'], $opts['children']);
				//					$_children =$set['children'] ??[];
				//					$value=self::transferType($_children, $opts); //repeat this()
				//				}
				break;
			case 'arr':
			case 'array':
			case 'values':
				if(is_string($value)){//'1,2,3,4,5' => [1,2,3,4,5]
					$value = trim($value);
					$split = $type_set['split'] ?? ',';
					$value = strlen($value) ? ((str_contains($value, $split)) ? explode($split, $value) : [$value]) : [];
				}
				if(!is_array($value)) $value = null;
				//todo 识别array结构
				//				if(null !==$value && $set['children'] ??false){
				//					//整理当前设置作为子元素的父设置
				//					$opts=[]+$set;
				//					$opts['from'] =$value;
				//					unset($opts['value'], $opts['children']);
				//					//整理key->set
				//					$key_set=[];
				//					foreach($value as $_key=>$_un){
				//						$key_set[$_key]=$set['children'];
				//					}
				//					$value=$this->filter($key_set, $opts); //repeat this()
				//				}
				break;
			case 'hex':
				$value = hexdec(trim($value));//hexdec('z'), 'z'==='0'=>false, 'z'==0 => true, 0=='z' => true
				break;
			case 'base64':
				$value = base64_decode(trim($value), true);
				if(false === $value) $value = null;
				break;
			case 'date':
				$value = strtotime(trim($value));
				if(false === $value) $value = null;
				break;
		}
		return $value;
	}
	static protected function matchNumber($value): bool{
		return preg_match('/^(\d+)$/', $value);
	}
	static protected function matchEmail($value): bool{
		return filter_var($value, FILTER_VALIDATE_EMAIL);
	}
}