# Private Messages for RunBB forum plugin


## Install
1.
```php
$ composer require runcmf/runbb-ext-pms:dev-master
```

2.  
add to setting.php into `plugins` section `'pms' => 'RunBBPMS\PrivateMessages'`  
  like:
```php
    'plugins' => [// register plugins as NameSpace\InitInfoClass
            'pms' => 'RunBBPMS\PrivateMessages'
        ],
```
3.  
go to Administration -> Plugins -> Private Messages -> Activate  


## Recommendations

* TODO


---
## Tests (TODO)
```bash
$ cd vendor/runcmf/runbb
$ composer update
$ vendor/bin/phpunit
```
---  
## Security  

If you discover any security related issues, please email to 1f7.wizard( at )gmail.com instead of using the issue tracker.  

---
## Credits
[private-messages](https://github.com/featherbb/private-messages)  


---
## License
 
```
Copyright 2016 1f7.wizard@gmail.com

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

