<?php

namespace Outlandish\SocialMonitor\Report;

class ReportDownloader
{

    /**
     * The url of the site to download the report from
     * eg. https://socialmonitor.britishcouncil.net
     *
     * @var string
     */
    private $baseUrl;
    /**
     * The url for the pdf generatoring service
     * eg. https://pdf.sundivenetworks.net/convert/outlandish.json
     *
     * @var string
     */
    private $pdfUrl;

    public function __construct($pdfUrl, $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->pdfUrl = $pdfUrl;
    }

    public function getUrl(Reportable $reportable, \DateTime $then, \DateTime $now)
    {
        $reportUrl = "{$this->baseUrl}/{$reportable->getBaseType()}/report/id/{$reportable->getId()}/from/{$then->format('Y-m-d')}/to/{$now->format('Y-m-d')}";
        $url = "{$this->pdfUrl}?urls[]={$reportUrl}";
        $contents = file_get_contents($url);
        $data = json_decode($contents);
        return $data[0];
    }

}