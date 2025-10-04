import AuthenticatedSessionController from './AuthenticatedSessionController'
import PasswordResetLinkController from './PasswordResetLinkController'
import NewPasswordController from './NewPasswordController'
import EmailVerificationPromptController from './EmailVerificationPromptController'
import VerifyEmailController from './VerifyEmailController'
import EmailVerificationNotificationController from './EmailVerificationNotificationController'
const Auth = {
    AuthenticatedSessionController: Object.assign(AuthenticatedSessionController, AuthenticatedSessionController),
PasswordResetLinkController: Object.assign(PasswordResetLinkController, PasswordResetLinkController),
NewPasswordController: Object.assign(NewPasswordController, NewPasswordController),
EmailVerificationPromptController: Object.assign(EmailVerificationPromptController, EmailVerificationPromptController),
VerifyEmailController: Object.assign(VerifyEmailController, VerifyEmailController),
EmailVerificationNotificationController: Object.assign(EmailVerificationNotificationController, EmailVerificationNotificationController),
}

export default Auth