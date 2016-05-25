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

			$name = $this->_request->getParam('name');
			$from = $this->_request->getParam('from');
			$body = $this->_request->getParam('body');

            // Show an error if missing any parameters.
            // This is also enforced on the front end form.
            if(!$name) {
                $error = $this->translator->trans('route.feedback.index.error.missing-name');
                $valid = false;
            } else if(!$from || !filter_var($from , FILTER_VALIDATE_EMAIL)) {
				$error = $this->translator->trans('route.feedback.index.error.missing-email');
                $valid = false;
            } else if(!$body) {
				$error = $this->translator->trans('route.feedback.index.error.missing-message');
                $valid = false;
            }

			$resp = $this->recaptcha->verify($this->_request->getParam('g-recaptcha-response'), $_SERVER['REMOTE_ADDR']);
			if (!$resp->isSuccess()) {
				$valid = false;
				$error = $this->translator->trans('recaptcha.failure');
			}

            // Limit body, from and name fields to 1000, 64 and 64 characters respectively.
            // This is also enforced on the front end.
            $name = substr($name, 0, 64);
            $from = substr($from, 0, 64);
            $body = substr($body, 0, 1000);
            $subject = $this->config->app->client_name . ' '.$this->translator->trans('route.feedback.index.email-subject', ['%name%' => $name]);

            if($valid) {
                $success = (bool) $this->sendEmail($body, $from, $name, $this->feedbackRecipient, $subject, true);
            }

        }

        $this->apiSuccess(['success'=>$success,'error'=>$error]);
	}
}