<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 10/06/2015
 * Time: 11:22
 */

namespace Outlandish\SocialMonitor\TableIndex;


use Outlandish\SocialMonitor\TableIndex\Header\Header;

class TableIndex {

    /**
     * @var \Header_Abstract[]
     */
    private $headers = array();

    public function addHeader(Header $header)
    {
        $this->headers[] = $header;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getRows(array $data)
    {
        $rows = [];

        foreach ($data as $model) {
            $row = new \stdClass();
            $row->id = $model->id;
            foreach ($this->headers as $header) {
                $row->{$header->getName()} = $header->getTableCellValue($model);
            }
            $rows[] = $row;
        }

        return $rows;
    }

}