<?php

class ErrorController extends BaseController
{
	protected $publicActions = array('error');

    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');
        
        // $boot = $this->getInvokeArg('bootstrap');
        // if ($boot->hasResource('logger')) {
        	// $boot->getResource('logger')->err($errors->exception);
        // }
        
        switch ($errors->type) { 
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
        
                // 404 error -- controller or action not found
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->title = 'Page not found';
                break;
            default:
                // application error 
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->title = 'Application error';
                break;
        }
        
        $this->view->exception = $errors->exception;
        $this->view->request   = $errors->request;
    }

}

