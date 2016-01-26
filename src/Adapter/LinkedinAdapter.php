<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 06/01/2016
 * Time: 11:00
 */

namespace Outlandish\SocialMonitor\Adapter;


use LinkedIn\LinkedIn;
use Outlandish\SocialMonitor\Exception\SocialMonitorException;
use Outlandish\SocialMonitor\Models\LinkedinStatus;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Outlandish\SocialMonitor\Models\Status;

class LinkedinAdapter extends AbstractAdapter
{

    private $token = '***REMOVED***';

    /**
     * @var LinkedIn
     */
    private $linkedIn;

    public function __construct(LinkedIn $linkedIn)
    {
        $this->linkedIn = $linkedIn;
    }

    /**
     *
     * Here we take a handle, as we might not yet know the UID
     *
     * @param $handle
     * @return PresenceMetadata
     */
    public function getMetadata($handle)
    {
        $company = $this->getCompanyFromHandle($handle);

        $response = (object)$this->linkedIn->get("/companies/{$company->id}:(id,name,num-followers,square-logo-url)");

        $metadata = new PresenceMetadata();
        $metadata->uid = $company->id;
        $metadata->image_url = $response->squareLogoUrl;
        $metadata->name = $response->name;
        $metadata->popularity = $response->numFollowers;
        $metadata->page_url = "https://www.linkedin.com/company/{$company->id}";

        return $metadata;
    }

    /**
     * @param $handle
     * @param $accessToken
     * @return PresenceMetadata
     */
    public function getMetadataWithAccessToken($handle, $accessToken)
    {
//        $this->linkedIn->setAccessToken($accessToken);
        $this->linkedIn->setAccessToken($this->token);
        $metadata = $this->getMetadata($handle);

        return $metadata;
    }

    /**
     *
     * Return all statuses posted by the presence with 3rd party ID $pageUID.
     * Also, additionally include statuses mentioning $handle.
     *
     * Since is either a DateTime or a status id, depending on implementation
     *
     * @param $pageUID
     * @param string $handle
     * @param mixed $since
     * @return Status[]
     */
    public function getStatuses($pageUID, $since, $handle)
    {
        $statuses = [];

        $response = $this->linkedIn->get("/companies/{$pageUID}/updates", ['count' => 100]);

        if (!empty($response['values'])) {
            foreach ($response['values'] as $post) {


                if (isset($post['updateContent']['companyStatusUpdate'])) {
                    $status = $this->parseCompanyUpdate($post);
                } else if (isset($post['updateContent']['companyJobUpdate'])) {
                    $status = $this->parseCompanyJob($post);
                } else if (isset($post['updateContent'][''])) {
                    continue;
                } else {
                    //unknown post type
                    continue;
                }



                $statuses[] = $status;
            }
        }

        return $statuses;

    }

    /**
     * Linkedin needs an access token so we need to pass it through before calling the getStatuses method
     *
     * @param $pageUID
     * @param $since
     * @param $handle
     * @param $accessToken
     * @return PresenceMetadata
     */
    public function getStatusesWithAccessToken($pageUID, $since, $handle, $accessToken)
    {
//        $this->linkedIn->setAccessToken($accessToken);
        $this->linkedIn->setAccessToken($this->token);
        $metadata = $this->getStatuses($pageUID, $since, $handle);

        return $metadata;
    }

    /**
     * @param $handle
     * @return array|null
     * @throws SocialMonitorException
     */
    protected function getCompanyFromHandle($handle)
    {
        $companies = $this->linkedIn->get('/companies', ['is-company-admin' => 'true']);

        $companyId = null;

        $company = null;
        if (!empty($companies['values'])) {
            $company = array_filter($companies['values'], function ($company) use ($handle) {
                return $company['name'] == $handle && isset($company['id']);
            });
        }

        if (empty($company)) {
            throw new SocialMonitorException("The company \"{$handle}\" is not owned by you and cannot be fetched.");
        }

        return (object)array_values($company)[0];
    }

    private function extractLinks($message)
    {
        $links = array();
        if (preg_match_all('/[^\s]{5,}/', $message, $tokens)) {
            foreach ($tokens[0] as $token) {
                $token = trim($token, '.,;!"()');
                if (filter_var($token, FILTER_VALIDATE_URL)) {
                    try {
                        $links[] = $token;
                    } catch (RuntimeException $ex) {
                        // ignore failed URLs
                        $failedLinks[] = $token;
                    }
                }
            }
        }
        return $links;
    }

    private function parseCompanyUpdate($post)
    {
        $postContent = $post['updateContent']['companyStatusUpdate'];

        $status = new LinkedinStatus();

        $status->comments = $post['updateComments']['_total'];
        $status->likes = $post['numLikes'];
        $status->message = $postContent['share']['comment'];
        $status->postId = $postContent['share']['id'];
        $status->created_time = $postContent['share']['timestamp']/1000;
        $status->type = 'status-update';

        $submittedLink = !empty($postContent['share']['content']['submittedUrl']) ? [$postContent['share']['content']['submittedUrl']] : [];
        $messageLinks = $status->message ? $this->extractLinks($status->message) : [];
        $status->links = array_merge($submittedLink, $messageLinks);

        return $status;
    }

    private function parseCompanyJob($post)
    {
        $postContent = $post['updateContent']['companyJobUpdate'];

        $status = new LinkedinStatus();

        $status->comments = $post['isCommentable'] ? $post['updateComments']['_total'] : 0;
        $status->likes = $post['isLikable'] ? $post['numLikes'] : 0;
        $status->message = $postContent['job']['description'];
        $status->postId = $postContent['job']['id'];
        $status->created_time = $post['timestamp']/1000;
        $status->type = 'job-posting';

        $submittedLink = !empty($postContent['job']['siteJobRequest']['url']) ? [$postContent['job']['siteJobRequest']['url']] : [];
        $messageLinks = $status->message ? $this->extractLinks($status->message) : [];
        $status->links = array_merge($submittedLink, $messageLinks);

        return $status;
    }

    public function getChannelWithAccessToken($handle, $accessToken)
    {
        $this->linkedIn->setAccessToken($accessToken);
        $this->getCompanyFromHandle($handle);
    }
}