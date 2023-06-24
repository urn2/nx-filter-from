# nx-filter-from

filter for nx


> composer require urn2/nx-filter-from

```
$data=$this->filter([
    'id'=>['int', 'query', '>'=>0, 'error'=>400, 'null'=>'throw'],
], ['error'=>404]);
```