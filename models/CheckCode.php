<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2020 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\twofa\models;

use humhub\modules\twofa\drivers\GoogleAuthenticatorDriver;
use humhub\modules\twofa\helpers\TwofaHelper;
use Yii;
use yii\base\Model;

/**
 * This is the model class for form to check code of Two-Factor Authentication
 *
 * Class CheckCode
 * @package humhub\modules\twofa\models
 */
class CheckCode extends Model
{
    public const ERROR_CODE_EXPIRED = 'ERROR_CODE_EXPIRED';

    /** @var string|null */
    public $code;

    /** @var string|null */
    public $rememberBrowser;

    private bool $isRecoveryCodeLogin = false;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['code', 'required'],
            ['code', 'string'],
            ['code', 'verifyCode'],
            ['rememberBrowser', 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'code' => Yii::t('TwofaModule.base', 'Code'),
            'rememberBrowser' => Yii::t('TwofaModule.base', 'Code'),
        ];
    }

    /**
     * Verify code
     *
     * @param $attribute
     * @param $params
     */
    public function verifyCode($attribute, $params)
    {
        if (TwofaHelper::isCodeExpired()) {
            $this->addError($attribute, self::ERROR_CODE_EXPIRED);
            return;
        }

        $driver = TwofaHelper::getDriver();
        if (!$driver) {
            return;
        }

        if ($driver instanceof GoogleAuthenticatorDriver && $driver->validateRecoveryCode($this->code)) {
            $this->isRecoveryCodeLogin = true;
            return;
        }

        if (!$driver->checkCode($this->code)) {
            $this->addError($attribute, Yii::t('TwofaModule.base', 'Verifying code is not valid!'));
        }
    }

    /**
     * @param bool $validate
     * @return bool|string|null
     */
    public function save($validate = true)
    {
        if ($validate && !$this->validate()) {
            return false;
        }

        $driver = TwofaHelper::getDriver();
        $transaction = Yii::$app->db->beginTransaction();

        try {
            if ($this->isRecoveryCodeLogin) {
                if (!($driver instanceof GoogleAuthenticatorDriver) || !$driver->consumeRecoveryCode($this->code)) {
                    $this->addError('code', Yii::t('TwofaModule.base', 'Verifying code is not valid!'));
                    $transaction->rollBack();
                    return false;
                }

                Yii::$app->view->warn(
                    Yii::t('TwofaModule.base', 'You signed in with a recovery code. Please generate new recovery codes or reconfigure your authenticator app.')
                );
            }

            if (!TwofaHelper::disableVerifying()) {
                throw new \RuntimeException();
            }

            $transaction->commit();
        } catch (\Throwable) {
            $transaction->rollBack();
            return false;
        }

        if ($this->rememberBrowser) {
            TwofaHelper::rememberBrowser();
        }

        return true;
    }
}
