<?php

use Outlandish\SocialMonitor\TableIndex\Header\Header;
use Outlandish\SocialMonitor\TableIndex\TableIndex;

class Util_Csv {
    /**
     * @param TableIndex $table
     * @return array
     */
    static function generateCsvData($table) {
        $headers = $table->getHeaders();
        $models = $table->getTableData();

        /*if (userHasNoPermissions) {
            $this->view->msg = 'This file cannot be downloaded!';
            $this->_forward('error', 'download');
            return FALSE;
        }*/

        $csvData = array(
            array(),
            array()
        );

        /** @var Header[] $columns */
        $columns = array();
        foreach ($headers as $column) {
            if ($column->isForCsv()) {
                $columns[] = $column;
                $csvData[0][] = $column->getLabel();
                $csvData[1][] = $column->getDescription();
            }
        }

        foreach($models as $model){
            $dataTypes = array();
            if ($model instanceof Model_Campaign) {
                $dataTypes[] = Header::MODEL_TYPE_CAMPAIGN;
                if ($model instanceof Model_Country) {
                    $dataTypes[] = Header::MODEL_TYPE_COUNTRY;
                } else if ($model instanceof Model_Group) {
                    $dataTypes[] = Header::MODEL_TYPE_GROUP;
                } else if ($model instanceof Model_Region) {
                    $dataTypes[] = Header::MODEL_TYPE_REGION;
                }
            } else if ($model instanceof Model_Presence) {
                $dataTypes[] = Header::MODEL_TYPE_PRESENCE;
            }

            $row = array();
            foreach($columns as $column) {
                $output = null;
                foreach ($dataTypes as $type) {
                    if ($column->isAllowedType($type)) {
                        $output = $column->getFormattedValue($model);
                        break;
                    }
                }
                $row[] = $output;
            }
            $csvData[] = $row;
        }

        return $csvData;
    }

    static function outputCsv($data, $filename) {
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename={$filename}.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen("php://output", "w");
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}