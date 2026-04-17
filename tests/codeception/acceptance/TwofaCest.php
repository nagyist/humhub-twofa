<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2020 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\twofa\tests\codeception\acceptance;

use humhub\libs\BasePermission;
use humhub\modules\admin\permissions\ManageUsers;
use humhub\modules\twofa\drivers\GoogleAuthenticatorDriver;
use humhub\modules\twofa\helpers\TwofaHelper;
use humhub\modules\user\components\PermissionManager;
use humhub\modules\user\models\GroupUser;
use humhub\modules\user\models\User;
use PHPUnit\Framework\Assert;
use tests\codeception\_pages\LoginPage;
use twofa\AcceptanceTester;
use Yii;

class TwofaCest
{
    public function testTwoFactorAuthentication(AcceptanceTester $I)
    {
        $I->wantTo('Ensure admin user login with 2FA');
        $loginPage = LoginPage::openBy($I);
        $I->amGoingTo('try to login with admin credentials');
        $loginPage->login('Admin', 'admin&humhub@PASS%worD!');
        $I->expectTo('See Two Factor Auth');
        $I->waitForText('Two-factor authentication');
    }

    public function testNoTwoFactorAuthentication(AcceptanceTester $I)
    {
        $I->wantTo('Ensure regular user login without 2FA');
        $loginPage = LoginPage::openBy($I);
        $I->amGoingTo('try to login with non-admin credentials');
        $loginPage->login('User1', 'user^humhub@PASS%worD!');
        $I->expectTo('see dashboard');
        $I->waitForText('User 2 Space 2 Post Public');
    }

    public function testManagerCanResetUserTwoFactorAuthentication(AcceptanceTester $I)
    {
        $I->wantTo('ensure a user manager can reset configured two-factor authentication from the module admin user list');

        $user = User::findOne(['username' => 'User1']);
        $manager = User::findOne(['username' => 'User2']);
        $userWithoutTwofa = User::findOne(['username' => 'User3']);

        $this->configureGoogleAuthenticator($user);
        $this->allowManageUsers($manager);

        Yii::$app->user->logout();

        $loginPage = LoginPage::openBy($I);
        $loginPage->login('User1', 'user^humhub@PASS%worD!');
        $I->waitForText('Two-factor authentication');
        $I->seeCurrentUrlEquals('/twofa/check');
        $I->resetCookie('PHPSESSID');
        $I->executeJS('window.localStorage.clear(); window.sessionStorage.clear();');
        $I->amOnPage('/user/auth/login');
        $I->waitForText('Please sign in');

        $I->amUser2();
        $I->amOnRoute(['/twofa/admin/users']);
        $I->waitForText('Enabled users');
        $I->see($user->displayName, '.table');
        $I->see('Time-based one-time passwords', '.table');
        $I->dontSee($userWithoutTwofa->displayName, '.table');
        $I->click('//tr[contains(., "' . addslashes($user->displayName) . '")]//a[contains(normalize-space(.), "Reset")]');
        $I->waitForElementVisible('#globalModalConfirm button[data-modal-confirm]', 10);
        $I->click('button[data-modal-confirm]', '#globalModalConfirm');
        $I->seeSuccess('Two-factor authentication has been reset for this user.');
        $I->seeCurrentUrlEquals('/twofa/admin/users');

        $this->assertUserHasNoTwofaSettings($I, $user);

        $I->logout();

        $loginPage = LoginPage::openBy($I);
        $loginPage->login('User1', 'user^humhub@PASS%worD!');
        $I->waitForElementVisible('#wallStream');
        $I->seeCurrentUrlEquals('/dashboard');
        $I->dontSee('Two-factor authentication');
    }

    private function configureGoogleAuthenticator(User $user): void
    {
        $settings = TwofaHelper::getSettings($user);
        $settings->set(TwofaHelper::USER_SETTING, GoogleAuthenticatorDriver::class);
        $settings->set(GoogleAuthenticatorDriver::SECRET_SETTING, 'JBSWY3DPEHPK3PXP');

        Yii::$app->getModule('user')->settings->flushContentContainer($user);
    }

    private function allowManageUsers(User $user): void
    {
        GroupUser::updateAll(['is_group_manager' => 0], ['user_id' => $user->id]);

        (new PermissionManager())->setGroupState(3, new ManageUsers(), BasePermission::STATE_ALLOW);
        Yii::$app->user->permissionManager->clear();
    }

    private function assertUserHasNoTwofaSettings(AcceptanceTester $I, User $user): void
    {
        Yii::$app->getModule('user')->settings->flushContentContainer($user);

        foreach (TwofaHelper::getUserSettingNames() as $settingName) {
            Assert::assertNull(TwofaHelper::getSettings($user)->get($settingName));
        }
    }
}
