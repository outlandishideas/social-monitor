<?php

class ErrorController extends BaseController
{
	protected static $publicActions = array('error');

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
                $this->view->pageTitle = $this->translator->trans('route.error.error.page-title-404'); //'Page not found';
                $this->view->text = $this->translator->trans('route.error.error.message.404'); //'Please check the url';
                break;
            default:
                // application error
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->text = $this->translator->trans('route.error.error.message.500'); //'Sorry, something has gone wrong';
                break;
        }

        $this->view->exception = $errors->exception;
        $this->view->request   = $errors->request;
    }

}

