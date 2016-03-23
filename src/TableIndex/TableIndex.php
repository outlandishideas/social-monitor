<?php

namespace Outlandish\SocialMonitor\TableIndex;

use Model_Campaign;
use Model_Presence;
use Model_Region;
use Outlandish\SocialMonitor\TableIndex\Header\Header;
use Outlandish\SocialMonitor\TableIndex\TableSource\TableSource;

class TableIndex {

    /** @var Header[] */
    private $headers = array();

    /** @var TableSource */
    protected $dataSource;

    /** @var string */
    protected $indexName;

    protected $tableData = null;

	/**
	 * @param string $indexName
	 * @param TableSource $dataSource
	 * @param Header[] $headers
	 */
    function __construct($indexName, $dataSource, $headers = array())
    {
        $this->indexName = $indexName;
        $this->dataSource = $dataSource;
		foreach ($headers as $header) {
			$this->addHeader($header);
		}
    }

    public function addHeader(Header $header)
    {
        $this->headers[] = $header;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getDataSource()
    {
        return $this->dataSource;
    }

    public function getIndexName()
    {
        return $this->indexName;
    }

    public function getTableData()
    {
        if ($this->tableData === null) {
            $this->tableData = $this->dataSource->getTableData();
        }
        return $this->tableData;
    }

    /**
     * @return array
     */
    public function generateRows()
    {
        $rows = [];

        $data = $this->getTableData();
        foreach ($data as $model) {
            /** @var Model_Presence|Model_Campaign $model */
            /** @var Model_Region $region */
            $region = $model->getRegion();
            $row = new \stdClass();
            $row->id = $model->id;
            $row->region_id = $region ? $region->id : null;
            $row->type = $model instanceof Model_Presence ? $model->getType()->getValue() : null;
            foreach ($this->headers as $header) {
                $name = $header->getName();
                $row->{$name} = $header->getTableCellValue($model);
            }
            $rows[] = $row;
        }

        return $rows;
    }

}