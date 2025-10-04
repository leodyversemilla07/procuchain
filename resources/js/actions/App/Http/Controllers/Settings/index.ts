import AppearanceController from './AppearanceController';
import EmailNotificationController from './EmailNotificationController';
import PasswordController from './PasswordController';
import ProfileController from './ProfileController';
import PushNotificationController from './PushNotificationController';
import TwoFactorAuthenticationController from './TwoFactorAuthenticationController';
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    PasswordController: Object.assign(PasswordController, PasswordController),
    PushNotificationController: Object.assign(PushNotificationController, PushNotificationController),
    EmailNotificationController: Object.assign(EmailNotificationController, EmailNotificationController),
    AppearanceController: Object.assign(AppearanceController, AppearanceController),
    TwoFactorAuthenticationController: Object.assign(TwoFactorAuthenticationController, TwoFactorAuthenticationController),
};

export default Settings;
