<?php

class UserController extends BaseController
{
	protected static $publicActions = array('login', 'forgotten', 'reset-password');

	public function init() {
		parent::init();
		$this->view->titleIcon = 'icon-group';
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
			$authAdapter->authName = $this->_request->username;
			$authAdapter->authPassword = $this->_request->password;
			$result = $this->auth->authenticate($authAdapter);
			
			if ($result->isValid())	{
				$user = Model_User::fetchById($this->auth->getIdentity());
				$user->last_sign_in = gmdate('Y-m-d H:i:s');
				$user->save();
				
				$this->redirect($this->_request->redirect_to);
			} else {
				$this->view->redirect_to = $this->_request->redirect_to;
                $this->flashMessage('Incorrect username/password', 'error');
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
			if (!$this->_request->username) {
                $this->flashMessage('Please enter a username or email address', 'error');
			} else {
				$user = Model_User::fetchBy('name', $this->_request->username);
				if (!$user) {
					$user = Model_User::fetchBy('email', $this->_request->username);
				}

				if (!$user) {
                    $this->flashMessage('User not found', 'error');
				} else {
					$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
					$user->reset_key = substr( str_shuffle( $chars ), 0, 16);
					$user->save();

					try {
						$resetLink = $this->_request->getScheme() . '://' . $this->_request->getHttpHost() . $this->view->url(array('action'=>'reset-password')) . '?name=' . urlencode($user->name) . '&reset_key=' . $user->reset_key;
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
		if ($this->_request->name && $this->_request->reset_key) {
			$user = Model_User::fetchAll('name=? AND reset_key=?', array($this->_request->name, $this->_request->reset_key));
			if ($user) {
				$user = $user[0];
			}
		} else {
			$user = null;
		}

		if ($user) {
			$this->view->editingUser = $user;
			if ($this->_request->isPost()) {
				if (!$this->_request->password || !$this->_request->password_confirm) {
                    $this->flashMessage('Both passwords are required', 'error');
				} else if ($this->_request->password != $this->_request->password_confirm) {
                    $this->flashMessage('Passwords do not match', 'error');
				} else if (strlen($this->_request->password) < 4) {
                    $this->flashMessage('Password must be at least 4 characters', 'error');
				} else {
					$user->fromArray(array('password'=>$this->_request->password));
					$user->reset_key = null;
					$user->last_sign_in = gmdate('Y-m-d H:i:s');
					$user->save();

					$authAdapter = new Model_User();
					$authAdapter->authName = $user->name;
					$authAdapter->authPassword = $this->_request->password;
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
		switch ($this->_request->action) {
			case 'new':
				$editingUser = new Model_User(array());
				break;
			case 'edit-self':
				$editingUser = new Model_User($this->view->user->toArray(), true);
				break;
			case 'edit':
			default:
				$editingUser = Model_User::fetchById($this->_request->id);
				break;
		}

		$this->validateData($editingUser);
		$this->view->canChangeLevel = $this->view->user->isManager;

		if ($this->_request->isPost()) {
			// prevent hackers upgrading their own user level
			$params = $this->_request->getParams();
			if (!$this->view->canChangeLevel) {
				unset($params['user_level']);
			}

			// populate the user with submitted values
			$editingUser->fromArray($params);

			$errorMessages = array();
			if (!$this->_request->name) {
				$errorMessages[] = 'Please enter a user name';
			}
			if (!$this->_request->email) {
				$errorMessages[] = 'Please enter an email address';
			} else if (preg_match('/.*@.*/', $this->_request->email) === 0) {
				$errorMessages[] = 'Please enter a valid email address';
			}

			if (!$errorMessages) {
				// don't require a new password for existing users
				if (!$editingUser->id && (!$this->_request->password || !$this->_request->password_confirm)) {
					$errorMessages[] = 'Please enter the password in both boxes';
				} else if ($this->_request->password != $this->_request->password_confirm) {
					$errorMessages[] = 'Passwords do not match';
				} else if ($this->_request->password && strlen($this->_request->password) < 4) {
					$errorMessages[] = 'Password must be at least 4 characters';
				}
			}

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
                    $this->flashMessage($message, 'error');
				}
			} else {
				try {
					$editingUser->save();
                    $this->flashMessage('User saved');
					$this->_helper->redirector->gotoSimple('index');
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
		$user = Model_User::fetchById($this->_request->id);

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
		$user = Model_User::fetchById($this->_request->id);
		$this->validateData($user);

		if ($this->_request->isPost()) {
			$user->assignAccess($this->_request->assigned);
            $this->flashMessage('User permissions saved');
			$this->_helper->redirector->gotoSimple('index');
		}

		$this->view->title = 'User Permissions';
		$this->view->titleIcon = 'icon-tasks';
		$this->view->editingUser = $user;
		$this->view->twitterPresences = Model_Presence::fetchAllTwitter();
		$this->view->facebookPresences = Model_Presence::fetchAllFacebook();
		$this->view->countries = Model_Country::fetchAll();
		$this->view->groups = Model_Group::fetchAll();
	}
}
