<?php

use Carbon\Carbon;
use LinkedIn\LinkedIn;
use Outlandish\SocialMonitor\Exception\SocialMonitorException;
use Outlandish\SocialMonitor\PresenceType\PresenceType;
use Outlandish\SocialMonitor\Validation;

class UserController extends BaseController
{
    protected static $publicActions = array('login', 'forgotten', 'reset-password', 'register', 'confirm-email');
    /** @var LinkedIn */
    protected $linkedin;

    protected $formInputLabels = array(
        'name'=>'Global.user',
        'user_level' => 'views.scripts.user.edit.label.user.level',
        'password'=>'Global.password', 'password_confirm'=>'Global.password-confirm', 'old_password'=>'route.user.edit.old-password'
    );


    public function init()
    {
        parent::init();

        $this->linkedin = $this->getContainer()->get('linkedin.client');
        $this->view->linkedinUrl = $this->linkedin->getLoginUrl([LinkedIn::SCOPE_BASIC_PROFILE, 'rw_company_admin']);
    }

	/**
	 * @user-level user
	 */
    public function linkedinAction()
    {
        if (isset($_REQUEST['code'])) {
            $token = $this->linkedin->getAccessToken($_REQUEST['code']);

            $this->flashMessage($this->translator->trans('route.user.linkedin.message.success'));
            /** @var Model_User $user */
            $user = Model_User::fetchById($this->auth->getIdentity());
            $expires = (new Carbon())->addSeconds($this->linkedin->getAccessTokenExpiration());
            $user->saveAccessToken(PresenceType::LINKEDIN(), $token, $expires);
        }

        $this->_helper->redirector->gotoSimple('edit-self', 'user');
    }

    /**
     * Displays a list of all users.
     * If current user is admin, also shows links for modifying each user
     * @user-level manager
     */
    public function indexAction()
    {
        $this->view->users = Model_User::fetchAll();
        $this->view->userLevels = Model_User::$userLevels;
    }

    /**
     * Attempts to login a user using the given credentials
     */
    public function loginAction()
    {
        if ($this->auth->hasIdentity()) {
            $this->_helper->redirector->gotoSimple('index', 'index');
        }

        if ($this->_request->isPost()) {
            $authAdapter = new Model_User();
            $authAdapter->authName = $this->_request->getParam('username');
            $authAdapter->authPassword = $this->_request->getParam('password');
            $result = $this->auth->authenticate($authAdapter);

            $redirect = $this->_request->getParam('redirect_to');
            $identity = $result->getIdentity();
            if($identity){
                $user = Model_User::fetchById($identity);

                if($user->last_failed_login){
                    $timeToResetLoginAttempts = new DateTime($user->last_failed_login);
                    $timeToResetLoginAttempts->modify('+ 1 hour');
                    $currentTime = new DateTime(gmdate('Y-m-d H:i:s'));

                    if($currentTime > $timeToResetLoginAttempts){
                        $user->failed_logins = 0;
                    }
                }

                if($user->failed_logins < 5){
                    if ($result->isValid()) {
                        $user->last_sign_in = gmdate('Y-m-d H:i:s');
                        $user->failed_logins = 0;
                        $user->save();

                        // do not reuse session ids
                        Zend_Session::regenerateId();

                        $this->redirect($redirect);
                    } else {
                        $this->auth->clearIdentity();
                        $user->failed_logins++;
                        $user->last_failed_login = gmdate('Y-m-d H:i:s');
                        $user->save();
                        $this->view->redirect_to = $redirect;
                        $this->flashMessage($this->translator->trans('route.user.login.message.cannot-login'), 'error'); //'Incorrect username/password or email has not been confirmed'
                    }
                }else{
                    $this->auth->clearIdentity();
                    $this->view->redirect_to = $redirect;
                    $this->flashMessage($this->translator->trans('route.user.login.message.too-many-tries'), 'error'); // Too many tries
                }
            }
        } else {
            $this->view->redirect_to = $this->_request->getPathInfo();
        }

        $this->_helper->layout()->setLayout('notabs');
    }

    /**
     * Prompts for a username/email address. If valid, an email will be sent with a link to reset the user's password
     */
    public function forgottenAction()
    {
		$this->_helper->layout()->setLayout('notabs');

        if ($this->auth->hasIdentity()) {
            $this->_helper->redirector->gotoSimple('index', 'index');
        }

        if ($this->_request->isPost()) {

			$resp = $this->recaptcha->verify($this->_request->getParam('g-recaptcha-response'), $_SERVER['REMOTE_ADDR']);
			if (!$resp->isSuccess()) {
				$this->flashMessage($this->translator->trans('recaptcha.failure'));
				return;
			}

            $username = $this->_request->getParam('username');
            if (!$username) {
                $this->flashMessage($this->translator->trans('route.user.forgotten.message.missing-username-email'), 'error'); //'Please enter a username or email address'
			} else {
				/** @var Model_User $user */
				$user = Model_User::fetchBy('name', $username);
				if (!$user) {
					$user = Model_User::fetchBy('email', $username);
				}

                if (!$user) {
                    $this->flashMessage($this->translator->trans('route.user.forgotten.message.user-not-found'), 'error'); //'User not found'
                } else {
                    $code = $this->generateCode();
                    $user->reset_key = $code;
                    $user->save();

                    try {
                        $this->sendResetPasswordEmail($user);
                        $this->flashMessage($this->translator->trans('route.user.forgotten.message.password-reset-email-sent')); //'You should receive an email shortly with a password reset link'
                    } catch (Exception $ex) {
                        $this->flashMessage($this->translator->trans('route.user.forgotten.message.password-reset-failed-email'), 'error'); //'Failed to send reset email.<br />Please ask an admin user to reset your password'
                    }
                }
            }
        }

    }

    /**
     * If the correct email/reset_key combination is provided, allows a user to reset their password
     */
    public function resetPasswordAction()
    {
        if ($this->_request->getParam('name') && $this->_request->getParam('reset_key')) {
            $user = Model_User::fetchAll('name=? AND reset_key=?', array($this->_request->getParam('name'), $this->_request->getParam('reset_key')));
            if ($user) {
                $user = $user[0];
            }
        } else {
            $user = null;
        }

        if ($user) {
            $this->view->editingUser = $user;
            if ($this->_request->isPost()) {
                $password = $this->_request->getParam('password');
                $password2 = $this->_request->getParam('password_confirm');
                if (!$password || !$password2) {
                    $this->flashMessage($this->translator->trans('route.user.reset-password.message.both-passwords-required'), 'error'); //'Both passwords are required'
                } else if ($password != $password2) {
                    $this->flashMessage($this->translator->trans('route.user.reset-password.message.password-mismatch'), 'error'); //'Passwords do not match'
                } else if (strlen($password) < 4) {
                    $this->flashMessage($this->translator->trans('route.user.reset-password.message.passwords-too-short'), 'error'); //'Password must be at least 4 characters'
                } else {
                    $user->fromArray(array('password' => $password));
                    $user->reset_key = null;
                    $user->last_sign_in = gmdate('Y-m-d H:i:s');
                    $user->save();

                    $authAdapter = new Model_User();
                    $authAdapter->authName = $user->name;
                    $authAdapter->authPassword = $password;
                    $this->auth->authenticate($authAdapter);

                    $this->flashMessage($this->translator->trans('route.user.reset-password.message.success')); //'Password changed successfully'
                    $this->_helper->redirector->gotoSimple('index', 'index');
                }
            }
        } else {
            $this->flashMessage($this->translator->trans('route.user.reset-password.message.incorrect-user-key'), 'error'); //'Incorrect user/key combination for password reset'
            $this->_helper->redirector->gotoSimple('index', 'index');
        }

		$this->_helper->layout()->setLayout('notabs');
    }

    /**
     * Logs out the current user
     */
    public function logoutAction()
    {
        $this->auth->clearIdentity();
        $this->flashMessage($this->translator->trans('route.user.logout.message.success'));
        $this->_helper->redirector->gotoSimple('index', 'index');
    }

    /**
     * Registers a new user if the new user is using a british council email address
     *
     */
    public function registerAction()
    {

        $this->editAction();
        $registerSuccessful = $this->_request->getParam('result') === 'success';
        if ($registerSuccessful) {
            $this->view->pageTitle = $this->translator->trans('route.user.register.registration-successful'); //'Registration success';
        }
        $this->view->registerSuccessful = $registerSuccessful;
        $this->_helper->layout()->setLayout('notabs');
    }

    /**
     * Prompts to create a new dashboard user
     * @user-level manager
     */
    public function newAction()
    {
        // do exactly the same as in editAction, but with a different title
        $this->editAction();
        $this->_helper->viewRenderer->setScriptAction('edit');
    }

    /**
     * Prompts to modify the current user
     * @user-level user
     */
    public function editSelfAction()
    {
        // do exactly the same as in editAction, but with different permissions
        $this->editAction();
        $this->view->canChangeLevel = false;
        $this->view->showAccessTokens = true;
        $this->view->linkedinToken = $this->view->editingUser->getAccessToken(PresenceType::LINKEDIN());
        $this->_helper->viewRenderer->setScriptAction('edit');
    }

    /**
     * Prompts to modify an existing user (also used for creating a new user, indicated by argument).
     * @user-level manager
     */
    public function editAction()
    {
        $messageOnSave = $this->translator->trans('route.user.edit.saved'); //'User saved';
        $action = $this->_request->getActionName();
        /** @var Model_User $editingUser */
        switch ($action) {
            case 'new':
                $editingUser = new Model_User(array());
                $messageOnSave = $this->translator->trans('route.user.edit.created'); //'User created';
                break;
            case 'register':
                $editingUser = new Model_User(array());
                $messageOnSave = $this->translator->trans('route.user.edit.registered'); //'User registered';
                break;
            case 'edit-self':
                $editingUser = new Model_User($this->view->user->toArray(), true);
                break;
            case 'edit':
            default:
                $editingUser = Model_User::fetchById($this->_request->getParam('id'));
                break;
        }

        $this->validateData($editingUser);
        $this->view->canChangeLevel = isset($this->view->user) ? $this->view->user->isManager : false;

        if ($this->_request->isPost()) {
            // prevent hackers upgrading their own user level
            $params = $this->_request->getParams();



            $password = $this->_request->getParam('password');
            $password2 = $this->_request->getParam('password_confirm');
            $oldPassword = $this->_request->getParam('old_password');

            if (($action == 'edit' || $action == 'edit-self') && !$this->isAuthorizedToChangeLevel($params, $editingUser)) {
                if(($password || $password2) && !($action == 'edit-self')){
                    $this->flashMessage($this->translator->trans('route.user.edit.message.not-allowed'), 'error');
                    return $this->redirectUser($editingUser);
                }
                unset($params['user_level']);
            }

            if($action == 'new' && !$this->isAuthorizedToCreateUser($params)){
                $this->flashMessage($this->translator->trans('route.user.edit.message.not-allowed'), 'error');
                return $this->redirectUser($editingUser);
            }

            if($action == 'edit-self' && ( $password || $password2 )){
                $oldPasswordMatches = $this->verifyInput([$oldPassword => [
                    'inputLabel' => $this->formInputLabels['old_password'],
                    'validator' => new Validation\PasswordValidator($editingUser),
                    'required' => true
                ]]);

                if(!$oldPasswordMatches){
                    return $this->redirectUser($editingUser);
                }
            }

            $isValidInput = $this->verifyInput([
                $this->_request->getParam('name') => [
                    'inputLabel' => $this->formInputLabels['name'],
                    'validator' => new Validation\StringValidator(),
                    'required' => true
                ],
                $this->_request->getParam('email') => [
                    'inputLabel' => 'Email address',
                    'validator' => new Validation\EmailValidator(),
                    'required' => true
                ],
            ]);

            if(!$isValidInput){
                return $this->redirectUser($editingUser);
            }

            $setProperties = $this->setProperties($editingUser, $params);
            $errorMessages = array();

            if ($action == 'register' &&
                !$this->isValidEmailAddress($this->_request->getParam('email'))) {
                $errorMessages[] = $this->translator->trans('route.user.edit.message.use-company-email', ['%company%' => $this->getCompanyName()]); //'To register, you must use a valid British Council email address';
            }

			if ($action == 'register') {
				$resp = $this->recaptcha->verify($this->_request->getParam('g-recaptcha-response'), $_SERVER['REMOTE_ADDR']);
				if (!$resp->isSuccess()) {
					$errorMessages[] = $this->translator->trans('recaptcha.failure');
				}
			}

            if (!$errorMessages && $setProperties) {
                // don't require a new password for existing users
                if (!$editingUser->id && (!$password || !$password2)) {
                    $errorMessages[] = $this->translator->trans('route.user.edit.message.both-passwords-required'); //'Please enter the password in both boxes';
                } else if ($password != $password2) {
                    $errorMessages[] = $this->translator->trans('route.user.edit.message.password-mismatch'); //'Passwords do not match';
                } else if ($password && strlen($password) < 4) {
                    $errorMessages[] = $this->translator->trans('route.user.edit.message.passwords-too-short'); //'Password must be at least 4 characters';
                }
            }

            if ($action == 'register') {
                $code = $this->generateCode();
                $editingUser->confirm_email_key = $code;
            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->flashMessage($message, 'error');
                }
            }

            if(!$errorMessages && $setProperties) {
                try {
                    $editingUser->save();
                    $this->flashMessage($messageOnSave);
                    if ($this->view->user->isManager) {
                        $this->_helper->redirector->gotoSimple('index');
                    } else if ($action == 'register') {
                        $this->sendRegisterEmail($editingUser);
                        $this->_helper->redirector->gotoRoute(['action' => 'register', 'result' => 'success']);
                    }
                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        if (strpos($ex->getMessage(), 'email') !== false) {
                            $message = $this->translator->trans('route.user.edit.message.email-in-use'); //'Email address already in use';
                        } else {
                            $message = $this->translator->trans('route.user.edit.message.username-in-use'); //'User name already taken';
                        }
                        $this->flashMessage($message, 'error');
                    } else {
                        $this->flashMessage($ex->getMessage(), 'error');
                    }
                }
            }
        }
        $this->redirectUser($editingUser);
    }

    public function redirectUser($user){
        $this->view->userLevels = Model_User::$userLevels;
        $this->view->editingUser = $user;
        $this->view->showAccessTokens = false;
    }

    /**
     * Deletes a user
     * @user-level manager
     */
    public function deleteAction()
    {
        $user = Model_User::fetchById($this->_request->getParam('id'));

        $this->validateData($user);

        if ($this->_request->isPost()) {
            $user->delete();
            $this->flashMessage($this->translator->trans('route.user.delete.message.success')); //'User deleted'
        }
        $this->_helper->redirector->gotoSimple('index');
    }

    /**
     * Manages a user's access rights
     * @user-level manager
     */
    public function manageAction()
    {
        /** @var Model_User $user */
        $user = Model_User::fetchById($this->_request->getParam('id'));
        $this->validateData($user);

        if ($this->_request->isPost()) {
            $user->assignAccess($this->_request->getParam('assigned'));
            $this->flashMessage($this->translator->trans('route.user.manage.message.permissions-saved')); //'User permissions saved');
            $this->_helper->redirector->gotoSimple('index');
        }

        $this->updatePageTitle(['user' => $user->safeName]); // 'Edit access rights for ' . $user->safeName;
        $this->view->editingUser = $user;
        $this->view->twitterPresences = Model_PresenceFactory::getPresencesByType(PresenceType::TWITTER());
        $this->view->facebookPresences = Model_PresenceFactory::getPresencesByType(PresenceType::FACEBOOK());
        $this->view->countries = Model_Country::fetchAll();
        $this->view->groups = Model_Group::fetchAll();
    }

    /**
     * Confirm email action
     */
    public function confirmEmailAction()
    {
        /** @var Model_User $user */
        if ($this->_request->getParam('name') && $this->_request->getParam('confirm_email_key')) {
            $user = Model_User::fetchAll('name=? AND confirm_email_key=?', array($this->_request->getParam('name'), $this->_request->getParam('confirm_email_key')));
            if ($user) {
                $user = $user[0];
            }
        } else {
            $user = null;
        }

        if ($user) {
            try {
                $user->confirm_email_key = null;
                $user->save();
            } catch (Exception $ex) {
                $this->flashMessage($this->translator->trans('route.user.confirm-email.message.fail'), 'error'); //'Something went wrong and we couldn't confirm your email address.'
                $this->_helper->redirector->gotoSimple('index', 'index');
            }
            $this->flashMessage($this->translator->trans('route.user.confirm-email.message.success'), 'info');
            $this->_helper->redirector->gotoSimple('login', 'user');
        } else {
            $this->flashMessage($this->translator->trans('route.user.confirm-email.message.invalid-email'), 'error');
            $this->_helper->redirector->gotoSimple('index', 'index');
        }
    }

    /**
     * Send an email when a user has registered so that they can confirm their email
     *
     * @param Model_User $registeredUser
     */
    private function sendRegisterEmail(Model_User $registeredUser)
    {
        $subject = $this->translator->trans('route.user.register.email.subject'); //"You have successfully registered";
        $toEmail = $registeredUser->email;
		$fromEmail = $this->translator->trans('route.user.register.email.from-address');
		$fromName = $this->translator->trans('route.user.register.email.from', ['%company%' => $this->getCompanyName()]);
        $resetLink = $this->getResetLink($registeredUser, 'confirm-email');
        $message = $this->translator->trans('route.user.register.email.message', ['%name%' => $registeredUser->name, '%link%' => $resetLink, '%company%' => $this->getCompanyName()]);

        $this->sendEmail($message, $fromEmail, $fromName, $toEmail, $subject);
    }

    /**
     * Send a password reset email to the given Model_User
     *
     * @param Model_User $user
     * @throws SocialMonitorException
     */
    private function sendResetPasswordEmail(Model_User $user)
    {
		$subject = $this->translator->trans('route.user.forgotten.email.subject');
		$fromAddress = $this->translator->trans('route.user.forgotten.email.from-address');
		$fromName = $this->translator->trans('route.user.forgotten.email.from', ['%company%' => $this->getCompanyName()]);
        $resetLink = $this->getResetLink($user, 'reset-password');
        $message = $this->translator->trans('route.user.forgotten.email.message', ['%name%' => $user->name, '%link%' => $resetLink, '%company%' => $this->getCompanyName()]);

        $this->sendEmail($message, $fromAddress, $fromName, $user->email, $subject);
    }

    /**
     * Generate a random code for use with resettng password and confirming emails
     *
     * @return string
     */
    private function generateCode()
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $code = substr(str_shuffle($chars), 0, 16);
        return $code;
    }

    /**
     * Generate a reset link for a user and a particular action
     *
     * @param Model_User $user
     * @param string $action
     * @return string
     */
    private function getResetLink(Model_User $user, $action)
    {
        $scheme = $this->_request->getScheme();
        $host = $this->_request->getHttpHost();
        $url = $this->view->url(['action' => $action]);
        $paramString = http_build_query([
            'name' => $user->name,
            'confirm_email_key' => $user->confirm_email_key
        ]);
        return "{$scheme}://{$host}{$url}?{$paramString}";
    }

	/**
	 * @user-level user
	 */
	public function joyrideAction()
	{
		if (!$this->_request->isPost()) {
			return $this->apiError("Only accepts POST");
		}

		$ride = $this->_request->getParam('ride', null);
		if (!$ride) {
			return $this->apiError("Missing parameter ride");
		}

		/** @var Model_User $user */
		$user = $this->view->user;

		if (!$user) {
			return $this->apiError("Must be logged in");
		}

		$user->setCompletedJoyrides($this->_request->getParam('ride', null), true);

		return $this->apiSuccess([], ["Updated User with joyride"]);
	}

    /**
     * Tests whether email is a british council email address
     *
     * For testing purposes it also passes if the email address is @outlandish.com
     *
     * @param $email
     * @return bool
     */
    private function isValidEmailAddress($email)
    {
        $validLogin = $this->getContainer()->getParameter('email.login');
        return (preg_match($validLogin, $email) === 1) || (preg_match('/@outlandish.com$/i', $email) === 1);
    }

	/**
	 * Only allows managers and admins to change user level, and restricts level that managers can change to
	 *
	 * @param $params
	 * @return bool
	 */
	protected function isAuthorizedToChangeLevel($params, $userToEdit)
	{
        //check that the user making the change can edit the user level of a user
		return $this->view->canChangeLevel &&
            //check that the user making the change is a high enough level to make the user being edited to the given user_level
        ($this->view->user->user_level > $userToEdit->user_level) &&
            //check that the user level is a valid user level
            array_key_exists($params['user_level'], Model_User::$userLevels);
	}

    /**
     * Only allows managers and admins to create users with a certain level
     *
     * @param $params
     * @return bool
     */
    protected function isAuthorizedToCreateUser($params){
        //check that the user making the change can edit the user level of a user
        return $this->view->canChangeLevel &&
        //check that the user making the change is a high enough level to make the user being edited to the given user_level
        ($this->view->user->user_level >= $params['user_level']) &&
        //check that the user level is a valid user level
        array_key_exists($params['user_level'], Model_User::$userLevels);
    }
}
