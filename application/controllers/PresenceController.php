<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Matthew
 * Date: 22/03/13
 * Time: 16:51
 * To change this template use File | Settings | File Templates.
 */
class PresenceController extends BaseController
{

    public function indexAction()
    {
        $this->view->title = 'All Presences';
        if ($this->_request->id) {
            $filter = 'campaign_id='. $this->_request->id;
        } else {
            $filter = null;
        }
        $this->view->presences = Model_FacebookPage::fetchAll($filter);
    }

    /**
     * Views a specific presence page
     * @permission view_facebook_page
     */
    public function viewAction()
    {
        $page = Model_FacebookPage::fetchById($this->_request->id);

        $this->view->title = $page->name;
        $this->view->page = $page;
        $this->view->posts = $page->facebookPosts;
        $this->view->defaultLineId = self::makeLineId('Model_FacebookPage', $page->id);
    }



    /**
     * Creates a new presence
     * @permission create_facebook_page
     */
    public function newAction()
    {
        // do exactly the same as in editAction, but with a different title
        $this->editAction();
        if($this->_request->type == 'facebook'){
            $this->view->title = 'Track a Facebook Page';
            $this->view->hintName = "For www.facebook.com/<strong>platform</strong> the username is '<strong>platform</strong>'";
        } else {
            $this->view->title = 'Track a Twitter Handle';
            $this->view->hintName = "For www.twitter.com/<strong>platform</strong> the handle is '<strong>platform</strong>'";
        }
        $this->view->type = $this->_request->type;
        $this->_helper->viewRenderer->setScriptAction('edit');
    }

    /**
     * Edits/creates a presence page
     * @permission edit_facebook_page
     */
    public function editAction()
    {
        if ($this->_request->action == 'edit') {
            $editingPage = Model_FacebookPage::fetchById($this->_request->id);
        } else {
            $editingPage = new Model_FacebookPage();
            $editingPage->campaign_id = $this->_request->id;
        }

        $this->validateData($editingPage, 'page');

        if ($this->_request->isPost()) {
            $editingPage->fromArray($this->_request->getParams());

            $errorMessages = array();
            if (empty($this->_request->username)) {
                $errorMessages[] = 'Please enter a username';
            }

            if (!$errorMessages) {
                try {
                    $editingPage->updateInfo();
                    $editingPage->save();

                    $this->_helper->redirector->gotoSimple('index');
                } catch (Exception $ex) {
                    $errorMessages[] = $ex->getMessage();
                }
            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->_helper->FlashMessenger(array('error'=>$message));
                }
            } else {
                $this->_helper->redirector->gotoSimple('index');
            }
        }

        $this->view->editingPage = $editingPage;
        $this->view->title = 'Edit Facebook Page';
    }

    /**
     * Updates the name, stats, pic etc for the given facebook page
     * @permission update_facebook_page
     */
    public function updateAction()
    {
        $page = Model_FacebookPage::fetchById($this->_request->id);
        $this->validateData($page, 'page');

        $page->updateInfo();
        $page->save();

        $this->_helper->FlashMessenger(array('info'=>'Updated page info from Facebook API'));
        $this->_helper->redirector->gotoSimple('index');
    }

    /**
     * Deletes a presence
     * @permission delete_facebook_page
     */
    public function deleteAction()
    {
        $page = Model_FacebookPage::fetchById($this->_request->id);
        $this->validateData($page, 'page');

        if ($this->_request->isPost()) {
            $page->delete();
            $this->_helper->FlashMessenger(array('info' => 'Page deleted'));
        }
        $this->_helper->redirector->gotoSimple('index');
    }


}
