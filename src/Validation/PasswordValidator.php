<?php
namespace Outlandish\SocialMonitor\Validation;

class PasswordValidator extends BaseValidator
{
    protected $errorMessage = 'route.base.validation.wrong-password';
    private $user;

    /** @var Model_User $user */
    function __construct($user)
    {
        $this->user = $user;
    }

    public function isValid($password){
        if($this->user && sha1(\Model_User::PASSWORD_SALT.$password) === $this->user->password_hash){
            return true;
        }else{
            return false;
        }
    }
}