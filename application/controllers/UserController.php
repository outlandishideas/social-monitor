<?php

class UserController extends BaseController
{
	protected $publicActions = array('login', 'forgotten', 'reset-password');

	/**
	 * Displays a list of all users.
	 * If current user is admin, also shows links for modifying each user
	 * @permission list_user
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
				// (in)validate the user's token, and update the last sign-in date
				$user = Model_User::fetchById($this->auth->getIdentity());
				$token = $user->getTwitterToken();
				try {
					$token->apiRequest('account/verify_credentials');
				} catch (Exception_TwitterApi $ex) {
					$user->token_id = null;
					$this->_helper->FlashMessenger(array('error' => 'Twitter authorisation expired. Please sign in again.'));
				}
				$user->last_sign_in = gmdate('Y-m-d H:i:s');
				$user->save();
				
				$this->redirect($this->_request->redirect_to);
			} else {
				$this->view->redirect_to = $this->_request->redirect_to;
				$this->_helper->FlashMessenger(array('error' => 'Incorrect username/password'));
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
				$this->_helper->FlashMessenger(array('error' => 'Please enter a username or email address'));
			} else {
				$user = Model_User::fetchBy('name', $this->_request->username);
				if (!$user) {
					$user = Model_User::fetchBy('email', $this->_request->username);
				}

				if (!$user) {
					$this->_helper->FlashMessenger(array('error' => 'User not found'));
				} else {
					$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
					$user->reset_key = substr( str_shuffle( $chars ), 0, 16);
					$user->save();

					try {
						$resetLink = $this->_request->getScheme() . '://' . $this->_request->getHttpHost() . $this->view->url(array('action'=>'reset-password')) . '?name=' . urlencode($user->name) . '&reset_key=' . $user->reset_key;
						$message = '<p>Hi ' . $user->name . ',</p>
							<p>A request to reset the password for your 33 digital account was recently made.</p>
							<p>If you did not request a reset, please ignore this email.</p>
							<p>Otherwise, click this link to reset your password <a href="' . $resetLink . '">Reset password</a></p>
							<p>Thanks,<br />the 33 digital password keeper</p>';
						$this->sendEmail(
							$message,
							'do.not.reply@example.com',
							'The 33 digital password keeper',
							$user->email,
							'Password reset'
						);
						$this->_helper->FlashMessenger(array('info' => 'You should receive an email shortly with a password reset link'));
					} catch (Exception $ex) {
						$this->_helper->FlashMessenger(array('error' => 'Failed to send reset email.<br />Please ask an admin user to reset your password'));
					}
				}
			}
		}
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
					$this->_helper->FlashMessenger(array('error' => 'Both passwords are required'));
				} else if ($this->_request->password != $this->_request->password_confirm) {
					$this->_helper->FlashMessenger(array('error' => 'Passwords do not match'));
				} else if (strlen($this->_request->password) < 4) {
					$this->_helper->FlashMessenger(array('error' => 'Password must be at least 4 characters'));
				} else {
					$user->fromArray(array('password'=>$this->_request->password));
					$user->reset_key = null;
					$user->last_sign_in = gmdate('Y-m-d H:i:s');
					$user->save();

					$authAdapter = new Model_User();
					$authAdapter->authName = $user->name;
					$authAdapter->authPassword = $this->_request->password;
					$this->auth->authenticate($authAdapter);

					$this->_helper->FlashMessenger(array('info' => 'Password changed successfully'));
					$this->_helper->redirector->gotoSimple('index', 'index');
				}
			}
		} else {
			$this->_helper->FlashMessenger(array('error' => 'Incorrect user/key combination for password reset'));
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
		$this->_helper->FlashMessenger(array('info' => 'Logged out'));
		$this->_helper->redirector->gotoSimple('index', 'index');
	}

	/**
	 * Prompts to create a new dashboard user
	 * @permission create_user
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->view->title = 'New User';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Prompts to modify the current user
	 * @permission edit_self
	 */
	public function editSelfAction()
	{
		// do exactly the same as in editAction, but with different permissions
		$this->editAction();
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Prompts to modify an existing user (also used for creating a new user, indicated by argument).
	 * @permission edit_user
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

		if ($this->_request->isPost()) {
			// prevent hackers upgrading their own user level
			$params = $this->_request->getParams();
			if (!$this->view->user->canPerform('change_user_level')) {
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
					$this->_helper->FlashMessenger(array('error' => $message));
				}
			} else {
				try {
					$editingUser->save();
					$this->_helper->FlashMessenger(array('info' => 'User saved'));
					$this->_helper->redirector->gotoSimple('index');
				} catch (Exception $ex) {
					if (strpos($ex->getMessage(), '23000') !== false) {
						if (strpos($ex->getMessage(), 'email') !== false) {
							$message = 'Email address already in use';
						} else {
							$message = 'User name already taken';
						}
						$this->_helper->FlashMessenger(array('error' => $message));
					} else {
						$this->_helper->FlashMessenger(array('error' => $ex->getMessage()));
					}
				}
			}
		}

		$this->view->userLevels = Model_User::$userLevels;
		$this->view->editingUser = $editingUser;
		$this->view->title = 'Edit User';
	}

	/**
	 * Deletes a user
	 * @permission delete_user
	 */
	public function deleteAction()
	{
		$user = Model_User::fetchById($this->_request->id);

		$this->validateData($user);

		if ($this->_request->isPost()) {
			$user->delete();
			$this->_helper->FlashMessenger(array('info' => 'User deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}

	public function statusAction(){
		$data = array(
			'logoutUrl' => $this->view->url(array('controller' => 'user', 'action' => 'logout'))
		);

		$this->apiSuccess($data);
	}
}
