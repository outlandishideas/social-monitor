<?php

use LinkedIn\LinkedIn;
use Outlandish\SocialMonitor\Exception\SocialMonitorException;

class UserController extends BaseController
{
	protected static $publicActions = array('login', 'forgotten', 'reset-password', 'register', 'confirm-email');

	public function init() {
		parent::init();
		$this->view->titleIcon = 'icon-group';
	}

	public function linkedin()
	{
		/** @var LinkedIn $linkedin */
		$linkedin = $this->getContainer()->get('linkedin.client');

		$token = $linkedin->getAccessToken($_REQUEST['code']);

		$user = $this->view->user;



		$linkedin->getLoginUrl([LinkedIn::SCOPE_BASIC_PROFILE, LinkedIn::SCOPE_FULL_PROFILE]);
	}

	/**
	 * Displays a list of all users.
	 * If current user is admin, also shows links for modifying each user
	 * @user-level manager
	 */
	public function indexAction()
	{
		$this->view->title = 'Users';
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

		$this->view->title = 'Login';

		if ($this->_request->isPost()) {
			$authAdapter = new Model_User();
			$authAdapter->authName = $this->_request->getParam('username');
			$authAdapter->authPassword = $this->_request->getParam('password');
			$result = $this->auth->authenticate($authAdapter);

            $redirect = $this->_request->getParam('redirect_to');
			if ($result->isValid())	{
				$user = Model_User::fetchById($this->auth->getIdentity());
				$user->last_sign_in = gmdate('Y-m-d H:i:s');
				$user->save();

				$this->redirect($redirect);
			} else {
				$this->view->redirect_to = $redirect;
                $this->flashMessage('Incorrect username/password or email has not been confirmed', 'error');
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
		if ($this->auth->hasIdentity()) {
			$this->_helper->redirector->gotoSimple('index', 'index');
		}

		if ($this->_request->isPost()) {
            $username = $this->_request->getParam('username');
			if (!$username) {
                $this->flashMessage('Please enter a username or email address', 'error');
			} else {
				$user = Model_User::fetchBy('name', $username);
				if (!$user) {
					$user = Model_User::fetchBy('email', $username);
				}

				if (!$user) {
                    $this->flashMessage('User not found', 'error');
				} else {
					$code = $this->generateCode();
					$user->reset_key = $code;
					$user->save();

					try {
						$this->sendResetPasswordEmail($user);
                        $this->flashMessage('You should receive an email shortly with a password reset link');
					} catch (Exception $ex) {
                        $this->flashMessage('Failed to send reset email.<br />Please ask an admin user to reset your password', 'error');
					}
				}
			}
		}
		$this->view->title = 'Forgotten password';
		$this->_helper->layout()->setLayout('notabs');
	}

	/**
	 * If the correct email/reset_key combination is provided, allows a user to reset their password
	 */
	public function resetPasswordAction() {
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
                    $this->flashMessage('Both passwords are required', 'error');
				} else if ($password != $password2) {
                    $this->flashMessage('Passwords do not match', 'error');
				} else if (strlen($password) < 4) {
                    $this->flashMessage('Password must be at least 4 characters', 'error');
				} else {
					$user->fromArray(array('password'=>$password));
					$user->reset_key = null;
					$user->last_sign_in = gmdate('Y-m-d H:i:s');
					$user->save();

					$authAdapter = new Model_User();
					$authAdapter->authName = $user->name;
					$authAdapter->authPassword = $password;
					$this->auth->authenticate($authAdapter);

                    $this->flashMessage('Password changed successfully');
					$this->_helper->redirector->gotoSimple('index', 'index');
				}
			}
		} else {
            $this->flashMessage('Incorrect user/key combination for password reset', 'error');
			$this->_helper->redirector->gotoSimple('index', 'index');
		}
		$this->view->title = 'Reset password';
		$this->_helper->layout()->setLayout('notabs');
	}

	/**
	 * Logs out the current user
	 */
	public function logoutAction()
	{
		$this->auth->clearIdentity();
        $this->flashMessage('Logged out');
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
			$this->view->title = 'Registration success';
		} else {
			$this->view->title = 'Register user';
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
		$this->view->title = 'New User';
		$this->view->titleIcon = 'icon-plus-sign';
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
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Prompts to modify an existing user (also used for creating a new user, indicated by argument).
	 * @user-level manager
	 */
	public function editAction()
	{
		$messageOnSave = 'User saved';
		/** @var Model_User $editingUser */
		switch ($this->_request->getActionName()) {
			case 'new':
				$editingUser = new Model_User(array());
				$messageOnSave = 'User created';
				break;
			case 'register':
				$editingUser = new Model_User(array());
				$messageOnSave = 'User registered';
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
			if (!$this->view->canChangeLevel) {
				unset($params['user_level']);
			}

			// populate the user with submitted values
			$editingUser->fromArray($params);

			$errorMessages = array();
			if (!$this->_request->getParam('name')) {
				$errorMessages[] = 'Please enter a user name';
			}
			if (!$this->_request->getParam('email')) {
				$errorMessages[] = 'Please enter an email address';
			} else if (preg_match('/.*@.*/', $this->_request->getParam('email')) === 0) {
				$errorMessages[] = 'Please enter a valid email address';
			} else if ($this->isRegistration() &&
				!$this->isBritishCouncilEmailAddress($this->_request->getParam('email'))) {
				$errorMessages[] = 'To register, you must use a valid British Council email address';
			}

			if (!$errorMessages) {
                $password = $this->_request->getParam('password');
                $password2 = $this->_request->getParam('password_confirm');
				// don't require a new password for existing users
				if (!$editingUser->id && (!$password || !$password2)) {
					$errorMessages[] = 'Please enter the password in both boxes';
				} else if ($password != $password2) {
					$errorMessages[] = 'Passwords do not match';
				} else if ($password && strlen($password) < 4) {
					$errorMessages[] = 'Password must be at least 4 characters';
				}
			}

			if ($this->isRegistration()) {
				$code = $this->generateCode();
				$editingUser->confirm_email_key = $code;
			}

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
                    $this->flashMessage($message, 'error');
				}
			} else {
				try {
					$editingUser->save();
                    $this->flashMessage($messageOnSave);
					if($this->view->user->isManager) {
						$this->_helper->redirector->gotoSimple('index');
					} else if ($this->isRegistration()) {
						$this->sendRegisterEmail($editingUser);
						$this->_helper->redirector->gotoRoute(['action' => 'register', 'result' => 'success']);
					}
				} catch (Exception $ex) {
					if (strpos($ex->getMessage(), '23000') !== false) {
						if (strpos($ex->getMessage(), 'email') !== false) {
							$message = 'Email address already in use';
						} else {
							$message = 'User name already taken';
						}
                        $this->flashMessage($message, 'error');
					} else {
                        $this->flashMessage($ex->getMessage(), 'error');
					}
				}
			}
		}

		$this->view->userLevels = Model_User::$userLevels;
		$this->view->editingUser = $editingUser;
		$this->view->title = 'Edit User';
		$this->view->titleIcon = 'icon-edit';
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
            $this->flashMessage('User deleted');
		}
		$this->_helper->redirector->gotoSimple('index');
	}

	/**
	 * Manages a user's access rights
	 * @user-level manager
	 */
	public function manageAction() {
		/** @var Model_User $user */
		$user = Model_User::fetchById($this->_request->getParam('id'));
		$this->validateData($user);

		if ($this->_request->isPost()) {
			$user->assignAccess($this->_request->getParam('assigned'));
            $this->flashMessage('User permissions saved');
            $this->_helper->redirector->gotoSimple('index');
		}

		$this->view->title = 'User Permissions';
		$this->view->titleIcon = 'icon-tasks';
		$this->view->editingUser = $user;
		$this->view->twitterPresences = Model_PresenceFactory::getPresencesByType(Enum_PresenceType::TWITTER());
		$this->view->facebookPresences = Model_PresenceFactory::getPresencesByType(Enum_PresenceType::FACEBOOK());
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
				$this->flashMessage('Something went wrong and we could\'nt confirm your email address.', 'error');
				$this->_helper->redirector->gotoSimple('index', 'index');
			}
			$this->flashMessage('Thank you for confirming your email. You can now login.', 'info');
			$this->_helper->redirector->gotoSimple('login', 'user');
		} else {
			$this->flashMessage('Incorrect user/key combination for email confirmation', 'error');
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
		$subject = "You have successfully registered";
		$toEmail = $registeredUser->email;
		$fromEmail = 'do.not.reply@example.com';
		$fromName = 'The British Council Social Media Monitor team';
		$resetLink = $this->getResetLink($registeredUser, 'confirm-email');
		$message = '<p>Hi ' . $registeredUser->name . ',</p>
					<p>Thank you for registering with the British Council Social Monitor</p>
					<p>If you did not register for this service, please ignore this email.</p>
					<p>Otherwise, click this link to confirm your email so that you can login with your new account <a href="' . $resetLink . '">Confirm email</a></p>
					<p>Thanks,<br />the British Council Social Media Monitor team</p>';

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
		$resetLink = $this->getResetLink($user, 'reset-password');
		$message = '<p>Hi ' . $user->name . ',</p>
					<p>A request to reset the password for your British Council Social Media Monitor account was recently made.</p>
					<p>If you did not request a reset, please ignore this email.</p>
					<p>Otherwise, click this link to reset your password <a href="' . $resetLink . '">Reset password</a></p>
					<p>Thanks,<br />the British Council Social Media Monitor team</p>';
		$this->sendEmail(
			$message,
			'do.not.reply@example.com',
			'The British Council Social Media Monitor team',
			$user->email,
			'Password reset'
		);
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
	 * @return bool
	 */
	private function isRegistration()
	{
		return $this->_request->getActionName() === 'register';
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
	 * Tests whether email is a british council email address
	 *
	 * For testing purposes it also passes if the email address is @outlandish.com
	 *
	 * @param $email
	 * @return bool
	 */
	private function isBritishCouncilEmailAddress($email)
	{
		return (preg_match('/@britishcouncil\.[\.a-z]{2,5}$/i', $email) === 1) || (preg_match('/@outlandish.com$/i', $email) === 1);
	}
}
