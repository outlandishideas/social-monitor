<?php


class FeedbackController extends BaseController {

    protected static $publicActions = array('index');
    private $feedbackRecipient = null;

    /**
	 * Sends an email to somebody giving feedback
	 */
	function indexAction() {
        $valid = true;
        $success = false;
        $error = '';
        if ($this->_request->isPost()) {

            if(!$this->feedbackRecipient) {
                $this->feedbackRecipient = BaseController::getOption('email-feedback-to-address');
            }

            // Show an error if missing any parameters.
            // This is also enforced on the front end form.
            if(!$this->_request->getParam('name')) {
                $error = 'Please enter your name.';
                $valid = false;
            } else if(!$this->_request->getParam('from') ||
                !filter_var($this->_request->getParam('from') , FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
                $valid = false;
            } else if(!$this->_request->getParam('body')) {
                $error = 'Please enter your message.';
                $valid = false;
            }

            // Limit body, from and name fields to 1000, 64 and 64 characters respectively.
            // This is also enforced on the front end.
            $name = substr($this->_request->getParam('name'),0,64);
            $from = substr($this->_request->getParam('from'),0,64);
            $body = substr($this->_request->getParam('body'),0,1000);
            $subject = 'British Council Social Monitor feedback from '.$name;

            if($valid) {
                $success = (bool) $this->sendEmail($body, $from, $name, $this->feedbackRecipient, $subject, true);
            }

        }

        $this->apiSuccess(['success'=>$success,'error'=>$error]);
	}
}