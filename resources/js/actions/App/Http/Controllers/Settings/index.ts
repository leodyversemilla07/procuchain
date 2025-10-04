import ProfileController from './ProfileController'
import PasswordController from './PasswordController'
import PushNotificationController from './PushNotificationController'
import EmailNotificationController from './EmailNotificationController'
import AppearanceController from './AppearanceController'
import TwoFactorAuthenticationController from './TwoFactorAuthenticationController'
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
PasswordController: Object.assign(PasswordController, PasswordController),
PushNotificationController: Object.assign(PushNotificationController, PushNotificationController),
EmailNotificationController: Object.assign(EmailNotificationController, EmailNotificationController),
AppearanceController: Object.assign(AppearanceController, AppearanceController),
TwoFactorAuthenticationController: Object.assign(TwoFactorAuthenticationController, TwoFactorAuthenticationController),
}

export default Settings