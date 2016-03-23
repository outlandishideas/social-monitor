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
                $error = $this->translator->trans('Feedback.index.error.missing-name');
                $valid = false;
            } else if(!$this->_request->getParam('from') ||
                !filter_var($this->_request->getParam('from') , FILTER_VALIDATE_EMAIL)) {
				$error = $this->translator->trans('Feedback.index.error.missing-email');
                $valid = false;
            } else if(!$this->_request->getParam('body')) {
				$error = $this->translator->trans('Feedback.index.error.missing-message');
                $valid = false;
            }

            // Limit body, from and name fields to 1000, 64 and 64 characters respectively.
            // This is also enforced on the front end.
            $name = substr($this->_request->getParam('name'),0,64);
            $from = substr($this->_request->getParam('from'),0,64);
            $body = substr($this->_request->getParam('body'),0,1000);
            $subject = $this->config->app->client_name . ' '.str_replace(
					'[]',
					$name,
					$this->translator->trans('Feedback.index.email-subject')
				);

            if($valid) {
                $success = (bool) $this->sendEmail($body, $from, $name, $this->feedbackRecipient, $subject, true);
            }

        }

        $this->apiSuccess(['success'=>$success,'error'=>$error]);
	}
}