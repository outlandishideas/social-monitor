<?php
namespace Outlandish\SocialMonitor\Validation;

/**
 * Validator for password fields
 *
 * @package Outlandish\SocialMonitor\Validation
 */
class PasswordValidator extends BaseValidator
{
    protected $errorMessage = 'route.base.validation.wrong-password';
    private $user;

	/**
	 * @param Model_User $user The user the the the password validation is to be against
	 */
    function __construct($user)
    {
        $this->user = $user;
    }

	/**
	 * Returns true if the sha1 of the $password string matches the $user->password_hash
	 *
	 * @param string $password the string to be validated
	 * @return bool
	 */
    public function isValid($password){
        return $this->user &&
			sha1(\Model_User::PASSWORD_SALT.$password) === $this->user->password_hash;
    }
}