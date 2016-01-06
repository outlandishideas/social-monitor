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
        // TODO: Implement getStatuses() method.
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
            throw new SocialMonitorException("No companies for logged in user");
        }

        return (object)$company[0];
    }
}