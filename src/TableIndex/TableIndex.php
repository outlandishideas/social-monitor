<?php

namespace Outlandish\SocialMonitor\TableIndex;

use Outlandish\SocialMonitor\TableIndex\Header\Header;

class TableIndex {

    /**
     * @var Header[]
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

    /**
     * @param \Model_Campaign[]|\Model_Presence[] $data
     * @return array
     */
    public function getRows(array $data)
    {
        $rows = [];

        foreach ($data as $model) {
            $region = $model->getRegion();
            $row = new \stdClass();
            $row->id = $model->id;
            $row->region_id = $region ? $region->id : null;
            foreach ($this->headers as $header) {
                $name = $header->getName();
                $row->{$name} = $header->getTableCellValue($model);
            }
            $rows[] = $row;
        }

        return $rows;
    }

}