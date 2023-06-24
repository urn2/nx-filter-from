<?php

namespace nx\parts\filter;
trait from{
	public ?\nx\helpers\filter\from $filter = null;
	protected function nx_parts_filter_from(): void{
		$this->filter = new \nx\helpers\filter\from();
	}
	/**
	 * # 过滤器
	 *  从指定来源(from)中获取数据并进行类型转换(transfer)，然后根据对应的检测(check)返回结果(result)
	 *      [key1=>[rule1, rule2:options, ...], key2...]
	 *      规则可简写，简写为字符串，即规则是有格式的，简写只能是字符串
	 *        同规则可多次出现，根据规则类型被合并(result)、覆盖(from,key,transfer)或同时起作用(check)，后续覆盖前面
	 *          规则有层级 option级 > rule级 > global 级，根据上述逻辑会进行继承并合并或覆盖
	 *      rule基本格式
	 *          rule => [option1=>set,option2...]
	 *          字符串式简写规则会自动扩充为上述格式
	 *      --- 默认options
	 *          , set:null 作为附加参数传递给parse和transfer等callable
	 *          , transfer:callable 类型转换的回调，只在transfer中存在
	 *          , parse:callable 用于解析规则的回调，在定义规则中设置，在解析rule步骤执行
	 *          , check:callable 用于检测的回调，在
	 *          , error:ERROR_SET
	 *
	 * ## 内置规则
	 *
	 * ### from=>'body|query|uri|header|cookie'|ArrayAccess
	 *      从 $this->in 或 ArrayAccess 中获取数据，需指定 key 名
	 *          如果为字符串 body|query|uri|header|cookie 会被转发到 $this->in->{from}(key)
	 *          如果为ArrayAccess返回 $array[key]
	 *      简写规则
	 *          body, query, uri, header, cookie 会被转换为 rule:[from:string]
	 * ### key=>string
	 *      简写指定from的key({a:1} 中的 a)，此规则会被转换为 rule:[key:string]
	 * ### type=>[type:'integer|unsigned|string|array|object|json|date|hex|base64']
	 *      转换从from中获取到的值的类型，强制转换，失败返回 null
	 *      简写规则
	 *          int, str, arr, integer, ... => rule:[type:string]
	 * ### result=>[default=>any, throw=>ERROR_SET]
	 *      检测失败后的结果配置，允许在check中覆盖，根据 check() 的结果返回
	 *          'default' 返回 result[default]
	 *          'throw' 抛出ERROR_SET异常
	 *          'remove' 从结果中移除此key，配置中不包含此项
	 * ### default=>any
	 *      转换为 rule:[result:{default:any}]
	 * ### throw | throw=>ERROR_SET
	 *      转换为 null=>[set:'throw', error:ERROR_SET]
	 * ### error=>ERROR_SET
	 *      check失败配置，当check失败后配置为throw时按此设置进行后续处理
	 *      ERROR_SET 的完整格式为 [code:400, message:'错误提示信息', exception:'\Exception']，可简写为 int|string|throwable 分别对应前面3个参数
	 *      转换为 rule:[result:{default:any}]
	 * ### null=>'remove|throw|default' | null=>[fail:'remove|throw|default', default:any, throw:ERROR_SET]
	 *      结果检测，当value为null时候的处理逻辑， remove 和 throw 作为字符串保留，如果需配置default为此字符串使用完整模式
	 *      1. remove 即从返回结果中移除对应的key
	 *      2. throw 抛出异常，异常信息从ERROR_SET中获取，会继承上层配置并覆盖
	 *      3. default 可省略
	 * ### remove
	 *      转换为 null=>[fail:'remove']
	 * ### empty 规则同 null配置
	 *      结果检测, 通过内置 empty() 检测返回false时候处理
	 * ### digit:{'=':3, '<':10, '>':0, '!=':5, '>=':0, '<=':10},
	 *      结果检测，对值进行数字比较
	 *      简写规则
	 *          '='=>3, '<'=>10 ... 转换为 rule:[digit:{'=':3}]
	 *
	 *
	 * ## 旧版(部分说明 待整理)
	 * 规则{type}:数据类型 如果类型或格式不正确，值为 null
	 * 不推荐直接使用type，而是使用具体类型替代，如 'integer'
	 * - type:'[integer|unsigned|string|array|object|json|date|hex|base64]',
	 * - type:'[int    |uint    |str   |arr  |obj   |json|date|hex|base64]',//简化设置写法
	 * - type:{value:'[array(values)|object(keys)]', children:{
	 * -      key:set[],// array 可省略key，即从 0开始
	 * - }}
	 * {'json':'integer'} // json格式的字符串，最终值为整形
	 * {'array':'integer'} //无key数组类型，内容是整形
	 * {'object':{n:'integer'}} //当前数据为key-value形式数组，其中key为n的值为整数
	 * values同array，keys同object
	 * 假定取值:{
	 *      user:{
	 *          id:3
	 *      },
	 *      users:[
	 *          {
	 *              id:1
	 *          },
	 *          {
	 *              id:2
	 *          }
	 *      ]
	 * }
	 * 设置规则:{
	 *      user:{'keys':{
	 *          id:'integer'
	 *      }}
	 *      users:{'values':{
	 *          {'keys':{
	 *              id:'integer',
	 *          }}
	 *      }}
	 * }
	 * 规则{callback}:使用自定义回调来检测取值, callback($value, $key, $source=[])
	 * callback:()=>[], //通过is_callable()检测的取值
	 * 规则{digit}:对值的结果进行数字比较
	 * 规则{length}:对值进行字符长度比较
	 * length:同digit设定
	 * 规则{match}:对值进行字符匹配检测
	 * match:'[number|email|url|china-mobile|china-id|ip-v4|ip-v6]',
	 *      number:数字规则 '0001' 123,
	 *      email:电子邮件规则
	 *      url:网址规则
	 *      china-mobile:中国手机号
	 *      china-id:中国身份证号
	 *      ip-v4:ipv4规则 255.255.255.255
	 *      ip-v6:ipv6规则
	 * match:'#^\d+$#', //如不在上述规则列表中即为正则匹配检测
	 *
	 * @param array|string $vars    obj->children{}规则设置。 当$vars为字符时，会转换成obj->children{$vars=>$options}
	 * @param array        $options 全局规则配置，缺省规则设置
	 * @return mixed
	 * @throws \Throwable
	 */
	public function filter(array|string $vars = [], array $options = []): mixed{
		return ($this->filter)($vars, $options);
	}
}