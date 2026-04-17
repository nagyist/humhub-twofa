<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2020 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */

namespace humhub\modules\twofa\controllers;

use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\admin\components\Controller;

class ConfigController extends Controller
{
    /**
     * @inheritdoc
     */
    public function getAccessRules()
    {
        return [['permissions' => ManageModules::class]];
    }

    /**
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        return $this->redirect(['/twofa/admin/index']);
    }
}
