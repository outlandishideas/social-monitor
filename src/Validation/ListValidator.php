<?php
/**
 * Created by PhpStorm.
 * User: Jannik
 * Date: 20.05.16
 * Time: 15:40
 */

namespace Outlandish\SocialMonitor\Validation;


class ListValidator extends BaseValidator
{
    private $listElements = [];
    protected $errorMessage = 'route.base.validation.not-in-list';

    public function __construct($listElements)
    {
        $this->listElements = $listElements;
    }

    public function isValid($candidate){
        return in_array($candidate, $this->listElements);
    }
}

