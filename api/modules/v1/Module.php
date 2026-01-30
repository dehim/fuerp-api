<?php

namespace api\modules\v1;

use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public $controllerNamespace = 'api\modules\v1\controllers';

    // 关键点 👇
    public $defaultRoute = 'site/index';

    public function init()
    {
        parent::init();
    }
}
