<?php
/**
 * Created by PhpStorm.
 * User: Jannik
 * Date: 20.05.16
 * Time: 16:55
 */

namespace Outlandish\SocialMonitor\Validation;


class EmailValidator extends BaseValidator
{
    protected $errorMessage = 'route.base.validation.email-forbidden';

    public function isValid($candidate){
        return filter_var($candidate, FILTER_VALIDATE_EMAIL);
    }
}